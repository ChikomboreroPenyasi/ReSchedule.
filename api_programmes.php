<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    $stmt = $pdo->prepare("SELECT id, program_code, program_name FROM programmes ORDER BY program_name ASC");
    $stmt->execute();
    $programmes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'programmes' => $programmes
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>