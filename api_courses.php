<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    $programId = isset($_GET['program_id']) ? (int) $_GET['program_id'] : (isset($_GET['programId']) ? (int) $_GET['programId'] : 0);
    $yearLevel = isset($_GET['year_level']) ? (int) $_GET['year_level'] : (isset($_GET['yearLevel']) ? (int) $_GET['yearLevel'] : 0);
    $semester = isset($_GET['semester']) ? (int) $_GET['semester'] : 0;

    if ($programId <= 0 || $yearLevel <= 0 || $semester <= 0) {
        echo json_encode([
            'status' => 'success',
            'data' => []
        ]);
        exit();
    }

    $sql = "
        SELECT c.id,
               c.course_code AS code,
               c.course_name AS name,
               COALESCE(cp.periods_per_week, 3) AS credit_hours,
               CONCAT('This course covers the core principles and practical activities for ', c.course_name, '.') AS description,
               NULL AS resources_url
        FROM courses c
        INNER JOIN course_program cp ON cp.course_id = c.id
        WHERE cp.program_id = :program_id
          AND cp.year_level = :year_level
          AND cp.semester = :semester
        GROUP BY c.id, c.course_code, c.course_name, cp.periods_per_week
        ORDER BY c.course_code ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':program_id' => $programId,
        ':year_level' => $yearLevel,
        ':semester' => $semester,
    ]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $courses
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
