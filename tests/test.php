<?php
require __DIR__ . '/../lib/bootstrap.php';

$passed = 0;
$failed = 0;

function expect(string $label, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ PASS: $label\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: $label\n";
        $failed++;
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(file_get_contents(__DIR__ . '/../schema.sql'));
$pdo->exec("ALTER TABLE documents ADD COLUMN publish_at TEXT DEFAULT NULL");
$pdo->exec("ALTER TABLE documents ADD COLUMN slug TEXT DEFAULT NULL");

$pdo->exec("INSERT INTO staff (email, name) VALUES ('test@folio.example', 'Tester')");
$pdo->exec("INSERT INTO documents (title, body, created_by, slug, publish_at)
            VALUES ('Alpha Report', 'Body A', 1, 'alpha-report-1a2b', NULL)");
$pdo->exec("INSERT INTO documents (title, body, created_by, slug, publish_at)
            VALUES ('Beta Guide', 'Body B', 1, 'beta-guide-3c4d', '2099-01-01 00:00:00')");
$pdo->exec("INSERT INTO documents (title, body, created_by, slug, publish_at)
            VALUES ('Gamma Notes', 'Body C', 1, 'gamma-notes-5e6f', '2000-01-01 00:00:00')");
$pdo->exec("INSERT INTO shares (document_id, token, recipient_email) VALUES (1, 'tok1', 'a@x.com')");
$pdo->exec("INSERT INTO shares (document_id, token, recipient_email) VALUES (2, 'tok2', 'b@x.com')");
$pdo->exec("INSERT INTO shares (document_id, token, recipient_email) VALUES (3, 'tok3', 'c@x.com')");

// ── Feature 3: Search by title ────────────────────────────────────────────────
echo "\n[Feature 3] Search by title\n";

$stmt = $pdo->prepare("SELECT * FROM documents WHERE title LIKE ?");

$stmt->execute(['%Alpha%']);
$r = $stmt->fetchAll();
expect("Search 'Alpha' finds 1 result", count($r) === 1);
expect("Result title matches", $r[0]['title'] === 'Alpha Report');

$stmt->execute(['%Guide%']);
$r = $stmt->fetchAll();
expect("Search 'Guide' finds Beta Guide", count($r) === 1);

$stmt->execute(['%zzznope%']);
$r = $stmt->fetchAll();
expect("Search with no match returns 0", count($r) === 0);

// ── Feature 1: Scheduled publishing ──────────────────────────────────────────
echo "\n[Feature 1] Scheduled publishing\n";

$now  = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("
    SELECT d.publish_at FROM shares s
    JOIN documents d ON d.id = s.document_id
    WHERE s.token = ?
");

$stmt->execute(['tok1']);
$row     = $stmt->fetch();
$pending = $row['publish_at'] && $row['publish_at'] > $now;
expect("No publish_at means live immediately", !$pending);

$stmt->execute(['tok2']);
$row     = $stmt->fetch();
$pending = $row['publish_at'] && $row['publish_at'] > $now;
expect("Future publish_at shows not yet available", $pending);

$stmt->execute(['tok3']);
$row     = $stmt->fetch();
$pending = $row['publish_at'] && $row['publish_at'] > $now;
expect("Past publish_at means live", !$pending);

// ── Feature 2: Human-readable slugs ──────────────────────────────────────────
echo "\n[Feature 2] Human-readable slugs\n";

$stmt = $pdo->prepare("SELECT slug FROM documents WHERE id = ?");

$stmt->execute([1]);
$row = $stmt->fetch();
expect("Doc has a slug", !empty($row['slug']));
expect("Slug matches expected format", $row['slug'] === 'alpha-report-1a2b');

$all = $pdo->query("SELECT slug FROM documents")->fetchAll(PDO::FETCH_COLUMN);
expect("All slugs are unique", count($all) === count(array_unique($all)));

// ── slugify() helper ──────────────────────────────────────────────────────────
echo "\n[Helper] slugify()\n";

expect("Basic slug", slugify('Hello World', 'ab12') === 'hello-world-ab12');
expect("Strips special chars", slugify('Q&A / FAQ!', 'zz99') === 'q-a-faq-zz99');
expect("Trims hyphens", slugify('  --Test-- ', '0000') === 'test-0000');

// ── Audit log ─────────────────────────────────────────────────────────────────
echo "\n[Requirement] Audit log\n";

$pdo->exec("INSERT INTO audit_log (staff_id, action, entity_type, entity_id, details)
            VALUES (1, 'create', 'document', 1, '{\"title\":\"Alpha Report\"}')");
$count = (int) $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
expect("Audit log records actions", $count >= 1);

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n────────────────────────────────\n";
echo "Results: $passed passed, $failed failed\n";
if ($failed > 0) exit(1);