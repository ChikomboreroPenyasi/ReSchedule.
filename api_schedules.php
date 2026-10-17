<?php
header('Content-Type: application/json');
require_once 'db.php';

$program_id = $_GET['program_id'] ?? $_POST['program_id'] ?? '';

if (empty($program_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing program_id']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            COALESCE(c.course_code, s.course_code) AS course_code,
            COALESCE(c.course_name, s.course_name) AS course_name,
            s.day_of_week,
            s.start_time,
            s.end_time,
            s.type,
            s.date,
            s.year_level,
            s.semester,
            r.room_name
        FROM schedules s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN rooms r ON s.room_id = r.id
         WHERE (s.program_id = :program_id
             OR EXISTS (SELECT 1 FROM course_program cp WHERE cp.course_id = s.course_id AND cp.program_id = :mapped_program_id AND cp.year_level = s.year_level AND cp.semester = s.semester))
        ORDER BY s.day_of_week ASC, s.start_time ASC
    ");
    $stmt->execute([':program_id' => $program_id, ':mapped_program_id' => $program_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $timetable = [];
    $assessments = [];

    foreach ($schedules as $row) {
        $type = strtolower(trim($row['type'] ?? ''));
        
        // Match any variation of 'class', 'lecture', etc.
        if ($type === 'class' || $type === 'lecture' || empty($type)) {
            $timetable[] = $row;
        } else {
            // 'ca', 'exam', 'quiz', etc.
            $assessments[] = $row;
        }
    }

    echo json_encode([
        'status' => 'success',
        'timetable' => $timetable,
        'assessments' => $assessments
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
