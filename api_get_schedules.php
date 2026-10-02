<?php
header('Content-Type: application/json');
require_once 'db.php';

$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;

try {
    if ($program_id > 0) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.type, s.day_of_week, s.date, s.start_time, s.end_time, 
                   COALESCE(c.course_code, s.course_code) AS course_code, 
                   COALESCE(c.course_name, s.course_name) AS course_name, 
                   r.room_name 
            FROM schedules s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN rooms r ON s.room_id = r.id
                 WHERE (s.program_id = :program_id
                     OR EXISTS (SELECT 1 FROM course_program cp WHERE cp.course_id = s.course_id AND cp.program_id = :mapped_program_id AND cp.year_level = s.year_level AND cp.semester = s.semester))
            ORDER BY s.type DESC, s.date ASC, s.day_of_week ASC, s.start_time ASC
        ");
        $stmt->execute([':program_id' => $program_id, ':mapped_program_id' => $program_id]);
    } else {
        $stmt = $pdo->query("
            SELECT s.id, s.type, s.day_of_week, s.date, s.start_time, s.end_time, 
                   COALESCE(c.course_code, s.course_code) AS course_code, 
                   COALESCE(c.course_name, s.course_name) AS course_name, 
                   r.room_name 
            FROM schedules s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN rooms r ON s.room_id = r.id
            ORDER BY s.type DESC, s.date ASC, s.day_of_week ASC, s.start_time ASC
        ");
    }

    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data'   => $schedules
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
