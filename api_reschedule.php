<?php
// Prevent PHP warnings/notices from breaking JSON parsing on mobile
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        // Read raw JSON or standard POST parameters
        $rawInput = file_get_contents('php://input');
        $jsonInput = json_decode($rawInput, true);

        $student_id     = intval($jsonInput['student_id'] ?? $_POST['student_id'] ?? 0);
        $schedule_id    = intval($jsonInput['schedule_id'] ?? $_POST['schedule_id'] ?? 0);
        $reason         = trim($jsonInput['reason'] ?? $_POST['reason'] ?? '');
        $preferred_time = trim($jsonInput['preferred_time'] ?? $_POST['preferred_time'] ?? '');

        if ($student_id <= 0 || $schedule_id <= 0 || empty($reason)) {
            ob_clean();
            echo json_encode([
                'status'  => 'error',
                'message' => 'Missing required fields: student_id, schedule_id, or reason'
            ]);
            exit();
        }

        $existingStmt = $pdo->prepare("SELECT id FROM reschedule_requests WHERE student_id = :student_id AND schedule_id = :schedule_id AND status = 'Pending' LIMIT 1");
        $existingStmt->execute([':student_id' => $student_id, ':schedule_id' => $schedule_id]);
        if ($existingStmt->fetch()) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'You already have a pending request for this schedule.']);
            exit();
        }

        // Insert request into database
        $stmt = $pdo->prepare("
            INSERT INTO reschedule_requests (student_id, schedule_id, reason, preferred_time, status, created_at)
            VALUES (:student_id, :schedule_id, :reason, :preferred_time, 'Pending', NOW())
        ");

        $stmt->execute([
            ':student_id'     => $student_id,
            ':schedule_id'    => $schedule_id,
            ':reason'         => $reason,
            ':preferred_time' => $preferred_time
        ]);

        ob_clean();
        echo json_encode([
            'status'  => 'success',
            'message' => 'Reschedule request submitted successfully'
        ]);
        exit();

    } else if ($method === 'GET') {
        $student_id = intval($_GET['student_id'] ?? 0);

        if ($student_id <= 0) {
            ob_clean();
            echo json_encode([
                'status'  => 'error',
                'message' => 'Invalid or missing student_id'
            ]);
            exit();
        }

        // Fetch request history with schedule details
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                r.student_id,
                r.schedule_id,
                r.reason,
                r.preferred_time,
                r.status,
                r.review_note,
                r.created_at,
                s.type,
                s.day_of_week,
                s.date,
                s.start_time,
                s.end_time,
                COALESCE(c.course_code, s.course_code, 'N/A') AS course_code,
                COALESCE(c.course_name, s.course_name, 'N/A') AS course_name,
                COALESCE(rm.room_name, 'Unassigned') AS room_name
            FROM reschedule_requests r
            JOIN schedules s ON r.schedule_id = s.id
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN rooms rm ON s.room_id = rm.id
            WHERE r.student_id = :student_id
            ORDER BY r.created_at DESC
        ");

        $stmt->execute([':student_id' => $student_id]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        echo json_encode([
            'status' => 'success',
            'data'   => $requests
        ]);
        exit();

    } else {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Method not allowed'
        ]);
        exit();
    }

} catch (PDOException $e) {
    ob_clean();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit();
}
?>