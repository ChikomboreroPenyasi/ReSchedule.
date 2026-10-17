<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    $stmt = $pdo->prepare(
        "SELECT e.id,
                e.title,
                COALESCE(e.description, 'Academic notice') AS content,
                TO_CHAR(e.start_datetime, 'YYYY-MM-DD') AS date,
                COALESCE(u.fullname, 'Academic Office') AS author
         FROM events e
         LEFT JOIN users u ON u.id = e.created_by
         WHERE e.visibility IN ('Everyone', 'Students')
           AND (e.end_datetime IS NULL OR e.end_datetime >= NOW())
         ORDER BY e.start_datetime ASC
         LIMIT 10"
    );
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        $items = [[
            'id' => 1,
            'title' => 'Welcome to the new semester',
            'content' => 'Your academic dashboard has been refreshed. Check your timetable, course resources, and upcoming events regularly.',
            'date' => date('Y-m-d'),
            'author' => 'Academic Office'
        ]];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $items
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
