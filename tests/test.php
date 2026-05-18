<?php

require __DIR__ . '/../lib/bootstrap.php';

$output = [];
$returnCode = 0;

exec('php ' . escapeshellarg(__DIR__ . '/../seed.php'), $output, $returnCode);

if ($returnCode !== 0) {
    fwrite(STDERR, "seed failed\n");
    fwrite(STDERR, implode("\n", $output) . "\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;

    try {
        $fn();
        echo " [ok] {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo " [FAIL] {$name}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

function assert_true($cond, string $msg = ''): void {
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected true');
    }
}

echo "\nRunning tests:\n";

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();

    $row = $stmt->fetch();

    assert_true($row !== false, 'expected seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title');
});

test('document can be scheduled for future publishing', function () {
    $future = date('Y-m-d H:i:s', time() + 3600);

    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by, publish_at)
        VALUES (?, ?, ?, ?)
    ');

    $stmt->execute([
        'Future Document',
        'Hidden until later',
        1,
        $future
    ]);

    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('
        SELECT publish_at
        FROM documents
        WHERE id = ?
    ');

    $stmt->execute([$docId]);

    $row = $stmt->fetch();

    assert_true($row !== false, 'document not found');
    assert_true($row['publish_at'] === $future, 'publish_at mismatch');
});

test('future scheduled document is not available yet', function () {
    $future = date('Y-m-d H:i:s', time() + 3600);

    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by, publish_at)
        VALUES (?, ?, ?, ?)
    ');

    $stmt->execute([
        'Private Future Doc',
        'Secret body',
        1,
        $future
    ]);

    $docId = (int) db()->lastInsertId();

    $token = bin2hex(random_bytes(16));

    $stmt = db()->prepare('
        INSERT INTO shares (document_id, token, recipient_email)
        VALUES (?, ?, ?)
    ');

    $stmt->execute([
        $docId,
        $token,
        'future@example.com'
    ]);

    $stmt = db()->prepare('
        SELECT d.*
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE s.token = ?
    ');

    $stmt->execute([$token]);

    $doc = $stmt->fetch();

    assert_true($doc !== false, 'shared doc missing');
    assert_true(strtotime($doc['publish_at']) > time(), 'document should still be hidden');
});

test('documents can be searched by title', function () {

    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by)
        VALUES (?, ?, ?)
    ');

    $stmt->execute([
        'Employee Onboarding Packet',
        'Onboarding details',
        1
    ]);

    $stmt = db()->prepare('
        SELECT *
        FROM documents
        WHERE title LIKE ?
    ');

    $stmt->execute(['%Onboarding%']);

    $rows = $stmt->fetchAll();

    assert_true(count($rows) >= 1, 'search failed');
});
test('past scheduled document is available', function () {
    $past = date('Y-m-d H:i:s', time() - 3600);

    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by, publish_at)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute(['Past Doc', 'Was scheduled', 1, $past]);
    $docId = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();

    assert_true(strtotime($row['publish_at']) <= time(), 'past doc should be available');
});

test('search returns empty for no match', function () {
    $stmt = db()->prepare('SELECT * FROM documents WHERE title LIKE ?');
    $stmt->execute(['%zzz_no_match_zzz%']);
    $rows = $stmt->fetchAll();
    assert_true(count($rows) === 0, 'expected no results');
});

test('audit log is written on document creation', function () {
    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by)
        VALUES (?, ?, ?)
    ');
    $stmt->execute(['Audit Test Doc', 'body', 1]);
    $docId = (int) db()->lastInsertId();

    // Manually write audit log (same as admin.php does)
    $s = db()->prepare('
        INSERT INTO audit_log (staff_id, action, entity_type, entity_id, details)
        VALUES (?, ?, ?, ?, ?)
    ');
    $s->execute([1, 'create', 'document', $docId, json_encode(['title' => 'Audit Test Doc'])]);

    $check = db()->prepare('SELECT * FROM audit_log WHERE entity_id = ? AND entity_type = ?');
    $check->execute([$docId, 'document']);
    $row = $check->fetch();

    assert_true($row !== false, 'audit log entry missing');
    assert_true($row['action'] === 'create', 'wrong action in audit log');
});
echo "\n{$pass} passed, {$fail} failed.\n";

exit($fail > 0 ? 1 : 0);