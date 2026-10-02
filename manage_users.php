<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Administrator') {
    header('Location: login.php');
    exit();
}

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
    <title>ReSchedule. - Manage Users</title>
    <link rel="icon" type="image/jpeg" href="assets/reschedule-app-icon.jpeg">
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="dashboard-body">

<nav class="navbar">
    <div class="logo">ReSchedule<span>.</span></div>
    <div class="user-profile">
        <a href="dashboard.php" class="card-link">&larr; Dashboard</a>
        <a href="logout.php" class="btn-logout">Log Out</a>
    </div>
</nav>

<main class="dashboard-container">
    <header class="welcome-banner">
        <div>
            <span class="eyebrow">Administrator workspace</span>
            <h1>Registered users</h1>
            <p>Review user accounts and academic placement for the whole campus community.</p>
        </div>
    </header>

    <?php if (isset($error)): ?>
        <div class="alert-box error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="dash-card">
        <div class="card-heading-row">
            <h3>Accounts overview</h3>
            <span class="section-hint"><?php echo count($users); ?> records</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="schedule-table">
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
                            <td><span class="badge badge-ca"><?php echo htmlspecialchars($user['role']); ?></span></td>
                            <td><?php echo htmlspecialchars($user['program_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['year_level'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['semester'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

</body>
</html>