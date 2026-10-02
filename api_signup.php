<?php
header('Content-Type: application/json');
require_once 'db.php';

$fullname       = trim($_POST['fullname'] ?? '');
$student_number = trim($_POST['student_number'] ?? '');
$program_id     = trim($_POST['program_id'] ?? '');
$year_level     = intval($_POST['year_level'] ?? 1);
$semester       = intval($_POST['semester'] ?? 1);
$password       = trim($_POST['password'] ?? '');
$role           = 'Student';

if (empty($fullname) || empty($student_number) || empty($password) || empty($program_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields']);
    exit();
}

try {
    // Check if student_number already exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE student_number = :sn LIMIT 1");
    $checkStmt->execute([':sn' => $student_number]);
    if ($checkStmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Student number already registered']);
        exit();
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user with year_level and semester
    $stmt = $pdo->prepare("
        INSERT INTO users (fullname, student_number, program_id, year_level, semester, password_hash, role, created_at) 
        VALUES (:fullname, :student_number, :program_id, :year_level, :semester, :password_hash, :role, NOW())
    ");
    
    $success = $stmt->execute([
        ':fullname'       => $fullname,
        ':student_number' => $student_number,
        ':program_id'     => $program_id,
        ':year_level'     => $year_level,
        ':semester'       => $semester,
        ':password_hash'  => $hashed_password,
        ':role'           => $role
    ]);

    if ($success) {
        echo json_encode(['status' => 'success', 'message' => 'Account created successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create account']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>