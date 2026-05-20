<?php
require __DIR__ . '/../lib/bootstrap.php';

$pdo = db();

// Create migrations tracking table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL UNIQUE,
        applied_at TEXT NOT NULL DEFAULT (datetime('now'))
    )
");

$migrationDir = __DIR__;
$files = glob($migrationDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $filename = basename($file);
    $already = $pdo->prepare("SELECT id FROM migrations WHERE filename = ?");
    $already->execute([$filename]);
    if ($already->fetch()) {
        echo "Skipping (already applied): $filename\n";
        continue;
    }
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    $pdo->prepare("INSERT INTO migrations (filename) VALUES (?)")->execute([$filename]);
    echo "Applied: $filename\n";
}

echo "Migrations complete.\n";