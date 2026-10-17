<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_input = trim($_POST['username_or_student_number'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login_input) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE student_number = :input 
                LIMIT 1
            ");
            $stmt->execute([':input' => $login_input]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['program_id'] = $user['program_id'];
                $_SESSION['student_number'] = $user['student_number'];

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid credentials. Check your Student Number/ID and password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReSchedule. | Login</title>
    <link rel="icon" type="image/jpeg" href="assets/reschedule-app-icon.jpeg">
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="auth-page">
    <div class="auth-shell">
        <aside class="auth-visual">
            <div class="brand">ReSchedule<span>.</span></div>
            <h2>Access your campus timetable.</h2>
            <p>Review your schedule, manage room changes, and stay aligned with your academic week without extra confusion.</p>

            <div class="auth-points">
                <div><span>✓</span> Student and staff access in one place</div>
                <div><span>✓</span> Clear weekly timetable visibility</div>
                <div><span>✓</span> Faster request handling and updates</div>
            </div>
        </aside>

        <main class="auth-card">
            <div class="auth-card-inner">
                <div class="auth-header">
                    <h2>Welcome back</h2>
                    <p>Sign in to continue</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-box error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label for="username_or_student_number">Student Number / User ID</label>
                        <input type="text" id="username_or_student_number" name="username_or_student_number" placeholder="Enter your student number or ID" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn-primary">Sign In</button>
                </form>

                <div class="auth-footer">
                    Need an account? <a href="signup.php">Create one</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

