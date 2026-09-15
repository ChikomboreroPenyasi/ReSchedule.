<?php
session_start();
require_once 'db.php';

$message = '';
$statusClass = '';
$programs = [];
$year_level = 1;
$semester = 1;

// Fetch available programs directly from the 'programmes' table
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
            // Check if student_number is already registered
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE student_number = :student_number LIMIT 1");
            $checkStmt->execute([':student_number' => $student_number]);

            if ($checkStmt->fetch()) {
                $message = "This Student Number/ID is already registered.";
                $statusClass = "error";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert user saving student_number as primary account identifier
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
    <title>Reschedule - Sign Up</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body.auth-page {
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 2rem 0;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .auth-container {
            width: 100%;
            max-width: 480px;
            padding: 1rem;
        }
        .auth-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .auth-header h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.75rem;
            color: #0f172a;
        }
        .auth-header p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            box-sizing: border-box;
            background-color: #fff;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            background-color: #0284c7;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        .btn-primary:hover {
            background-color: #0369a1;
        }
        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: #64748b;
        }
        .auth-footer a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        @media (max-width: 520px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Create Account</h2>
                <p>Join the Reschedule Portal</p>
            </div>

            <?php if (!empty($message)): ?>
                <div style="background:<?php echo ($statusClass === 'error') ? '#fee2e2' : '#dcfce7'; ?>; color:<?php echo ($statusClass === 'error') ? '#991b1b' : '#166534'; ?>; padding:0.75rem; border-radius:6px; margin-bottom:1.25rem; font-size:0.85rem;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="signup.php" method="POST">
                <div class="form-group">
                    <label for="fullname">Full Name *</label>
                    <input type="text" id="fullname" name="fullname" placeholder="e.g. John Doe" required>
                </div>

                <div class="form-group">
                    <label for="student_number">Student Number / User ID *</label>
                    <input type="text" id="student_number" name="student_number" placeholder="e.g. 202410982" required>
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
                        <label for="year_level">Year Level Enrolled *</label>
                        <select id="year_level" name="year_level" required>
                            <option value="">Select year...</option>
                            <?php for ($level = 1; $level <= 4; $level++): ?>
                                <option value="<?php echo $level; ?>" <?php echo $year_level === $level ? 'selected' : ''; ?>>Year <?php echo $level; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester Enrolled *</label>
                        <select id="semester" name="semester" required>
                            <option value="">Select semester...</option>
                            <option value="1" <?php echo $semester === 1 ? 'selected' : ''; ?>>Semester 1</option>
                            <option value="2" <?php echo $semester === 2 ? 'selected' : ''; ?>>Semester 2</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" placeholder="Create password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                </div>

                <button type="submit" class="btn-primary">Sign Up</button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php">Log In</a>
            </div>
        </div>
    </div>
</body>
</html>
