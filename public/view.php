<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$token = $_GET['token'] ?? '';

$stmt = db()->prepare('
    SELECT d.*, s.recipient_email
    FROM shares s
    JOIN documents d ON d.id = s.document_id
    WHERE s.token = ?
');
$stmt->execute([$token]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    render_header('Not found');
    ?>
    <div class="centered-message">
        <h1>Share link not found</h1>
        <p>The link you used is invalid or has been removed.</p>
    </div>
    <?php
    render_footer();
    exit;
}

$now     = date('Y-m-d H:i:s');
$pending = $doc['publish_at'] && $doc['publish_at'] > $now;

render_header($doc['title']);
?>

<?php if ($pending): ?>
    <div class="centered-message">
        <h1>Not yet available</h1>
        <p>This document will be available on
            <strong><?= h(date('F j, Y \a\t g:i A', strtotime($doc['publish_at']))) ?></strong>.
        </p>
        <p>Please check back then.</p>
    </div>
<?php else: ?>
    <h1 class="page-title"><?= h($doc['title']) ?></h1>
    <?php if ($doc['slug']): ?>
        <p class="meta"><code><?= h($doc['slug']) ?></code></p>
    <?php endif ?>
    <p class="meta">Shared with <?= h($doc['recipient_email']) ?></p>
    <pre class="doc-body"><?= h($doc['body']) ?></pre>
<?php endif ?>

<?php render_footer(); ?>