<?php
// migrate.php
// Standalone migration and seeding script for BUK EIMS (Bayero University Kano)
// Usage: php migrate.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

echo "==> BUK EIMS Advanced Database Bootstrap & Migration\n";

try {
    $databaseUrl = getenv('DATABASE_URL') ?: '';
    if ($databaseUrl !== '') {
        $masked = preg_replace('/:[^:@]+@/', ':****@', $databaseUrl);
        echo "Using DATABASE_URL: {$masked}\n";
    } else {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbname = getenv('DB_NAME') ?: 'buk_eims';
        echo "Using DB_HOST={$host}, DB_NAME={$dbname}\n";
    }

    $pdo = get_pdo();
    // Force complete schema initialization
    init_schema($pdo);

    $tablesCount = $pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")->fetchColumn();
    $usersCount = $pdo->query("SELECT count(*) FROM users")->fetchColumn();
    $facCount = $pdo->query("SELECT count(*) FROM faculties")->fetchColumn();
    $schedulesCount = $pdo->query("SELECT count(*) FROM exam_schedules")->fetchColumn();

    echo "==> Schema migration completed successfully.\n";
    echo "    Tables in public schema: {$tablesCount}\n";
    echo "    Faculties seeded: {$facCount}\n";
    echo "    Demonstration users: {$usersCount}\n";
    echo "    Timetable exam schedules: {$schedulesCount}\n";
    echo "==> Database is ready.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
