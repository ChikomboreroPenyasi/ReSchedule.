<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    echo json_encode([
        'status' => 'success',
        'data' => []
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
