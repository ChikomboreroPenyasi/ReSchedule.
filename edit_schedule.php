<?php
session_start();

// Import shared database setup
require_once 'db.php';

// Auth Guard: Admin and Lecturer access only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';
$schedule_id = $_GET['id'] ?? null;

if (!$schedule_id) {
    header("Location: dashboard.php");
    exit();
}

try {
    // Fetch programmes for dropdown using shared $pdo from db.php
    $programmes = $pdo->query("SELECT id, program_code, program_name FROM programmes ORDER BY program_code ASC")->fetchAll();

    // Fetch rooms for dropdown
    $rooms = $pdo->query("SELECT id, room_name FROM rooms ORDER BY room_name ASC")->fetchAll();

    // Fetch existing schedule entry details
    $stmt = $pdo->prepare("
        SELECT s.*, COALESCE(c.course_code, s.course_code) AS course_code, COALESCE(c.course_name, s.course_name) AS course_name 
        FROM schedules s 
        LEFT JOIN courses c ON s.course_id = c.id 
        WHERE s.id = :id
    ");
    $stmt->execute([':id' => $schedule_id]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        die("Schedule entry not found.");
    }

    // Handle Form Update Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $type = $_POST['type'] ?? 'Class';
        $program_id = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
        $course_name = trim($_POST['course_name'] ?? '');
        $room_id = !empty($_POST['room_id']) ? intval($_POST['room_id']) : null;
        $day_of_week = $_POST['day_of_week'] ?? null;
        $date = !empty($_POST['date']) ? $_POST['date'] : null;
        $start_time = $_POST['start_time'] ?? null;
        $end_time = $_POST['end_time'] ?? null;

        if (empty($course_code) || empty($course_name) || empty($start_time) || empty($end_time) || empty($room_id)) {
            $error = "Please fill in all required fields.";
        } else {
            // 1. Update or Insert into 'courses' table
            $course_id = $schedule['course_id'];
            if ($course_id) {
                $updateCourse = $pdo->prepare("UPDATE courses SET course_code = :course_code, course_name = :course_name WHERE id = :id");
                $updateCourse->execute([':course_code' => $course_code, ':course_name' => $course_name, ':id' => $course_id]);
            } else {
                $insertCourse = $pdo->prepare("INSERT INTO courses (course_code, course_name) VALUES (:course_code, :course_name)");
                $insertCourse->execute([':course_code' => $course_code, ':course_name' => $course_name]);
                $course_id = $pdo->lastInsertId();
            }

            // 2. Infer Day of Week from Date if CA/Exam
            if (!empty($date) && empty($day_of_week)) {
                $day_of_week = date('l', strtotime($date));
            }

            // 3. Update 'schedules' table across both foreign keys and text columns
            $updateStmt = $pdo->prepare("
                UPDATE schedules 
                SET course_id = :course_id, program_id = :program_id, room_id = :room_id, 
                    day_of_week = :day_of_week, start_time = :start_time, end_time = :end_time, 
                    type = :type, date = :date, course_code = :course_code, course_name = :course_name
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':course_id'   => $course_id,
                ':program_id'  => $program_id,
                ':room_id'     => $room_id,
                ':day_of_week' => $day_of_week,
                ':start_time'  => $start_time,
                ':end_time'    => $end_time,
                ':type'        => $type,
                ':date'        => $date,
                ':course_code' => $course_code,
                ':course_name' => $course_name,
                ':id'          => $schedule_id
            ]);

            $message = "Schedule entry updated successfully!";
            
            // Refresh schedule data in scope
            $stmt->execute([':id' => $schedule_id]);
            $schedule = $stmt->fetch();
        }
    }

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule - Edit Schedule Entry</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 2rem auto;
            background: #ffffff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 600; color: #334155; }
        .form-control { width: 100%; padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .alert { padding: 0.8rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .btn-submit { width: 100%; padding: 0.75rem; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #1d4ed8; }
    </style>
</head>
<body class="dashboard-body">

    <main class="dashboard-container">
        <div class="form-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Edit Schedule Entry</h2>
                <a href="dashboard.php" style="color: #2563eb; text-decoration: none; font-weight: 600;">&larr; Back to Dashboard</a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="edit_schedule.php?id=<?php echo htmlspecialchars($schedule_id); ?>" method="POST">
                
                <div class="form-group">
                    <label for="type">Schedule Category</label>
                    <select name="type" id="type" class="form-control" onchange="toggleDateRequirement()">
                        <option value="Class" <?php echo ($schedule['type'] === 'Class') ? 'selected' : ''; ?>>Regular Class</option>
                        <option value="CA" <?php echo ($schedule['type'] === 'CA') ? 'selected' : ''; ?>>Continuous Assessment (CA)</option>
                        <option value="Exam" <?php echo ($schedule['type'] === 'Exam') ? 'selected' : ''; ?>>Final Exam</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="program_id">Target Programme</label>
                    <select name="program_id" id="program_id" class="form-control" required>
                        <option value="">-- Select Programme --</option>
                        <?php foreach ($programmes as $prog): ?>
                            <option value="<?php echo $prog['id']; ?>" <?php echo ($schedule['program_id'] == $prog['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prog['program_code'] . ' - ' . $prog['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="course_code">Course Code</label>
                    <input type="text" name="course_code" id="course_code" class="form-control" value="<?php echo htmlspecialchars($schedule['course_code']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="course_name">Course Title</label>
                    <input type="text" name="course_name" id="course_name" class="form-control" value="<?php echo htmlspecialchars($schedule['course_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="room_id">Venue / Room</label>
                    <select name="room_id" id="room_id" class="form-control" required>
                        <option value="">-- Select Room --</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>" <?php echo ($schedule['room_id'] == $room['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($room['room_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="day-group">
                    <label for="day_of_week">Day of Week</label>
                    <select name="day_of_week" id="day_of_week" class="form-control">
                        <?php 
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        foreach ($days as $day) {
                            $selected = ($schedule['day_of_week'] === $day) ? 'selected' : '';
                            echo "<option value=\"$day\" $selected>$day</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" id="date-group">
                    <label for="date">Specific Date (Required for CA/Exam)</label>
                    <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($schedule['date'] ?? ''); ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" name="start_time" id="start_time" class="form-control" value="<?php echo htmlspecialchars($schedule['start_time']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" name="end_time" id="end_time" class="form-control" value="<?php echo htmlspecialchars($schedule['end_time']); ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Save & Update Schedule</button>
            </form>
        </div>
    </main>

    <script>
        function toggleDateRequirement() {
            const type = document.getElementById('type').value;
            const dateInput = document.getElementById('date');
            
            if (type === 'CA' || type === 'Exam') {
                dateInput.setAttribute('required', 'required');
            } else {
                dateInput.removeAttribute('required');
            }
        }
        // Initialize state on page load
        toggleDateRequirement();
    </script>
</body>
</html>
