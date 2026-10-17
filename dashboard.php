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
$next_class = null;
$next_assessment = null;
$notifications = [];
$calendarDays = [];
$message = '';
$message_type = 'danger';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Student Reschedule Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_reschedule') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = 'Your session has expired. Refresh and try again.';
        $message_type = 'danger';
    } else {
    $schedule_id    = intval($_POST['schedule_id'] ?? 0);
    $reason         = trim($_POST['reason'] ?? '');
    $preferredTimeInput = trim((string) ($_POST['preferred_time'] ?? ''));
    $preferred_time = $preferredTimeInput !== '' ? $preferredTimeInput : null;

    if ($schedule_id > 0 && !empty($reason)) {
        try {
            $existingReq = $pdo->prepare("SELECT id FROM reschedule_requests WHERE student_id = :student_id AND schedule_id = :schedule_id AND status = 'Pending' LIMIT 1");
            $existingReq->execute([
                ':student_id' => $user_id,
                ':schedule_id' => $schedule_id,
            ]);

            if ($existingReq->fetch()) {
                $message = "You already have a pending reschedule request for this schedule.";
                $message_type = "warning";
            } else {
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
            }
        } catch (PDOException $e) {
            $message = "Database Error: " . $e->getMessage();
        }
    } else {
        $message = "Please provide a valid schedule and reason.";
    }
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
            SELECT s.*, COALESCE(c.course_code, s.course_code, 'Course') AS course_code,
                   COALESCE(c.course_name, s.course_name, 'Course details unavailable') AS course_name, r.room_name
            FROM schedules s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN rooms r ON s.room_id = r.id
                        WHERE s.type IN ('CA', 'Exam')
                            AND ((s.program_id = :program_id AND s.year_level = :year_level AND s.semester = :semester)
                                     OR EXISTS (SELECT 1 FROM course_program cp WHERE cp.course_id = s.course_id AND cp.program_id = :mapped_program_id AND cp.year_level = :mapped_year_level AND cp.semester = :mapped_semester))
            ORDER BY s.date ASC, s.start_time ASC
        ");
        $stmt_ca->execute([':program_id' => $program_id, ':year_level' => $enrolled_year, ':semester' => $enrolled_semester, ':mapped_program_id' => $program_id, ':mapped_year_level' => $enrolled_year, ':mapped_semester' => $enrolled_semester]);
    } else {
        $stmt_ca = $pdo->query("
            SELECT s.*, COALESCE(c.course_code, s.course_code, 'Course') AS course_code,
                   COALESCE(c.course_name, s.course_name, 'Course details unavailable') AS course_name, r.room_name
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
            SELECT s.*, COALESCE(c.course_code, s.course_code, 'Course') AS course_code,
                   COALESCE(c.course_name, s.course_name, 'Course details unavailable') AS course_name, r.room_name
            FROM schedules s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN rooms r ON s.room_id = r.id
                        WHERE s.type = 'Class'
                            AND ((s.program_id = :program_id AND s.year_level = :year_level AND s.semester = :semester)
                                     OR EXISTS (SELECT 1 FROM course_program cp WHERE cp.course_id = s.course_id AND cp.program_id = :mapped_program_id AND cp.year_level = :mapped_year_level AND cp.semester = :mapped_semester))
            ORDER BY CASE s.day_of_week
                WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END,
                s.start_time ASC
        ");
        $stmt_class->execute([':program_id' => $program_id, ':year_level' => $enrolled_year, ':semester' => $enrolled_semester, ':mapped_program_id' => $program_id, ':mapped_year_level' => $enrolled_year, ':mapped_semester' => $enrolled_semester]);
    } else {
        $stmt_class = $pdo->query("
            SELECT s.*, COALESCE(c.course_code, s.course_code, 'Course') AS course_code,
                   COALESCE(c.course_name, s.course_name, 'Course details unavailable') AS course_name, r.room_name
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

    $currentTimestamp = time();
    foreach ($class_schedules as $classSchedule) {
        $isToday = ($classSchedule['day_of_week'] ?? '') === date('l');
        $classTimestamp = strtotime(($isToday ? date('Y-m-d') : '1970-01-01') . ' ' . ($classSchedule['start_time'] ?? '00:00:00'));
        if ($isToday && $classTimestamp >= $currentTimestamp) {
            $next_class = $classSchedule;
            break;
        }
    }
    if (!$next_class && !empty($class_schedules)) {
        $next_class = $class_schedules[0];
    }

    foreach ($ca_schedules as $assessment) {
        $assessmentDate = $assessment['date'] ?? null;
        if (!$assessmentDate || $assessmentDate >= date('Y-m-d')) {
            $next_assessment = $assessment;
            break;
        }
    }

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

    if ($role === 'Student') {
        if ($next_class) {
            $notifications[] = [
                'type' => 'info',
                'text' => 'Next class: ' . htmlspecialchars($next_class['course_code'] ?? 'Course') . ' on ' . htmlspecialchars($next_class['day_of_week'] ?? 'Today') . ' at ' . htmlspecialchars($next_class['start_time'] ?? ''),
            ];
        }
        if ($next_assessment) {
            $notifications[] = [
                'type' => 'warning',
                'text' => 'Assessment reminder: ' . htmlspecialchars($next_assessment['course_code'] ?? 'Assessment') . ' on ' . htmlspecialchars($next_assessment['date'] ?? '') . ' at ' . htmlspecialchars($next_assessment['start_time'] ?? ''),
            ];
        }
        if ($today_classes > 0) {
            $notifications[] = [
                'type' => 'info',
                'text' => 'You have ' . $today_classes . ' class' . ($today_classes === 1 ? '' : 'es') . ' today.',
            ];
        }
        if (count($my_requests) > 0) {
            $notifications[] = [
                'type' => 'warning',
                'text' => 'You have ' . count($my_requests) . ' reschedule request' . (count($my_requests) === 1 ? '' : 's') . ' in progress.',
            ];
        } else {
            $notifications[] = [
                'type' => 'success',
                'text' => 'No active reschedule requests. You are up to date.',
            ];
        }
    } else {
        if ($pending_requests > 0) {
            $notifications[] = [
                'type' => 'warning',
                'text' => $pending_requests . ' pending reschedule request' . ($pending_requests === 1 ? '' : 's') . ' need review.',
            ];
        } else {
            $notifications[] = [
                'type' => 'success',
                'text' => 'No pending reschedule requests. Everything is clear.',
            ];
        }
        if ($next_assessment) {
            $notifications[] = [
                'type' => 'info',
                'text' => 'Upcoming assessment: ' . htmlspecialchars($next_assessment['course_code'] ?? 'Assessment') . ' on ' . htmlspecialchars($next_assessment['date'] ?? ''),
            ];
        }
    }

    $calendarMonth = (int) date('n');
    $calendarYear = (int) date('Y');
    $calendarStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $calendarYear, $calendarMonth));
    $calendarDaysInMonth = (int) $calendarStart->format('t');
    $calendarEventsByDate = [];

    foreach ($class_schedules as $schedule) {
        if (empty($schedule['day_of_week'])) {
            continue;
        }
        $dayName = $schedule['day_of_week'];
        $dayNumber = 1;
        while ($dayNumber <= $calendarDaysInMonth) {
            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $calendarYear, $calendarMonth, $dayNumber));
            if ($date->format('l') === $dayName) {
                $dateKey = $date->format('Y-m-d');
                $calendarEventsByDate[$dateKey][] = [
                    'type' => 'Class',
                    'label' => trim((string) ($schedule['course_code'] ?? 'Class')),
                    'time' => trim((string) ($schedule['start_time'] ?? '')) . ' - ' . trim((string) ($schedule['end_time'] ?? ''))
                ];
            }
            $dayNumber++;
        }
    }

    foreach ($ca_schedules as $assessment) {
        $assessmentDate = trim((string) ($assessment['date'] ?? ''));
        if ($assessmentDate === '') {
            continue;
        }
        $eventDate = new DateTimeImmutable($assessmentDate);
        if ($eventDate->format('n') === $calendarMonth && $eventDate->format('Y') === (string) $calendarYear) {
            $dateKey = $eventDate->format('Y-m-d');
            $calendarEventsByDate[$dateKey][] = [
                'type' => strtoupper((string) ($assessment['type'] ?? 'Assessment')),
                'label' => trim((string) ($assessment['course_code'] ?? 'Assessment')),
                'time' => trim((string) ($assessment['start_time'] ?? '')) . ' - ' . trim((string) ($assessment['end_time'] ?? ''))
            ];
        }
    }

    $calendarDays = [];
    for ($day = 1; $day <= $calendarDaysInMonth; $day++) {
        $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $calendarYear, $calendarMonth, $day));
        $dateKey = $date->format('Y-m-d');
        $calendarDays[] = [
            'day' => $day,
            'date' => $dateKey,
            'events' => $calendarEventsByDate[$dateKey] ?? []
        ];
    }

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
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="dashboard-body">

    <nav class="navbar">
        <div class="logo"><img src="assets/reschedule-logo.png" alt="ReSchedule. Campus Timetable System"></div>
        <div class="user-profile">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-controls="dashboardSidebar" aria-expanded="false">☰ Menu</button>
            <span><strong><?php echo htmlspecialchars($fullname); ?></strong> (<?php echo htmlspecialchars($role); ?>)</span>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </nav>

    <div class="dashboard-shell">
        <aside class="dashboard-sidebar" id="dashboardSidebar">
            <div class="sidebar-heading">
                <strong><?php echo htmlspecialchars($fullname); ?></strong>
                <span><?php echo htmlspecialchars($role); ?> workspace</span>
            </div>
            <span class="sidebar-label">Workspace</span>
            <nav class="sidebar-nav" aria-label="Dashboard navigation">
                <a href="dashboard.php" class="is-active"><span class="sidebar-icon">⌂</span>Overview</a>
                <a href="timetable.php"><span class="sidebar-icon">▦</span>Master timetable</a>
                <a href="#notifications"><span class="sidebar-icon">◎</span>Notifications <span class="sidebar-badge"><?php echo count($notifications); ?></span></a>
                <?php if ($role === 'Student'): ?>
                    <a href="#request-history"><span class="sidebar-icon">↗</span>My requests</a>
                <?php endif; ?>
            </nav>

            <?php if ($role === 'Student'): ?>
                <div class="sidebar-request-panel" id="request-history">
                    <div class="sidebar-request-header">
                        <strong>My Reschedule Requests</strong>
                        <span class="sidebar-request-count"><?php echo count($my_requests); ?></span>
                    </div>

                    <?php if (empty($my_requests)): ?>
                        <p class="sidebar-request-empty">You have not submitted any reschedule requests yet.</p>
                    <?php else: ?>
                        <ul class="sidebar-request-list">
                            <?php foreach (array_slice($my_requests, 0, 3) as $req): ?>
                                <li>
                                    <span class="request-status <?php echo strtolower($req['status']); ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                    <strong><?php echo htmlspecialchars($req['course_code'] ?? 'Course'); ?></strong>
                                    <small><?php echo htmlspecialchars($req['day_of_week'] ?? $req['date']); ?> · <?php echo htmlspecialchars($req['start_time']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (in_array($role, ['Administrator', 'Lecturer'], true)): ?>
                <span class="sidebar-label">Management</span>
                <nav class="sidebar-nav" aria-label="Management navigation">
                    <a href="add_schedule.php"><span class="sidebar-icon">＋</span>Add schedule</a>
                    <a href="manage_courses.php"><span class="sidebar-icon">◇</span>Courses</a>
                    <a href="manage_resources.php"><span class="sidebar-icon">▣</span>Resources</a>
                    <a href="manage_events.php"><span class="sidebar-icon">◷</span>Events</a>
                    <a href="manage_reschedules.php"><span class="sidebar-icon">↻</span>Requests</a>
                    <a href="manage_substitutions.php"><span class="sidebar-icon">⇄</span>Substitute coverage</a>
                    <?php if ($role === 'Administrator'): ?>
                        <a href="generate_timetable.php"><span class="sidebar-icon">✧</span>Generate timetable</a>
                        <a href="manage_users.php"><span class="sidebar-icon">◎</span>Users</a>
                        <a href="ai_import.php"><span class="sidebar-icon">✦</span>AI import</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

            <div class="sidebar-note">
                <strong><?php echo htmlspecialchars(date('l, j F')); ?></strong>
                <p>Keep schedules current and check requests before the next academic session.</p>
            </div>
        </aside>

        <main class="dashboard-main">
        <div class="dashboard-container">

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

        <section id="notifications" class="notification-panel" aria-label="Notifications">
            <div class="card-heading-row">
                <h3>Notifications</h3>
                <span class="section-hint"><?php echo count($notifications); ?> item<?php echo count($notifications) === 1 ? '' : 's'; ?></span>
            </div>
            <ul class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <li class="notification-item notification-<?php echo htmlspecialchars($notification['type']); ?>">
                        <span class="notification-dot"></span>
                        <span><?php echo $notification['text']; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if ($role === 'Student'): ?>
        <section class="student-overview-grid" aria-label="Student overview">
            <article class="profile-summary-card">
                <div class="card-heading-row">
                    <h3>Student profile</h3>
                    <span class="section-hint">Academic summary</span>
                </div>
                <div class="profile-summary-top">
                    <div class="profile-avatar"><?php echo htmlspecialchars(strtoupper(substr($fullname, 0, 1))); ?></div>
                    <div>
                        <h4><?php echo htmlspecialchars($fullname); ?></h4>
                        <p><?php echo htmlspecialchars($programme_name); ?></p>
                    </div>
                </div>
                <div class="profile-meta-grid">
                    <div><span>Year</span><strong>Year <?php echo $enrolled_year; ?></strong></div>
                    <div><span>Semester</span><strong>Semester <?php echo $enrolled_semester; ?></strong></div>
                    <div><span>Classes</span><strong><?php echo count($class_schedules); ?></strong></div>
                    <div><span>Requests</span><strong><?php echo count($my_requests); ?></strong></div>
                </div>
            </article>

            <article class="calendar-card">
                <div class="card-heading-row">
                    <h3>Academic calendar</h3>
                    <span class="section-hint"><?php echo htmlspecialchars(date('F Y')); ?></span>
                </div>
                <div class="calendar-weekdays">
                    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $abbr): ?>
                        <span><?php echo $abbr; ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="calendar-grid">
                    <?php foreach ($calendarDays as $dayInfo): ?>
                        <div class="calendar-day <?php echo !empty($dayInfo['events']) ? 'has-events' : ''; ?>">
                            <span class="calendar-day-number"><?php echo $dayInfo['day']; ?></span>
                            <?php foreach (array_slice($dayInfo['events'], 0, 2) as $event): ?>
                                <span class="calendar-chip <?php echo strtolower($event['type']) === 'exam' ? 'chip-exam' : 'chip-class'; ?>"><?php echo htmlspecialchars($event['label']); ?></span>
                            <?php endforeach; ?>
                            <?php if (count($dayInfo['events']) > 2): ?>
                                <span class="calendar-more">+<?php echo count($dayInfo['events']) - 2; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>
        <?php endif; ?>

        <section class="summary-grid" aria-label="Dashboard summary">
            <div class="summary-card summary-card-primary"><span class="summary-label">My classes</span><strong><?php echo count($class_schedules); ?></strong><small>Weekly schedule</small></div>
            <div class="summary-card"><span class="summary-label">Today</span><strong><?php echo $today_classes; ?></strong><small><?php echo htmlspecialchars(date('l')); ?> classes</small></div>
            <div class="summary-card"><span class="summary-label">Assessments</span><strong><?php echo count($ca_schedules); ?></strong><small>Published slots</small></div>
            <div class="summary-card <?php echo $pending_requests > 0 ? 'summary-card-attention' : ''; ?>"><span class="summary-label"><?php echo $role === 'Student' ? 'My requests' : 'Pending requests'; ?></span><strong><?php echo $role === 'Student' ? count($my_requests) : $pending_requests; ?></strong><small><?php echo $role === 'Student' ? 'Reschedule history' : 'Needs review'; ?></small></div>
        </section>

        <?php if ($role !== 'Administrator'): ?>
        <section class="dashboard-updates" aria-label="Dashboard updates">
            <div class="update-panel">
                <h3>What’s happening</h3>
                <div class="update-list">
                    <div class="update-item"><span class="update-dot"></span><div><strong><?php echo count($class_schedules); ?> class slots available</strong><span>Your current timetable is ready to review.</span></div></div>
                    <div class="update-item"><span class="update-dot"></span><div><strong><?php echo count($ca_schedules); ?> assessment slots published</strong><span>Check dates, times, and venues before attending.</span></div></div>
                    <div class="update-item"><span class="update-dot"></span><div><strong><?php echo $role === 'Student' ? count($my_requests) : $pending_requests; ?> request<?php echo (($role === 'Student' ? count($my_requests) : $pending_requests) === 1) ? '' : 's'; ?> in your view</strong><span><?php echo $role === 'Student' ? 'Track your submitted reschedule requests.' : 'Review outstanding student requests.'; ?></span></div></div>
                </div>
            </div>
            <div class="next-step-panel">
                <h3>Next best action</h3>
                <p><?php if ($role === 'Student'): ?>Review your timetable and submit a request if a slot needs attention.<?php elseif ($pending_requests > 0): ?>There are requests waiting for an academic decision.<?php else: ?>Keep your timetable and academic resources up to date.<?php endif; ?></p>
                <a href="<?php echo ($role === 'Student') ? '#request-history' : (($pending_requests > 0) ? 'manage_reschedules.php' : 'timetable.php'); ?>"><?php echo ($role === 'Student') ? 'View my requests' : (($pending_requests > 0) ? 'Review requests' : 'Open timetable'); ?> &rarr;</a>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($role === 'Student'): ?>
        <section class="student-focus-grid" aria-label="Student academic focus">
            <article class="student-focus-card student-focus-primary">
                <span class="focus-kicker">Next class</span>
                <?php if ($next_class): ?>
                    <strong><?php echo htmlspecialchars($next_class['course_code']); ?></strong>
                    <h3><?php echo htmlspecialchars($next_class['course_name']); ?></h3>
                    <p><?php echo htmlspecialchars($next_class['day_of_week']); ?> - <?php echo htmlspecialchars($next_class['start_time']); ?> to <?php echo htmlspecialchars($next_class['end_time']); ?></p>
                    <span class="focus-location"><?php echo htmlspecialchars($next_class['room_name'] ?? 'Venue to be confirmed'); ?></span>
                <?php else: ?>
                    <h3>No class scheduled</h3>
                    <p>Your current timetable does not have an upcoming class slot.</p>
                <?php endif; ?>
            </article>
            <article class="student-focus-card">
                <span class="focus-kicker">Next assessment</span>
                <?php if ($next_assessment): ?>
                    <span class="badge <?php echo ($next_assessment['type'] === 'Exam') ? 'badge-exam' : 'badge-ca'; ?>"><?php echo htmlspecialchars($next_assessment['type']); ?></span>
                    <h3><?php echo htmlspecialchars($next_assessment['course_code']); ?> - <?php echo htmlspecialchars($next_assessment['course_name']); ?></h3>
                    <p><?php echo htmlspecialchars($next_assessment['date']); ?> - <?php echo htmlspecialchars($next_assessment['start_time']); ?> to <?php echo htmlspecialchars($next_assessment['end_time']); ?></p>
                    <span class="focus-location"><?php echo htmlspecialchars($next_assessment['room_name'] ?? 'Venue to be confirmed'); ?></span>
                <?php else: ?>
                    <h3>No upcoming assessment</h3>
                    <p>New assessment dates will appear here when published.</p>
                <?php endif; ?>
            </article>
        </section>
        <?php endif; ?>

        <!-- Quick Actions Grid -->
        <h3 class="dashboard-section-title"><?php echo $role === 'Student' ? 'Student actions' : 'Management & Quick Actions'; ?></h3>
        <div class="action-grid<?php echo $role === 'Administrator' ? ' action-grid-compact' : ''; ?>">
            <a href="timetable.php" class="action-card">
                <?php if ($role === 'Administrator'): ?><span class="action-card-icon" aria-hidden="true">▦</span><?php endif; ?>
                <div class="action-card-content"><h4>Master Timetable</h4>
                <p>View full weekly grid & room allocations</p></div>
            </a>

            <?php if ($role === 'Student'): ?>
                <button type="button" onclick="openModal()" class="action-card action-card-button">
                    <h4>Request Reschedule</h4>
                    <p>Submit a request to change a class or exam slot</p>
                </button>
                <a href="#request-history" class="action-card">
                    <h4>Request History</h4>
                    <p>Track status of your submitted requests</p>
                </a>
            <?php endif; ?>

            <?php if (in_array($role, ['Administrator', 'Lecturer'])): ?>
                <a href="manage_courses.php" class="action-card">
                    <span class="action-card-icon" aria-hidden="true">◇</span>
                    <div class="action-card-content"><h4>Manage Courses</h4>
                    <p>Assign courses to academic programmes</p></div>
                </a>
                <a href="manage_resources.php" class="action-card">
                    <span class="action-card-icon" aria-hidden="true">▣</span>
                    <div class="action-card-content"><h4>Manage Resources</h4>
                    <p>Configure halls, labs, and capacities</p></div>
                </a>
                <a href="add_schedule.php" class="action-card">
                    <span class="action-card-icon" aria-hidden="true">＋</span>
                    <div class="action-card-content"><h4>Add Schedule Slot</h4>
                    <p>Create new class, CA, or Exam entry</p></div>
                </a>
                <a href="manage_reschedules.php" class="action-card">
                    <span class="action-card-icon" aria-hidden="true">↻</span>
                    <div class="action-card-content"><h4>Reschedule Requests</h4>
                    <p>Review and approve/reject student requests</p></div>
                </a>
            <?php endif; ?>

            <?php if ($role === 'Administrator'): ?>
                <a href="generate_timetable.php" class="action-card">
                    <span class="action-card-icon" aria-hidden="true">✧</span>
                    <div class="action-card-content"><h4>Generate Timetable</h4>
                    <p>Build and review a conflict-checked weekly draft</p></div>
                </a>
                <a href="ai_import.php" class="action-card">
                    <span class="action-card-icon" aria-hidden="true">✦</span>
                    <div class="action-card-content"><h4>AI Timetable Import</h4>
                    <p>Upload and review a timetable document</p></div>
                </a>
                <a href="manage_users.php" class="action-card">
                    <span class="action-card-icon" aria-hidden="true">◎</span>
                    <div class="action-card-content"><h4>Manage Users</h4>
                    <p>Register & update student/lecturer accounts</p></div>
                </a>
            <?php endif; ?>
            <?php if (in_array($role, ['Administrator', 'Lecturer'], true)): ?>
                <a href="manage_substitutions.php" class="action-card">
                    <span class="action-card-icon" aria-hidden="true">⇄</span>
                    <div class="action-card-content"><h4>Substitute Coverage</h4>
                    <p>Report lecturer absences and arrange class cover</p></div>
                </a>
            <?php endif; ?>
        </div>

        <!-- CA & Exam Schedule Table -->
        <details class="dash-card schedule-disclosure">
            <summary>
                <span class="schedule-summary-title"><h3>CA & Exam Schedule</h3><span class="section-hint"><?php echo count($ca_schedules); ?> published</span></span>
                <span class="schedule-toggle"><span class="when-closed">Show schedule</span><span class="when-open">Hide schedule</span></span>
            </summary>
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
        </details>

        <!-- Weekly Class Timetable Table -->
        <details class="dash-card schedule-disclosure">
            <summary>
                <span class="schedule-summary-title"><h3>Weekly Class Timetable</h3><span class="section-hint"><?php echo count($class_schedules); ?> class slots</span></span>
                <span class="schedule-toggle"><span class="when-closed">Show timetable</span><span class="when-open">Hide timetable</span></span>
            </summary>
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
        </details>

        </div>
        </main>
    </div>

    <?php if ($role === 'Student'): ?>
    <div id="rescheduleModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Submit Reschedule Request</h3>
            <form method="POST">
                <input type="hidden" name="action" value="submit_reschedule">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="schedule_id">Select Slot</label>
                    <select name="schedule_id" id="schedule_id" required>
                        <option value="">-- Choose Slot --</option>
                        <?php foreach (array_merge($ca_schedules, $class_schedules) as $sc): ?>
                            <option value="<?php echo $sc['id']; ?>">
                                <?php echo htmlspecialchars($sc['course_code'] . ' (' . ($sc['day_of_week'] ?? $sc['date']) . ' ' . $sc['start_time'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for request</label>
                    <textarea name="reason" id="reason" rows="4" placeholder="Tell us why this class or exam needs rescheduling" required></textarea>
                </div>

                <div class="form-group">
                    <label for="preferred_time">Preferred time</label>
                    <input type="text" name="preferred_time" id="preferred_time" placeholder="e.g. Tuesday 10:00 - 12:00">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const sidebar = document.getElementById('dashboardSidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        sidebarToggle?.addEventListener('click', function () {
            const open = sidebar.classList.toggle('is-open');
            sidebarToggle.setAttribute('aria-expanded', String(open));
        });

        sidebar?.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                sidebar.classList.remove('is-open');
                sidebarToggle?.setAttribute('aria-expanded', 'false');
            });
        });

        const rescheduleModal = document.getElementById('rescheduleModal');
        const scheduleSelect = document.getElementById('schedule_id');

        function openModal() {
            if (rescheduleModal) {
                rescheduleModal.style.display = 'flex';
            }
        }

        function openModalFor(scheduleId) {
            if (!rescheduleModal || !scheduleSelect) return;
            if (scheduleId) {
                scheduleSelect.value = String(scheduleId);
            }
            rescheduleModal.style.display = 'flex';
        }

        function closeModal() {
            if (!rescheduleModal || !scheduleSelect) return;
            rescheduleModal.style.display = 'none';
            scheduleSelect.value = '';
        }

        if (rescheduleModal) {
            rescheduleModal.addEventListener('click', function (event) {
                if (event.target === rescheduleModal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && rescheduleModal.style.display === 'flex') {
                    closeModal();
                }
            });
        }
    </script>
