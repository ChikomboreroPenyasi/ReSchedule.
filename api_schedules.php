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

    $exceptionsStmt = $pdo->prepare("
        SELECT exception.schedule_id,
               TO_CHAR(exception.occurrence_date, 'YYYY-MM-DD') AS occurrence_date,
               COALESCE(class_course.course_code, class_schedule.course_code, 'Course') AS course_code,
               TO_CHAR(class_schedule.start_time, 'HH24:MI') AS start_time,
               TO_CHAR(class_schedule.end_time, 'HH24:MI') AS end_time,
               exception.source_type,
               COALESCE(event.title, source_course.course_code, source_schedule.course_code, 'Schedule item') AS replacement
        FROM schedule_exceptions exception
        JOIN schedules class_schedule ON class_schedule.id = exception.schedule_id
        LEFT JOIN courses class_course ON class_course.id = class_schedule.course_id
        LEFT JOIN events event ON event.id = exception.source_event_id
        LEFT JOIN schedules source_schedule ON source_schedule.id = exception.source_schedule_id
        LEFT JOIN courses source_course ON source_course.id = source_schedule.course_id
        WHERE exception.occurrence_date >= CURRENT_DATE
          AND (class_schedule.program_id = :program_id
               OR EXISTS (
                   SELECT 1 FROM course_program cp
                   WHERE cp.course_id = class_schedule.course_id
                     AND cp.program_id = :mapped_program_id
                     AND cp.year_level = class_schedule.year_level
                     AND cp.semester = class_schedule.semester
               ))
        ORDER BY exception.occurrence_date, class_schedule.start_time
    ");
    $exceptionsStmt->execute([':program_id' => $program_id, ':mapped_program_id' => $program_id]);
    $classExceptions = $exceptionsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'timetable' => $timetable,
        'assessments' => $assessments,
        'class_exceptions' => $classExceptions
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
