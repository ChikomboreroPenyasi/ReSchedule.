<?php
session_start();

// Import shared database setup
require_once 'db.php';

// Auth Guard: Direct unauthenticated users to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? 'User';
$role = $_SESSION['role'] ?? 'Student';

$message = "";
$statusClass = "";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule']) && in_array($role, ['Administrator', 'Lecturer'], true)) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Your session has expired. Refresh and try again.');
        }
        $scheduleId = intval($_POST['schedule_id'] ?? 0);
        $pdo->beginTransaction();
        $deleteRequests = $pdo->prepare('DELETE FROM reschedule_requests WHERE schedule_id = :schedule_id');
        $deleteRequests->execute([':schedule_id' => $scheduleId]);
        $deleteSchedule = $pdo->prepare('DELETE FROM schedules WHERE id = :schedule_id');
        $deleteSchedule->execute([':schedule_id' => $scheduleId]);
        $pdo->commit();
        $message = $deleteSchedule->rowCount() ? 'Schedule deleted successfully.' : 'Schedule was not found.';
        $statusClass = $deleteSchedule->rowCount() ? 'success' : 'error';
    }

    // Handle Schedule Slot Creation (Admins and Lecturers)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_schedule']) && in_array($role, ['Administrator', 'Lecturer'])) {
        $course_id = intval($_POST['course_id'] ?? 0);
        $room_name_input = trim($_POST['room_name'] ?? '');
        $day = trim($_POST['day'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');

        if ($course_id <= 0 || empty($room_name_input) || empty($day) || empty($start_time) || empty($end_time)) {
            $message = "All schedule fields, including room name, are required.";
            $statusClass = "error";
        } elseif ($start_time >= $end_time) {
            $message = "Start time must be strictly earlier than end time.";
            $statusClass = "error";
        } else {
            // Find or Create Room
            $roomStmt = $pdo->prepare("SELECT id FROM rooms WHERE room_name = :room_name");
            $roomStmt->execute([':room_name' => $room_name_input]);
            $existingRoom = $roomStmt->fetch();

            if ($existingRoom) {
                $room_id = $existingRoom['id'];
            } else {
                // Dynamically insert new room if it doesn't exist yet
                $createRoomStmt = $pdo->prepare("INSERT INTO rooms (room_name, capacity) VALUES (:room_name, 50)");
                $createRoomStmt->execute([':room_name' => $room_name_input]);
                $room_id = $pdo->lastInsertId();
            }

            // Conflict Detection 1: Room Double-booking Check
            $roomConflictStmt = $pdo->prepare("
                SELECT id FROM schedules 
                WHERE room_id = :room_id AND day_of_week = :day 
                AND ((start_time < :end_time AND end_time > :start_time))
            ");
            $roomConflictStmt->execute([
                ':room_id' => $room_id,
                ':day' => $day,
                ':start_time' => $start_time,
                ':end_time' => $end_time
            ]);

            if ($roomConflictStmt->fetch()) {
                $message = "Conflict Detected! Room '" . htmlspecialchars($room_name_input) . "' is already booked during this time slot.";
                $statusClass = "error";
            } else {
                // Conflict Detection 2: Lecturer Double-booking Check
                $lecturerConflictStmt = $pdo->prepare("
                    SELECT s.id FROM schedules s
                    JOIN courses c ON s.course_id = c.id
                    WHERE c.lecturer_id = (SELECT lecturer_id FROM courses WHERE id = :course_id)
                    AND s.day_of_week = :day
                    AND ((s.start_time < :end_time AND s.end_time > :start_time))
                ");
                $lecturerConflictStmt->execute([
                    ':course_id' => $course_id,
                    ':day' => $day,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time
                ]);

                if ($lecturerConflictStmt->fetch()) {
                    $message = "Conflict Detected! The assigned lecturer has another class scheduled during this time slot.";
                    $statusClass = "error";
                } else {
                    // No conflicts — Insert Schedule Entry
                    $insertStmt = $pdo->prepare("
                        INSERT INTO schedules (course_id, room_id, day_of_week, start_time, end_time) 
                        VALUES (:course_id, :room_id, :day, :start_time, :end_time)
                    ");
                    $insertStmt->execute([
                        ':course_id' => $course_id,
                        ':room_id' => $room_id,
                        ':day' => $day,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time
                    ]);
                    $message = "Schedule entry added successfully for room '" . htmlspecialchars($room_name_input) . "'!";
                    $statusClass = "success";
                }
            }
        }
    }

    // Fetch courses dropdown data
    $courses = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll();

    // Fetch Master Schedule
    $scheduleQuery = "
        SELECT s.id, s.day_of_week, 
               TIME_FORMAT(s.start_time, '%H:%i') AS start_time,
               TIME_FORMAT(s.end_time, '%H:%i') AS end_time,
               c.course_code, c.course_name, r.room_name, u.fullname AS lecturer_name
        FROM schedules s
        JOIN courses c ON s.course_id = c.id
        JOIN rooms r ON s.room_id = r.id
        LEFT JOIN users u ON c.lecturer_id = u.id
        ORDER BY 
            CASE s.day_of_week
                WHEN 'Monday' THEN 1
                WHEN 'Tuesday' THEN 2
                WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4
                WHEN 'Friday' THEN 5
                WHEN 'Saturday' THEN 6
                ELSE 7
            END, 
            s.start_time ASC
    ";
    $schedules = $pdo->query($scheduleQuery)->fetchAll();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = "Database Error: " . $e->getMessage();
    $statusClass = "error";
}

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule - Master Timetable</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-body">

    <nav class="navbar">
        <div class="logo">Reschedule<span>.</span></div>
        <div class="user-profile">
            <a href="dashboard.php" class="card-link">&larr; Back to Dashboard</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </nav>

    <main class="dashboard-container">
        
        <header class="welcome-banner">
            <h1>Master Timetable</h1>
            <p>View current class schedules and allocate lecture slots.</p>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert-box <?php echo $statusClass; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Schedule Entry Form (Admins / Lecturers Only) -->
        <?php if (in_array($role, ['Administrator', 'Lecturer'])): ?>
            <div class="dash-card" style="margin-bottom: 2rem;">
                <h3>Allocate Time Slot</h3>
                <form action="timetable.php" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                    <div class="form-group" style="margin: 0;">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">Select Course...</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="room_name">Lecture Room</label>
                        <input type="text" id="room_name" name="room_name" placeholder="e.g. Lab 1, LT 2" required>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="day">Day</label>
                        <select id="day" name="day" required>
                            <option value="">Select Day...</option>
                            <?php foreach ($daysOfWeek as $d): ?>
                                <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>

                    <button type="submit" name="add_schedule" class="btn-submit" style="height: 42px;">Add Slot</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Master Timetable Listing -->
        <div class="dash-card">
            <div class="card-heading-row"><h3>Weekly Timetable Grid</h3><span id="scheduleResultCount" class="section-hint"><?php echo count($schedules); ?> slots</span></div>
            
            <?php if (empty($schedules)): ?>
                <p style="color: #64748b; margin-top: 1rem;">No time slots scheduled yet.</p>
            <?php else: ?>
                <div class="timetable-filters"><input type="search" id="scheduleSearch" placeholder="Search course, room, or lecturer"><select id="dayFilter"><option value="">All days</option><?php foreach ($daysOfWeek as $d): ?><option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option><?php endforeach; ?></select><button type="button" id="exportSchedule" class="filter-export">Export CSV</button></div>
                <div style="overflow-x: auto; margin-top: 1rem;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="background-color: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
                                <th style="padding: 0.75rem;">Day</th>
                                <th style="padding: 0.75rem;">Time</th>
                                <th style="padding: 0.75rem;">Course Code</th>
                                <th style="padding: 0.75rem;">Course Title</th>
                                <th style="padding: 0.75rem;">Room</th>
                                <th style="padding: 0.75rem;">Lecturer</th>
                                <th style="padding: 0.75rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $s): ?>
                                <tr class="schedule-row" data-day="<?php echo htmlspecialchars($s['day_of_week']); ?>" data-search="<?php echo htmlspecialchars(strtolower($s['course_code'] . ' ' . $s['course_name'] . ' ' . $s['room_name'] . ' ' . ($s['lecturer_name'] ?? ''))); ?>" style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 0.75rem; font-weight: 600; color: #2563eb;"><?php echo htmlspecialchars($s['day_of_week']); ?></td>
                                    <td style="padding: 0.75rem; color: #334155;"><?php echo htmlspecialchars($s['start_time']); ?> - <?php echo htmlspecialchars($s['end_time']); ?></td>
                                    <td style="padding: 0.75rem; font-weight: 600;"><?php echo htmlspecialchars($s['course_code']); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($s['course_name']); ?></td>
                                    <td style="padding: 0.75rem;"><span style="background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 500;"><?php echo htmlspecialchars($s['room_name']); ?></span></td>
                                    <td style="padding: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($s['lecturer_name'] ?? 'Unassigned'); ?></td>
                                    <td style="padding: 0.75rem;">
                                        <a href="edit_schedule.php?id=<?php echo (int) $s['id']; ?>" class="schedule-edit-link">Edit</a>
                                        <form method="POST" class="inline-delete-form" onsubmit="return confirm('Delete this schedule and its requests?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="schedule_id" value="<?php echo (int) $s['id']; ?>">
                                            <button type="submit" name="delete_schedule" class="btn-delete">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        const search = document.getElementById('scheduleSearch'), day = document.getElementById('dayFilter'), rows = Array.from(document.querySelectorAll('.schedule-row')), count = document.getElementById('scheduleResultCount');
        function filterRows() { const q = (search?.value || '').toLowerCase(); const d = day?.value || ''; let visible = 0; rows.forEach(row => { const show = (!q || row.dataset.search.includes(q)) && (!d || row.dataset.day === d); row.hidden = !show; if (show) visible++; }); if (count) count.textContent = visible + (visible === 1 ? ' slot' : ' slots'); }
        search?.addEventListener('input', filterRows); day?.addEventListener('change', filterRows);
        document.getElementById('exportSchedule')?.addEventListener('click', () => { const csv = [['Day','Time','Course Code','Course Title','Room','Lecturer'], ...rows.filter(row => !row.hidden).map(row => Array.from(row.cells).slice(0,6).map(cell => '"' + cell.textContent.trim().replace(/"/g, '""') + '"'))].map(row => row.join(',')).join('\n'); const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([csv], {type:'text/csv'})); link.download = 'timetable.csv'; link.click(); });
    </script>

</body>
</html>
