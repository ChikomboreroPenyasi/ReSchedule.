<?php
// XAMPP MySQL/MariaDB connection.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'reschedule';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '3306';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $scheduleColumns = $pdo->query('SHOW COLUMNS FROM schedules')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('year_level', $scheduleColumns, true)) {
        $pdo->exec('ALTER TABLE schedules ADD COLUMN year_level INT NULL DEFAULT 1');
    }
    if (!in_array('semester', $scheduleColumns, true)) {
        $pdo->exec('ALTER TABLE schedules ADD COLUMN semester INT NULL DEFAULT 1');
    }
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}
?>
