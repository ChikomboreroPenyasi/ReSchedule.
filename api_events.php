<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    $stmt = $pdo->prepare("SELECT id, title, description, event_type, start_datetime, end_datetime, is_all_day, venue, visibility FROM events WHERE visibility IN ('Everyone', 'Students') AND (end_datetime IS NULL OR end_datetime >= NOW()) ORDER BY start_datetime ASC LIMIT 100");
    $stmt->execute();
    ob_clean();
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to load events.']);
}
