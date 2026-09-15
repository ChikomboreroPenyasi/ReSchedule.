<?php
session_start();
require_once 'db.php';

// Auth Guard: Admin and Lecturer access only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

// Fetch dropdown data from the local MySQL database.
$programmes = $pdo->query('SELECT id, program_code, program_name FROM programmes ORDER BY program_name ASC')->fetchAll();
$courses = $pdo->query('SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC')->fetchAll();
$rooms = $pdo->query('SELECT id, room_name FROM rooms ORDER BY room_name ASC')->fetchAll();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_programmes = $_POST['program_ids'] ?? []; // Array of selected programme IDs
    $course_id   = $_POST['course_id'] ?? '';
    $room_id     = $_POST['room_id'] ?? '';
    $type        = $_POST['type'] ?? 'Class';
    $day_of_week = $_POST['day_of_week'] ?? 'Monday';
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';
    $year_level  = intval($_POST['year_level'] ?? 1);
    $semester    = intval($_POST['semester'] ?? 1);
    $date        = !empty($_POST['date']) ? $_POST['date'] : null;

    if (empty($selected_programmes) || empty($course_id) || empty($room_id) || empty($start_time) || empty($end_time)) {
        $error = "Please select at least one programme and fill in all required fields.";
    } else {
        $inserted_count = 0;
        $has_error = false;

        // Loop through each selected programme and insert a schedule record
        foreach ($selected_programmes as $program_id) {
            $stmt = $pdo->prepare('
                INSERT INTO schedules
                    (program_id, course_id, room_id, type, day_of_week, start_time, end_time, year_level, semester, date)
                VALUES
                    (:program_id, :course_id, :room_id, :type, :day_of_week, :start_time, :end_time, :year_level, :semester, :date)
            ');

            if ($stmt->execute([
                ':program_id' => (int)$program_id,
                ':course_id' => (int)$course_id,
                ':room_id' => (int)$room_id,
                ':type' => $type,
                ':day_of_week' => $day_of_week,
                ':start_time' => $start_time,
                ':end_time' => $end_time,
                ':year_level' => $year_level,
                ':semester' => $semester,
                ':date' => $date
            ])) {
                $inserted_count++;
            } else {
                $has_error = true;
                $error = "Error inserting schedule for programme ID {$program_id}.";
                break;
            }
        }

        if (!$has_error && $inserted_count > 0) {
            $message = "Schedule published successfully for {$inserted_count} programme(s)!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reschedule - Publish Schedule</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.5rem;
            max-height: 180px;
            overflow-y: auto;
            border: 1px solid #cbd5e1;
            padding: 0.75rem;
            border-radius: 6px;
            background: #f8fafc;
        }
        .checkbox-item {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
    </style>
</head>
<body class="dashboard-body">
    <main class="dashboard-container">
        <div class="dash-card style-form-box" style="max-width: 650px; margin: 2rem auto;">
            <h2>Publish Schedule</h2>
            <p><a href="dashboard.php" class="card-link">&larr; Return to Dashboard</a></p>

            <?php if (!empty($message)): ?>
                <div class="alert-box success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert-box error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="add_schedule.php" method="POST" style="display: grid; gap: 1rem; margin-top: 1rem;">
                <!-- Schedule Category -->
                <div class="form-group">
                    <label for="type">Schedule Category</label>
                    <select name="type" id="type" class="form-control" required>
                        <option value="Class">Regular Class</option>
                        <option value="CA">Continuous Assessment (CA)</option>
                        <option value="Exam">Final Exam</option>
                    </select>
                </div>

                <!-- Multiple Programmes Selection -->
                <div class="form-group">
                    <label>Select Programmes (Check all that apply):</label>
                    <div class="checkbox-grid">
                        <?php foreach ($programmes as $p): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="program_ids[]" value="<?php echo htmlspecialchars($p['id']); ?>">
                                <strong>[<?php echo htmlspecialchars($p['program_code'] ?? ''); ?>]</strong> <?php echo htmlspecialchars($p['program_name'] ?? ''); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Course Dropdown -->
                <div class="form-group">
                    <label for="course_id">Course</label>
                    <select name="course_id" id="course_id" class="form-control" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['id']); ?>">
                                <?php echo htmlspecialchars(($c['course_code'] ?? '') . ' - ' . ($c['course_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Year Level & Semester -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="year_level">Academic Year Level</label>
                        <select name="year_level" id="year_level" class="form-control" required>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select name="semester" id="semester" class="form-control" required>
                            <option value="1">Semester 1</option>
                            <option value="2">Semester 2</option>
                        </select>
                    </div>
                </div>

                <!-- Venue / Room Dropdown -->
                <div class="form-group">
                    <label for="room_id">Venue / Room</label>
                    <select name="room_id" id="room_id" class="form-control" required>
                        <option value="">-- Select Venue --</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo htmlspecialchars($r['id']); ?>">
                                <?php echo htmlspecialchars($r['room_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Day of Week -->
                <div class="form-group">
                    <label for="day_of_week">Day of Week</label>
                    <select name="day_of_week" id="day_of_week" class="form-control" required>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                    </select>
                </div>

                <!-- Start Time & End Time -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" name="start_time" id="start_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" name="end_time" id="end_time" class="form-control" required>
                    </div>
                </div>

                <!-- Specific Date for Exams/CAs -->
                <div class="form-group">
                    <label for="date">Date (Required for Exams/CAs)</label>
                    <input type="date" name="date" id="date" class="form-control">
                </div>

                <button type="submit" class="btn-submit">Publish Schedule</button>
            </form>
        </div>
    </main>
</body>
</html>
