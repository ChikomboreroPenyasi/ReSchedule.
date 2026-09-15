<?php
require_once 'db.php';

// Fetch all users with their associated program names
try {
    $stmt = $pdo->query("
        SELECT u.id, u.fullname, u.student_number, u.role, u.year_level, u.semester, p.program_name 
        FROM users u 
        LEFT JOIN programmes p ON u.program_id = p.id 
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $error = "Error fetching users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f4f4f4; }
        .back-link { text-decoration: none; color: #0088cc; font-weight: bold; }
    </style>
</head>
<body class="dashboard-body">

<a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
<h2>Registered Users</h2>

<?php if (isset($error)): ?>
    <p style="color: red;"><?php echo $error; ?></p>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Student Number</th>
            <th>Role</th>
            <th>Programme</th>
            <th>Year</th>
            <th>Semester</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['id']); ?></td>
                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                <td><?php echo htmlspecialchars($user['student_number']); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td><?php echo htmlspecialchars($user['program_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($user['year_level'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($user['semester'] ?? 'N/A'); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>