<?php
session_start();

require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$username   = $_SESSION['username'] ?? 'User';
$fullname   = $_SESSION['fullname'] ?? $username;
$role       = $_SESSION['role'] ?? 'Student';
$program_id = $_SESSION['program_id'] ?? null;

$ca_schedules = [];
$class_schedules = [];
$my_requests = [];
$pending_requests = 0;
$programme_name = 'your programme';
$enrolled_year = 1;
$enrolled_semester = 1;
$today_classes = 0;
$message = '';
$message_type = 'danger';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Student Reschedule Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_reschedule') {
    $schedule_id    = intval($_POST['schedule_id'] ?? 0);
    $reason         = trim($_POST['reason'] ?? '');
    $preferred_time = trim($_POST['preferred_time'] ?? '');

    if ($schedule_id > 0 && !empty($reason)) {
        try {
            $stmt_req = $pdo->prepare("
                INSERT INTO reschedule_requests (student_id, schedule_id, reason, preferred_time, status)
                VALUES (:student_id, :schedule_id, :reason, :preferred_time, 'Pending')
            ");
            $stmt_req->execute([
                ':student_id'    => $user_id,
                ':schedule_id'   => $schedule_id,
                ':reason'        => $reason,
                ':preferred_time'=> $preferred_time
            ]);
            $message = "Your reschedule request has been submitted successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Database Error: " . $e->getMessage();
        }
    } else {
        $message = "Please provide a valid schedule and reason.";
    }
}

