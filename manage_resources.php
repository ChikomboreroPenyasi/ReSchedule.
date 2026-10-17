<?php
session_start();

// Import shared database setup
require_once 'db.php';

// Auth & Access Control Guard: Only Logged-in Admins or Lecturers
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'])) {
    header("Location: dashboard.php");
    exit();
}

$message = "";
$statusClass = "";

try {
    // Handle Adding a Room
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_room'])) {
        $room_name = trim($_POST['room_name'] ?? '');
        $capacity = intval($_POST['capacity'] ?? 0);

        if (empty($room_name) || $capacity <= 0) {
            $message = "Please provide a valid room name and positive capacity.";
            $statusClass = "error";
        } else {
            $stmt = $pdo->prepare("INSERT INTO rooms (room_name, capacity) VALUES (:room_name, :capacity)");
            $stmt->execute([':room_name' => $room_name, ':capacity' => $capacity]);
            $message = "Room '$room_name' added successfully.";
            $statusClass = "success";
        }
    }

    // Handle Adding a Course
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_course'])) {
        $course_code = trim($_POST['course_code'] ?? '');
        $course_name = trim($_POST['course_name'] ?? '');
        $lecturer_id = !empty($_POST['lecturer_id']) ? intval($_POST['lecturer_id']) : null;

        if (empty($course_code) || empty($course_name)) {
            $message = "Course code and course name are required.";
            $statusClass = "error";
        } else {
            $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, lecturer_id) VALUES (:code, :name, :lecturer_id)");
            $stmt->execute([
                ':code' => $course_code,
                ':name' => $course_name,
                ':lecturer_id' => $lecturer_id
            ]);
            $message = "Course '$course_code - $course_name' added successfully.";
            $statusClass = "success";
        }
    }

    // Fetch existing rooms
    $rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_name ASC")->fetchAll();

    // Fetch existing courses with assigned lecturer names
    $coursesQuery = "SELECT c.id, c.course_code, c.course_name, u.fullname AS lecturer_name 
                    FROM courses c 
                    LEFT JOIN users u ON c.lecturer_id = u.id 
                    ORDER BY c.course_code ASC";
    $courses = $pdo->query($coursesQuery)->fetchAll();

    // Fetch lecturers for dropdown selection
    $lecturers = $pdo->query("SELECT id, fullname FROM users WHERE role IN ('Lecturer', 'Administrator') ORDER BY fullname ASC")->fetchAll();

} catch (PDOException $e) {
    $message = "Database Error: " . $e->getMessage();
    $statusClass = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReSchedule. - Manage Resources</title>
    <link rel="icon" type="image/jpeg" href="assets/reschedule-app-icon.jpeg">
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="dashboard-body">

    <nav class="navbar">
        <div class="logo"><img src="assets/reschedule-logo.png" alt="ReSchedule. Campus Timetable System"></div>
        <div class="user-profile">
            <a href="dashboard.php" class="card-link">&larr; Back to Dashboard</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </nav>

    <main class="dashboard-container">
        
        <header class="welcome-banner">
            <h1>Resource Allocation & Management</h1>
            <p>Configure lecture halls and define academic course listings.</p>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert-box <?php echo $statusClass; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card-grid">
            
            <!-- Room Management Box -->
            <div class="dash-card">
                <h3>Add Lecture Room</h3>
                <form action="manage_resources.php" method="POST">
                    <div class="form-group">
                        <label for="room_name">Room Name / Code</label>
                        <input type="text" id="room_name" name="room_name" placeholder="e.g. Lab 1, Hall A" required>
                    </div>
                    <div class="form-group">
                        <label for="capacity">Seating Capacity</label>
                        <input type="number" id="capacity" name="capacity" placeholder="e.g. 60" min="1" required>
                    </div>
                    <button type="submit" name="add_room" class="btn-submit">Add Room</button>
                </form>

                <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid #e2e8f0;">

                <h4>Existing Rooms</h4>
                        <ul style="list-style: none; margin-top: 0.5rem;">
                    <?php if (empty($rooms)): ?>
                        <li style="color: #64748b; font-size: 0.875rem;">No rooms added yet.</li>
                    <?php endif; ?>
                    <?php foreach ($rooms as $room): ?>
                        <li style="padding: 0.4rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem;">
                            <strong><?php echo htmlspecialchars($room['room_name']); ?></strong> 
                            <span style="color: #64748b;">(Cap: <?php echo htmlspecialchars($room['capacity']); ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Course Management Box -->
            <div class="dash-card">
                <h3>Add Course</h3>
                <form action="manage_resources.php" method="POST">
                    <div class="form-group">
                        <label for="course_code">Course Code</label>
                        <input type="text" id="course_code" name="course_code" placeholder="e.g. ICT211" required>
                    </div>
                    <div class="form-group">
                        <label for="course_name">Course Title</label>
                        <input type="text" id="course_name" name="course_name" placeholder="e.g. Web Development" required>
                    </div>
                    <div class="form-group">
                        <label for="lecturer_id">Assign Lecturer (Optional)</label>
                        <select id="lecturer_id" name="lecturer_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($lecturers as $lec): ?>
                                <option value="<?php echo $lec['id']; ?>"><?php echo htmlspecialchars($lec['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_course" class="btn-submit">Add Course</button>
                </form>

                <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid #e2e8f0;">

                <h4>Existing Courses</h4>
                <ul style="list-style: none; margin-top: 0.5rem;">
                    <?php if (empty($courses)): ?>
                        <li style="color: #64748b; font-size: 0.875rem;">No courses added yet.</li>
                    <?php endif; ?>
                    <?php foreach ($courses as $course): ?>
                        <li style="padding: 0.4rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem;">
                            <strong><?php echo htmlspecialchars($course['course_code']); ?></strong> - <?php echo htmlspecialchars($course['course_name']); ?>
                            <br>
                            <small style="color: #64748b;">Lecturer: <?php echo htmlspecialchars($course['lecturer_name'] ?? 'Unassigned'); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div>

    </main>

</body>
</html>
