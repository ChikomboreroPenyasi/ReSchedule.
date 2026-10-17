<?php
session_start();
require_once 'db.php';

$message = '';
$statusClass = '';
$programs = [];
$year_level = 1;
$semester = 1;

try {
    $programsStmt = $pdo->query("
        SELECT id, program_name
        FROM programmes
        ORDER BY id ASC
    ");
    $programs = $programsStmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error loading programs: " . $e->getMessage();
    $statusClass = "error";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $role = $_POST['role'] ?? 'Student';
    $program_id = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
    $year_level = intval($_POST['year_level'] ?? 1);
    $semester = intval($_POST['semester'] ?? 1);

    if (empty($fullname) || empty($student_number) || empty($password)) {
        $message = "Please fill in all required fields.";
        $statusClass = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $statusClass = "error";
    } elseif ($year_level < 1 || $year_level > 4 || $semester < 1 || $semester > 2) {
        $message = "Please select a valid year level and semester.";
        $statusClass = "error";
    } else {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE student_number = :student_number LIMIT 1");
            $checkStmt->execute([':student_number' => $student_number]);

            if ($checkStmt->fetch()) {
                $message = "This Student Number/ID is already registered.";
                $statusClass = "error";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $pdo->prepare("
                    INSERT INTO users (student_number, fullname, password_hash, role, program_id, year_level, semester)
                    VALUES (:student_number, :fullname, :password_hash, :role, :program_id, :year_level, :semester)
                ");
                $insertStmt->execute([
                    ':student_number' => $student_number,
                    ':fullname' => $fullname,
                    ':password_hash' => $hashedPassword,
                    ':role' => $role,
                    ':program_id' => $program_id,
                    ':year_level' => $year_level,
                    ':semester' => $semester
                ]);

                $message = "Registration successful! You can now log in.";
                $statusClass = "success";
            }
        } catch (PDOException $e) {
            $message = "Database Error: " . $e->getMessage();
            $statusClass = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReSchedule. | Sign Up</title>
    <link rel="icon" type="image/jpeg" href="assets/reschedule-app-icon.jpeg">
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="auth-page">
    <div class="auth-shell">
        <aside class="auth-visual">
            <div class="brand"><img src="assets/reschedule-logo.png" alt="ReSchedule. Campus Timetable System"></div>
            <h2>Join the university timetable system.</h2>
            <p>Create your account and get instant access to your academic schedule, campus resources, and timetable updates.</p>

            <div class="auth-points">
                <div><span>✓</span> Simple student and staff onboarding</div>
                <div><span>✓</span> Personalized timetable access</div>
                <div><span>✓</span> Faster communication with academic offices</div>
            </div>
        </aside>

        <main class="auth-card">
            <div class="auth-card-inner">
                <div class="auth-header">
                    <h2>Create account</h2>
                    <p>Sign up to get started</p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert-box <?php echo htmlspecialchars($statusClass === 'error' ? 'error' : 'success'); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form action="signup.php" method="POST" id="signupForm">
                    <div class="form-group">
                        <label for="fullname">Full Name *</label>
                        <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" placeholder="e.g. John Doe" autocomplete="name" required>
                    </div>

                    <div class="form-group">
                        <label for="student_number">Student Number / User ID *</label>
                        <input type="text" id="student_number" name="student_number" value="<?php echo htmlspecialchars($_POST['student_number'] ?? ''); ?>" placeholder="e.g. 202410982" autocomplete="username" required>
                    </div>

                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required>
                            <option value="Student">Student</option>
                            <option value="Lecturer">Lecturer</option>
                            <option value="Administrator">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="program_id">Academic Program</label>
                        <select id="program_id" name="program_id">
                            <option value="">Select Program...</option>
                            <?php foreach ($programs as $prog): ?>
                                <option value="<?php echo $prog['id']; ?>"><?php echo htmlspecialchars($prog['program_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="year_level">Year Level *</label>
                            <select id="year_level" name="year_level" required>
                                <option value="">Select year...</option>
                                <?php for ($level = 1; $level <= 4; $level++): ?>
                                    <option value="<?php echo $level; ?>" <?php echo $year_level === $level ? 'selected' : ''; ?>>Year <?php echo $level; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="semester">Semester *</label>
                            <select id="semester" name="semester" required>
                                <option value="">Select semester...</option>
                                <option value="1" <?php echo $semester === 1 ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="2" <?php echo $semester === 2 ? 'selected' : ''; ?>>Semester 2</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" placeholder="Create password" minlength="6" autocomplete="new-password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" minlength="6" autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn-primary">Create Account</button>
                </form>

                <div class="auth-footer">
                    Already have an account? <a href="login.php">Log In</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
