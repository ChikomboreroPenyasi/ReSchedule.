<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';
require_once 'schedule_exceptions.php';

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
        $type = trim($input['type']);
        if (!in_array($type, ['Class', 'CA', 'Exam'], true)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Invalid schedule type.']);
            exit();
        }
        $date = trim((string) ($input['date'] ?? ''));
        if (in_array($type, ['CA', 'Exam'], true) && $date === '') {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'A specific date is required for a CA or exam.']);
            exit();
        }
        if ($date !== '') {
            $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (!$dateValue || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Invalid schedule date.']);
                exit();
            }
            if (in_array($type, ['CA', 'Exam'], true)) {
                $input['day_of_week'] = $dateValue->format('l');
            }
        }

        $courseStmt = $pdo->prepare('SELECT id FROM courses WHERE course_code = :course_code LIMIT 1');
        $courseStmt->execute([':course_code' => trim($input['course_code'])]);
        $course = $courseStmt->fetch();
        if (!$course) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Course is not registered.']);
            exit();
        }

        $mappingStmt = $pdo->prepare('SELECT program_id, year_level, semester FROM course_program WHERE course_id = :course_id');
        $mappingStmt->execute([':course_id' => $course['id']]);
        $mappings = $mappingStmt->fetchAll();
        if (!$mappings) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Course has no programme assignments.']);
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO schedules
                (program_id, course_id, room_id, course_code, course_name, day_of_week, start_time, end_time, type, year_level, semester, date)
            VALUES
                (:program_id, :course_id, :room_id, :course_code, :course_name, :day_of_week, :start_time, :end_time, :type, :year_level, :semester, :date)
            RETURNING id
        ");
        $pdo->beginTransaction();
        try {
            foreach ($mappings as $mapping) {
                $stmt->execute([
                    ':program_id' => (int) $mapping['program_id'],
                    ':course_id' => (int) $course['id'],
                    ':room_id' => 1,
                    ':course_code' => trim($input['course_code']),
                    ':course_name' => trim($input['course_name']),
                    ':day_of_week' => trim($input['day_of_week']),
                    ':start_time' => trim($input['start_time']),
                    ':end_time' => trim($input['end_time']),
                    ':type' => $type,
                    ':year_level' => (int) $mapping['year_level'],
                    ':semester' => (int) $mapping['semester'],
                    ':date' => $date !== '' ? $date : null
                ]);
                $scheduleId = (int) $stmt->fetchColumn();
                if (in_array($type, ['CA', 'Exam'], true)) {
                    recordAssessmentClassExceptions($pdo, $scheduleId);
                } elseif ($type === 'Class') {
                    refreshClassExceptions($pdo, $scheduleId);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        echo json_encode(['status' => 'success', 'message' => 'Schedule published successfully.']);
        exit();
    }

    $stmt = $pdo->query("
        SELECT COALESCE(c.course_code, s.course_code, 'Course') AS course_code,
               COALESCE(c.course_name, s.course_name, 'Course details unavailable') AS course_name,
               s.day_of_week, s.start_time, s.end_time, s.type
        FROM schedules s
        LEFT JOIN courses c ON c.id = s.course_id
        ORDER BY s.id DESC
    ");
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>
