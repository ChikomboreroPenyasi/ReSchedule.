<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $required = ['course_code', 'course_name', 'day_of_week', 'start_time', 'end_time', 'type'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => "Missing field: {$field}"]);
                exit();
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO schedules
                (course_id, room_id, course_code, course_name, day_of_week, start_time, end_time, type)
            VALUES
                (:course_id, :room_id, :course_code, :course_name, :day_of_week, :start_time, :end_time, :type)
        ");
        $stmt->execute([
            ':course_id' => 1,
            ':room_id' => 1,
            ':course_code' => trim($input['course_code']),
            ':course_name' => trim($input['course_name']),
            ':day_of_week' => trim($input['day_of_week']),
            ':start_time' => trim($input['start_time']),
            ':end_time' => trim($input['end_time']),
            ':type' => trim($input['type'])
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Schedule published successfully.']);
        exit();
    }

    $stmt = $pdo->query("SELECT course_code, course_name, day_of_week, start_time, end_time, type FROM schedules ORDER BY id DESC");
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>
