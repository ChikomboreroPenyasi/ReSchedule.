<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);

    // Accept student_number or username from input
    $studentNumber = trim($jsonInput['username'] ?? $jsonInput['student_number'] ?? $_POST['username'] ?? $_POST['student_number'] ?? '');
    $password = trim($jsonInput['password'] ?? $_POST['password'] ?? '');

    if (empty($studentNumber) || empty($password)) {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Student number and password are required'
        ]);
        exit();
    }

    // SQL query strictly matching your PostgreSQL column schema
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.student_number, 
            u.password_hash, 
            u.fullname, 
            u.role, 
            u.program_id,
            COALESCE(u.year_level, 1) AS year_level,
            COALESCE(u.semester, 1) AS semester,
            COALESCE(p.program_name, 'General Studies') AS programme_name
        FROM users u
        LEFT JOIN programmes p ON u.program_id = p.id
        WHERE u.student_number = :student_number
        LIMIT 1
    ");
    
    $stmt->execute([':student_number' => $studentNumber]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifies against hashed password or fallback check
    if ($user && ($user['role'] ?? '') !== 'Student') {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'This mobile app is available to students only'
        ]);
        exit();
    }

    if ($user && (password_verify($password, $user['password_hash']) || $password === $user['password_hash'])) {
        ob_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'user'   => [
                'id'             => intval($user['id']),
                'username'       => $user['student_number'],
                'fullname'       => $user['fullname'] ?? $user['student_number'],
                'role'           => $user['role'] ?? 'Student',
                'program_id'     => intval($user['program_id'] ?? 0),
                'programme_name' => $user['programme_name'],
                'year'           => (string)$user['year_level'],
                'semester'       => (string)$user['semester']
            ]
        ]);
        exit();
    } else {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Invalid student number or password'
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
