<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();

$search  = trim($_GET['q'] ?? '');
$results = [];

if ($search !== '') {
    $stmt = db()->prepare('
        SELECT * FROM documents WHERE title LIKE ? ORDER BY created_at DESC
    ');
    $stmt->execute(['%' . $search . '%']);
    $results = $stmt->fetchAll();
}

$docId = (int) ($_GET['doc'] ?? 0);
$doc   = null;

if ($docId) {
    $stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
}

$error         = null;
$created_token = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = (int) ($_POST['doc_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Recipient email is required.';
    } else {
        $token = random_token();
        $stmt  = db()->prepare('
            INSERT INTO shares (document_id, token, recipient_email)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$docId, $token, $email]);
        $shareId = (int) db()->lastInsertId();

        audit_log('create', 'share', $shareId, [
            'document_id'     => $docId,
            'recipient_email' => $email,
        ]);

        $created_token = $token;

        $stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
    }
}

render_header('Share', $staff);
?>

<a href="/admin.php" class="back-link">← back to admin</a>
<h1 class="page-title">Share a document</h1>
<p class="page-subtitle">Find a document by title and generate a share link.</p>

<section class="card">
    <h2 class="card-title">Find document</h2>
    <form method="get" class="search-form">
        <?php if ($docId): ?>
            <input type="hidden" name="doc" value="<?= $docId ?>">
        <?php endif ?>
        <input
            type="text"
            name="q"
            placeholder="Search by title…"
            value="<?= h($search) ?>"
        >
        <button type="submit" class="btn">Search</button>
        <?php if ($search !== ''): ?>
            <a href="/share.php" class="btn-link">Clear</a>
        <?php endif ?>
    </form>

    <?php if ($search !== '' && empty($results)): ?>
        <p class="empty">No documents found matching "<?= h($search) ?>".</p>
    <?php endif ?>

    <?php if (!empty($results)): ?>
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Slug</th>
                    <th>Title</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td class="id">#<?= (int) $r['id'] ?></td>
                        <td><code><?= h($r['slug'] ?? '—') ?></code></td>
                        <td><?= h($r['title']) ?></td>
                        <td>
                            <a href="/share.php?doc=<?= (int) $r['id'] ?>&q=<?= urlencode($search) ?>" class="btn-link">
                                Select →
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>

<?php if ($doc): ?>
    <section class="card">
        <h2 class="card-title">
            Share "<?= h($doc['title']) ?>"
            <?php if ($doc['slug']): ?>
                <code style="font-size:13px; font-weight:normal; margin-left:8px"><?= h($doc['slug']) ?></code>
            <?php endif ?>
        </h2>

        <?php if ($error): ?>
            <div class="banner banner-error"><?= h($error) ?></div>
        <?php endif ?>

        <?php if ($created_token): ?>
            <div class="banner banner-success">
                Share link ready:
                <code>http://<?= h($_SERVER['HTTP_HOST']) ?>/view.php?token=<?= h($created_token) ?></code>
            </div>
        <?php endif ?>

        <form method="post">
            <input type="hidden" name="doc_id" value="<?= (int) $doc['id'] ?>">
            <div class="form-field">
                <label for="email">Recipient email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn">Generate link</button>
        </form>
    </section>
<?php endif ?>

<?php render_footer(); ?>