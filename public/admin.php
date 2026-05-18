<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $publishAt = trim($_POST['publish_at'] ?? '');

    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } else {
        $publishAtValue = $publishAt !== '' ? str_replace('T', ' ', $publishAt) . ':00' : null;

        $stmt = db()->prepare('
            INSERT INTO documents (title, body, created_by, publish_at)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$title, $body, $staff['id'], $publishAtValue]);

        $docId = (int) db()->lastInsertId();

        audit_log('create', 'document', $docId, [
            'title' => $title,
            'publish_at' => $publishAtValue,
        ]);

        header('Location: /admin.php?created=' . $docId);
        exit;
    }
}

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $stmt = db()->prepare('
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        WHERE d.title LIKE ?
        ORDER BY d.created_at DESC
    ');
    $stmt->execute(['%' . $q . '%']);
    $docs = $stmt->fetchAll();
} else {
    $docs = db()->query('
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        ORDER BY d.created_at DESC
    ')->fetchAll();
}

render_header('Admin', $staff);

?>
<h1 class="page-title">Admin</h1>

<p class="page-subtitle">Create documents and generate share links for recipients.</p>

<?php if (!empty($_GET['created'])): ?>
    <div class="banner banner-success">Document #<?= (int) $_GET['created'] ?> created.</div>
<?php endif ?>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">New document</h2>

    <form method="post">
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required value="<?= h($_POST['title'] ?? '') ?>">
        </div>

        <div class="form-field">
            <label for="body">Body</label>
            <textarea id="body" name="body" required><?= h($_POST['body'] ?? '') ?></textarea>
        </div>

        <div class="form-field">
            <label for="publish_at">Publish at</label>
            <input type="datetime-local" id="publish_at" name="publish_at">
            <p class="meta">Leave blank to make the document available immediately.</p>
        </div>

        <button type="submit" class="btn">Create document</button>
    </form>
</section>

<section class="card">
    <h2 class="card-title">Documents</h2>

    <form method="get" style="margin-bottom: 1rem;">
        <div class="form-field">
            <label for="q">Search by title</label>
            <input type="text" id="q" name="q" value="<?= h($q) ?>" placeholder="Search documents by title">
        </div>
        <button type="submit" class="btn">Search</button>
        <?php if ($q !== ''): ?>
            <a href="/admin.php" class="btn-link">Clear search</a>
        <?php endif ?>
    </form>

    <?php if (empty($docs)): ?>
        <p class="empty">No documents found.</p>
    <?php else: ?>
        <table class="data">
            <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Creator</th>
                <th>Publish At</th>
                <th>Created</th>
                <th></th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($docs as $d): ?>
                <tr>
                    <td class="id">#<?= (int) $d['id'] ?></td>
                    <td><?= h($d['title']) ?></td>
                    <td><?= h($d['creator_name']) ?></td>
                    <td><?php if (empty($d['publish_at'])): ?>
        <span style="color:#888">Immediately</span>
    <?php elseif (strtotime($d['publish_at']) > time()): ?>
        <?= h($d['publish_at']) ?>
        <span style="background:#fff3cd;color:#856404;padding:2px 6px;border-radius:3px;font-size:0.75em;font-weight:600;">SCHEDULED</span>
    <?php else: ?>
        <?= h($d['publish_at']) ?>
        <span style="background:#d1e7dd;color:#0a3622;padding:2px 6px;border-radius:3px;font-size:0.75em;font-weight:600;">LIVE</span>
    <?php endif ?></td>
                    <td><?= h($d['created_at']) ?></td>
                    <td><a href="/share.php?doc=<?= (int) $d['id'] ?>" class="btn-link">Create share →</a></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>

<?php render_footer(); ?>