<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    $stmt = $pdo->prepare(
        "SELECT 0 AS id,
                'No pending assignments' AS title,
                '' AS course_code,
                DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%Y-%m-%d') AS due_date,
                'No assignments are currently scheduled for this student.' AS description,
                0 AS is_completed"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If a real assignment table exists later, replace this fallback with a real query.
    echo json_encode([
        'status' => 'success',
        'data' => $rows
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