try {
    $profileStmt = $pdo->prepare('SELECT u.year_level, u.semester, p.program_name FROM users u LEFT JOIN programmes p ON u.program_id = p.id WHERE u.id = :user_id LIMIT 1');
    $profileStmt->execute([':user_id' => $user_id]);
    $profile = $profileStmt->fetch();
    if ($profile) {
        $programme_name = $profile['program_name'] ?: 'your programme';
        $enrolled_year = (int) ($profile['year_level'] ?: 1);
        $enrolled_semester = (int) ($profile['semester'] ?: 1);
    }
    // 1. Fetch CA & Exam Schedules
    if ($role === 'Student' && $program_id) {
        $stmt_ca = $pdo->prepare("
            SELECT s.*, COALESCE(c.course_code, s.course_code) AS course_code, 
                   COALESCE(c.course_name, s.course_name) AS course_name, r.room_name 
            FROM schedules s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN rooms r ON s.room_id = r.id
            WHERE s.type IN ('CA', 'Exam') AND s.program_id = :program_id AND s.year_level = :year_level AND s.semester = :semester
            ORDER BY s.date ASC, s.start_time ASC
        ");
        $stmt_ca->execute([':program_id' => $program_id, ':year_level' => $enrolled_year, ':semester' => $enrolled_semester]);
    } else {
        $stmt_ca = $pdo->query("
            SELECT s.*, COALESCE(c.course_code, s.course_code) AS course_code, 
                   COALESCE(c.course_name, s.course_name) AS course_name, r.room_name 
            FROM schedules s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN rooms r ON s.room_id = r.id
            WHERE s.type IN ('CA', 'Exam')
            ORDER BY s.date ASC, s.start_time ASC
        ");
    }
    $ca_schedules = $stmt_ca->fetchAll();

    // 2. Fetch Regular Class Schedules
    if ($role === 'Student' && $program_id) {
        $stmt_class = $pdo->prepare("
            SELECT s.*, COALESCE(c.course_code, s.course_code) AS course_code, 
                   COALESCE(c.course_name, s.course_name) AS course_name, r.room_name 
            FROM schedules s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN rooms r ON s.room_id = r.id
            WHERE s.type = 'Class' AND s.program_id = :program_id AND s.year_level = :year_level AND s.semester = :semester
            ORDER BY CASE s.day_of_week
                WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END,
                s.start_time ASC
        ");
        $stmt_class->execute([':program_id' => $program_id, ':year_level' => $enrolled_year, ':semester' => $enrolled_semester]);
    } else {
        $stmt_class = $pdo->query("
            SELECT s.*, COALESCE(c.course_code, s.course_code) AS course_code, 
                   COALESCE(c.course_name, s.course_name) AS course_name, r.room_name 
            FROM schedules s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN rooms r ON s.room_id = r.id
            WHERE s.type = 'Class'
            ORDER BY CASE s.day_of_week
                WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END,
                s.start_time ASC
        ");
    }
    $class_schedules = $stmt_class->fetchAll();

    // 3. Fetch Student's Reschedule Request History
    if ($role === 'Student') {
        $stmt_reqs = $pdo->prepare("
            SELECT r.*, COALESCE(c.course_code, s.course_code) AS course_code,
                   COALESCE(c.course_name, s.course_name) AS course_name,
                   s.type, s.day_of_week, s.date, s.start_time, s.end_time
            FROM reschedule_requests r
            JOIN schedules s ON r.schedule_id = s.id
            LEFT JOIN courses c ON s.course_id = c.id
            WHERE r.student_id = :student_id
            ORDER BY r.created_at DESC
        ");
        $stmt_reqs->execute([':student_id' => $user_id]);
        $my_requests = $stmt_reqs->fetchAll();
    }

    if (in_array($role, ['Administrator', 'Lecturer'], true)) {
        $pending_requests = (int) $pdo->query("SELECT COUNT(*) FROM reschedule_requests WHERE status = 'Pending'")->fetchColumn();
    }
    $today_classes = count(array_filter($class_schedules, fn ($schedule) => ($schedule['day_of_week'] ?? '') === date('l')));

} catch (PDOException $e) {
    $message = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule - Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .action-card { background: #ffffff; padding: 1.25rem; border-radius: 8px; border: 1px solid #cbd5e1; text-align: center; text-decoration: none; color: #1e293b; transition: all 0.2s ease; cursor: pointer; }
        .action-card:hover { border-color: #2563eb; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .action-card h4 { margin: 0 0 0.5rem 0; color: #2563eb; }
        .action-card p { margin: 0; font-size: 0.85rem; color: #64748b; }
        .schedule-table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem; }
        .schedule-table th, .schedule-table td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .schedule-table th { background: #f8fafc; font-weight: 600; color: #475569; }
        .badge { padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-ca { background: #fef3c7; color: #92400e; }
        .badge-exam { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-approved { background: #dcfce7; color: #15803d; }
        .badge-rejected { background: #fee2e2; color: #b91c1c; }
        .btn-req { background: #2563eb; color: #ffffff; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 0.8rem; }
        .btn-req:hover { background: #1d4ed8; }
        
        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: #ffffff; padding: 1.5rem; border-radius: 8px; width: 100%; max-width: 450px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .modal-content h3 { margin-top: 0; color: #1e293b; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 600; color: #475569; font-size: 0.85rem; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; }
        .btn-cancel { background: #94a3b8; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body class="dashboard-body">

    <nav class="navbar">
        <div class="logo">Reschedule<span>.</span></div>
        <div class="user-profile">
            <span><strong><?php echo htmlspecialchars($fullname); ?></strong> (<?php echo htmlspecialchars($role); ?>)</span>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </nav>

    <main class="dashboard-container">
        
        <header class="welcome-banner">
            <div>
                <span class="eyebrow">Reschedule portal</span>
                <h1>Welcome, <?php echo htmlspecialchars($fullname); ?>!</h1>
                <p><?php if ($role === 'Student'): ?>Your classes, assessments, and schedule requests for <?php echo htmlspecialchars($programme_name); ?>, Year <?php echo $enrolled_year; ?>, Semester <?php echo $enrolled_semester; ?> in one place.<?php else: ?>Keep your timetable, courses, resources, and requests moving from one place.<?php endif; ?></p>
            </div>
            <a href="timetable.php" class="banner-link">Open timetable &rarr;</a>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert" style="background:<?php echo ($message_type === 'success') ? '#dcfce7' : '#fee2e2'; ?>; color:<?php echo ($message_type === 'success') ? '#166534' : '#991b1b'; ?>; padding:1rem; border-radius:6px; margin-bottom:1.5rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <section class="summary-grid" aria-label="Dashboard summary">
            <div class="summary-card summary-card-primary"><span class="summary-label">My classes</span><strong><?php echo count($class_schedules); ?></strong><small>Weekly schedule</small></div>
            <div class="summary-card"><span class="summary-label">Today</span><strong><?php echo $today_classes; ?></strong><small><?php echo htmlspecialchars(date('l')); ?> classes</small></div>
            <div class="summary-card"><span class="summary-label">Assessments</span><strong><?php echo count($ca_schedules); ?></strong><small>Published slots</small></div>
            <div class="summary-card <?php echo $pending_requests > 0 ? 'summary-card-attention' : ''; ?>"><span class="summary-label"><?php echo $role === 'Student' ? 'My requests' : 'Pending requests'; ?></span><strong><?php echo $role === 'Student' ? count($my_requests) : $pending_requests; ?></strong><small><?php echo $role === 'Student' ? 'Reschedule history' : 'Needs review'; ?></small></div>
        </section>

        <!-- Quick Actions Grid -->
        <h3 style="margin-bottom: 1rem; color: #334155;">Management & Quick Actions</h3>
        <div class="action-grid">
            <a href="timetable.php" class="action-card">
                <h4>Master Timetable</h4>
                <p>View full weekly grid & room allocations</p>
            </a>

            <?php if ($role === 'Student'): ?>
                <div onclick="openModal()" class="action-card">
                    <h4>Request Reschedule</h4>
                    <p>Submit a request to change a class or exam slot</p>
                </div>
                <a href="#request-history" class="action-card">
                    <h4>Request History</h4>
                    <p>Track status of your submitted requests</p>
                </a>
            <?php endif; ?>

            <?php if (in_array($role, ['Administrator', 'Lecturer'])): ?>
                <a href="manage_courses.php" class="action-card">
                    <h4>Manage Courses</h4>
                    <p>Assign courses to academic programmes</p>
                </a>
                <a href="manage_resources.php" class="action-card">
                    <h4>Manage Resources</h4>
                    <p>Configure halls, labs, and capacities</p>
                </a>
                <a href="add_schedule.php" class="action-card">
                    <h4>Add Schedule Slot</h4>
                    <p>Create new class, CA, or Exam entry</p>
                </a>
                <a href="manage_reschedules.php" class="action-card">
                    <h4>Reschedule Requests</h4>
                    <p>Review and approve/reject student requests</p>
                </a>
            <?php endif; ?>

            <?php if ($role === 'Administrator'): ?>
                <a href="ai_import.php" class="action-card">
                    <h4>AI Timetable Import</h4>
                    <p>Upload and review a timetable document</p>
                </a>
                <a href="manage_users.php" class="action-card">
                    <h4>Manage Users</h4>
                    <p>Register & update student/lecturer accounts</p>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($role === 'Student'): ?>
        <!-- Student Request History Table -->
        <div id="request-history" class="dash-card" style="margin-bottom: 2rem;">
            <h3>My Reschedule Requests</h3>
            <?php if (empty($my_requests)): ?>
                <p style="color: #64748b; margin-top: 1rem;">You have not submitted any reschedule requests yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Type</th>
                                <th>Slot Details</th>
                                <th>Reason</th>
                                <th>Preferred Time</th>
                                <th>Submitted Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_requests as $req): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($req['course_code']); ?></strong> - <?php echo htmlspecialchars($req['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($req['type']); ?></td>
                                    <td><?php echo htmlspecialchars($req['day_of_week'] ?? $req['date']); ?> (<?php echo htmlspecialchars($req['start_time']); ?>)</td>
                                    <td><?php echo htmlspecialchars($req['reason']); ?></td>
                                    <td><?php echo htmlspecialchars(!empty($req['preferred_time']) ? $req['preferred_time'] : 'None'); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($req['created_at']))); ?></td>
                                    <td>
                                        <?php 
                                            $statusClass = 'badge-pending';
                                            if ($req['status'] === 'Approved') $statusClass = 'badge-approved';
                                            if ($req['status'] === 'Rejected') $statusClass = 'badge-rejected';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($req['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- CA & Exam Schedule Table -->
        <div class="dash-card" style="margin-bottom: 2rem;">
            <h3>CA & Exam Schedule</h3>
            <?php if (empty($ca_schedules)): ?>
                <p style="color: #64748b; margin-top: 1rem;">No scheduled CAs or Exams found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Venue</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ca_schedules as $s): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo ($s['type'] === 'Exam') ? 'badge-exam' : 'badge-ca'; ?>">
                                            <?php echo htmlspecialchars($s['type']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($s['course_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['date'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($s['start_time']); ?> - <?php echo htmlspecialchars($s['end_time']); ?></td>
                                    <td><?php echo htmlspecialchars($s['room_name'] ?? 'Unassigned'); ?></td>
                                    <td>
                                        <?php if (in_array($role, ['Administrator', 'Lecturer'])): ?>
                                            <a href="edit_schedule.php?id=<?php echo $s['id']; ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">Edit</a>
                                        <?php else: ?>
                                            <button class="btn-req" onclick="openModalFor(<?php echo $s['id']; ?>)">Request Reschedule</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Weekly Class Timetable Table -->
        <div class="dash-card">
            <h3>Weekly Class Timetable</h3>
            <?php if (empty($class_schedules)): ?>
                <p style="color: #64748b; margin-top: 1rem;">No active class slots found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Time</th>
                                <th>Venue</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($class_schedules as $s): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($s['day_of_week']); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($s['course_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['start_time']); ?> - <?php echo htmlspecialchars($s['end_time']); ?></td>
                                    <td><?php echo htmlspecialchars($s['room_name'] ?? 'Unassigned'); ?></td>
                                    <td>
                                        <?php if (in_array($role, ['Administrator', 'Lecturer'])): ?>
                                            <a href="edit_schedule.php?id=<?php echo $s['id']; ?>" style="color: #2563eb; text-decoration: none; font-weight: 600;">Edit</a>
                                        <?php else: ?>
                                            <button class="btn-req" onclick="openModalFor(<?php echo $s['id']; ?>)">Request Reschedule</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Student Reschedule Modal -->
    <div id="rescheduleModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Submit Reschedule Request</h3>
            <form method="POST">
                <input type="hidden" name="action" value="submit_reschedule">
                
                <div class="form-group">
                    <label for="schedule_id">Select Slot</label>
                    <select name="schedule_id" id="schedule_id" required>
                        <option value="">-- Choose Slot --</option>
                        <?php foreach (array_merge($ca_schedules, $class_schedules) as $sc): ?>
                            <option value="<?php echo $sc['id']; ?>">
                                <?php echo htmlspecialchars($sc['course_code'] . ' (' . ($sc['day_of_week'] ?? $sc['date']) . ' ' . $sc['start_time'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
