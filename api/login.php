<?php
header('Content-Type: application/json');
require_once '../db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username']) || !isset($data['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing credentials']);
    exit();
}

$stmt = $pdo->prepare("SELECT id, fullname, role, password FROM users WHERE username = ?");
$stmt->execute([$data['username']]);
$user = $stmt->fetch();

if ($user && password_verify($data['password'], $user['password'])) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'fullname' => $user['fullname'],
            'role' => $user['role']
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
}