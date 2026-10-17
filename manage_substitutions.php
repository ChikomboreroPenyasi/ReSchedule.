<?php
session_start();
require_once 'db.php';

// Auth Guard: Administrator and Lecturer access only.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'], true)) {
    header('Location: dashboard.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$message = '';
$messageType = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/** Suggest substitute lecturers for one affected class slot, ranked best first. */
function suggestSubstitutes(PDO $pdo, array $slot): array
{
    $candidates = $pdo->prepare("
        SELECT u.id, u.fullname,
               (SELECT COUNT(*) FROM schedules s2 JOIN courses c2 ON s2.course_id = c2.id WHERE COALESCE(s2.lecturer_id, c2.lecturer_id) = u.id AND s2.day_of_week = :day) AS day_load,
               (SELECT COUNT(*) FROM courses c3 WHERE c3.lecturer_id = u.id AND c3.id IN (SELECT course_id FROM course_program WHERE program_id = :program_id)) AS teaches_in_programme
        FROM users u
        WHERE u.role = 'Lecturer' AND u.id != :original_id
          AND NOT EXISTS (
              SELECT 1
              FROM schedules s3
              JOIN courses c4 ON s3.course_id = c4.id
              WHERE COALESCE(s3.lecturer_id, c4.lecturer_id) = u.id
                AND s3.day_of_week = :day2
                AND s3.start_time = :start_time
          )
          AND NOT EXISTS (
              SELECT 1
              FROM lecturer_unavailability lu
              WHERE lu.lecturer_id = u.id
                AND lu.unavailable_date = :abs_date
          )
        ORDER BY teaches_in_programme DESC, day_load ASC, u.fullname ASC
        LIMIT 4
    ");
    $candidates->execute([
        ':day' => $slot['day_of_week'],
        ':program_id' => $slot['program_id'],
        ':day2' => $slot['day_of_week'],
        ':start_time' => $slot['start_time'],
        ':abs_date' => $slot['absence_date'],
        ':original_id' => $slot['original_lecturer_id'],
    ]);
    return $candidates->fetchAll();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Your session has expired. Refresh and try again.');
        }
        $action = $_POST['action'] ?? '';

        if ($action === 'report_absence') {
            $lecturerId = $role === 'Administrator' && !empty($_POST['lecturer_id']) ? (int) $_POST['lecturer_id'] : $userId;
            $date = trim($_POST['unavailable_date'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            if ($lecturerId < 1 || $date === '') {
                throw new RuntimeException('Choose a lecturer and a date.');
            }
            $stmt = $pdo->prepare('INSERT INTO lecturer_unavailability (lecturer_id, unavailable_date, reason) VALUES (:lecturer_id, :date, :reason)');
            $stmt->execute([':lecturer_id' => $lecturerId, ':date' => $date, ':reason' => $reason ?: null]);

            // Auto-create a Pending substitution row for every class that lecturer
            // teaches on that weekday, so admins immediately see what needs covering.
            $dayName = date('l', strtotime($date));
            $affected = $pdo->prepare("
                SELECT s.id FROM schedules s
                JOIN courses c ON s.course_id = c.id
                WHERE COALESCE(s.lecturer_id, c.lecturer_id) = :lecturer_id AND s.day_of_week = :day AND s.type = 'Class'
            ");
            $affected->execute([':lecturer_id' => $lecturerId, ':day' => $dayName]);
            $insertSub = $pdo->prepare("INSERT INTO substitutions (schedule_id, absence_date, original_lecturer_id, status) VALUES (:schedule_id, :date, :lecturer_id, 'Pending') ON CONFLICT (schedule_id, absence_date) DO NOTHING");
            foreach ($affected->fetchAll(PDO::FETCH_COLUMN) as $scheduleId) {
                $insertSub->execute([':schedule_id' => $scheduleId, ':date' => $date, ':lecturer_id' => $lecturerId]);
            }

            $message = 'Absence reported for ' . htmlspecialchars($date) . '. Affected classes are listed below for coverage.';
            $messageType = 'success';
        }

        if ($action === 'assign_substitute' && $role === 'Administrator') {
            $subId = (int) ($_POST['substitution_id'] ?? 0);
            $substituteId = (int) ($_POST['substitute_lecturer_id'] ?? 0);
            if ($subId < 1 || $substituteId < 1) {
                throw new RuntimeException('Choose a substitute lecturer.');
            }
            $stmt = $pdo->prepare("UPDATE substitutions SET substitute_lecturer_id = :sub_id, status = 'Assigned', created_by = :by WHERE id = :id");
            $stmt->execute([':sub_id' => $substituteId, ':by' => $userId, ':id' => $subId]);
            $message = 'Substitute assigned.';
            $messageType = 'success';
        }

        if ($action === 'cancel_substitution' && $role === 'Administrator') {
            $subId = (int) ($_POST['substitution_id'] ?? 0);
            $pdo->prepare('DELETE FROM substitutions WHERE id = :id')->execute([':id' => $subId]);
            $message = 'Substitution entry removed.';
            $messageType = 'success';
        }
    }

    $lecturers = $pdo->query("SELECT id, fullname FROM users WHERE role = 'Lecturer' ORDER BY fullname ASC")->fetchAll();

    $unavailabilityQuery = $role === 'Administrator'
        ? 'SELECT lu.*, u.fullname FROM lecturer_unavailability lu JOIN users u ON lu.lecturer_id = u.id WHERE lu.unavailable_date >= CURRENT_DATE ORDER BY lu.unavailable_date ASC'
        : 'SELECT lu.*, u.fullname FROM lecturer_unavailability lu JOIN users u ON lu.lecturer_id = u.id WHERE lu.unavailable_date >= CURRENT_DATE AND lu.lecturer_id = :uid ORDER BY lu.unavailable_date ASC';
    $unavailabilityStmt = $pdo->prepare($unavailabilityQuery);
    if ($role !== 'Administrator') {
        $unavailabilityStmt->execute([':uid' => $userId]);
    } else {
        $unavailabilityStmt->execute();
    }
    $absences = $unavailabilityStmt->fetchAll();

    // Pending / assigned substitutions with full slot context.
    $subQuery = "
        SELECT sub.*, s.day_of_week, s.start_time, s.end_time, s.program_id,
               c.course_code, c.course_name, p.program_code,
               ou.fullname AS original_name, su.fullname AS substitute_name
        FROM substitutions sub
        JOIN schedules s ON sub.schedule_id = s.id
        JOIN courses c ON s.course_id = c.id
        LEFT JOIN programmes p ON s.program_id = p.id
        JOIN users ou ON sub.original_lecturer_id = ou.id
        LEFT JOIN users su ON sub.substitute_lecturer_id = su.id
        WHERE sub.absence_date >= CURRENT_DATE
    ";
    if ($role !== 'Administrator') {
        $subQuery .= ' AND sub.original_lecturer_id = :uid';
    }
    $subQuery .= ' ORDER BY sub.absence_date ASC, s.start_time ASC';
    $subStmt = $pdo->prepare($subQuery);
    if ($role !== 'Administrator') {
        $subStmt->execute([':uid' => $userId]);
    } else {
        $subStmt->execute();
    }
    $substitutions = $subStmt->fetchAll();

    // Attach ranked suggestions to each still-pending row (admin only, keeps the page fast for lecturers).
    if ($role === 'Administrator') {
        foreach ($substitutions as &$sub) {
            $sub['suggestions'] = $sub['status'] === 'Pending' ? suggestSubstitutes($pdo, [
                'day_of_week' => $sub['day_of_week'],
                'program_id' => $sub['program_id'],
                'start_time' => $sub['start_time'],
                'absence_date' => $sub['absence_date'],
                'original_lecturer_id' => $sub['original_lecturer_id'],
            ]) : [];
        }
        unset($sub);
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
    $absences = $absences ?? [];
    $substitutions = $substitutions ?? [];
    $lecturers = $lecturers ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReSchedule. - Substitute Coverage</title>
    <link rel="icon" type="image/jpeg" href="assets/reschedule-app-icon.jpeg">
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="dashboard-body">
<nav class="navbar">
    <div class="logo"><img src="assets/reschedule-logo.png" alt="ReSchedule. Campus Timetable System"></div>
    <div class="user-profile">
        <a href="dashboard.php" class="card-link">&larr; Dashboard</a>
        <a href="logout.php" class="btn-logout">Log Out</a>
    </div>
</nav>
<main class="dashboard-container">
    <header class="welcome-banner">
        <div>
            <span class="eyebrow"><?php echo $role === 'Administrator' ? 'Administrator workspace' : 'Lecturer workspace'; ?></span>
            <h1>Substitute coverage</h1>
            <p><?php echo $role === 'Administrator' ? 'Review reported absences and assign a substitute for each affected class.' : 'Report a day you are unavailable and track who covers your classes.'; ?></p>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="alert-box <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="event-management-grid">
        <article class="dash-card">
            <div class="card-heading-row">
                <h3>Report an absence</h3>
            </div>
            <form method="POST" class="event-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="report_absence">
                <?php if ($role === 'Administrator'): ?>
                    <div class="form-group">
                        <label for="lecturer_id">Lecturer</label>
                        <select id="lecturer_id" name="lecturer_id" required>
                            <option value="">Select lecturer...</option>
                            <?php foreach ($lecturers as $lec): ?>
                                <option value="<?php echo $lec['id']; ?>"><?php echo htmlspecialchars($lec['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="unavailable_date">Date unavailable</label>
                    <input type="date" id="unavailable_date" name="unavailable_date" required>
                </div>
                <div class="form-group">
                    <label for="reason">Reason (optional)</label>
                    <input type="text" id="reason" name="reason" maxlength="255" placeholder="e.g. Conference, sick leave">
                </div>
                <button type="submit" class="btn-submit">Report absence</button>
            </form>

            <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid var(--line);">
            <h4>Upcoming absences</h4>
            <?php if (empty($absences)): ?>
                <p class="empty-state">No upcoming absences reported.</p>
            <?php else: ?>
                <ul style="list-style: none; margin-top: 0.5rem;">
                    <?php foreach ($absences as $a): ?>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--line); font-size: 0.9rem;">
                            <strong><?php echo htmlspecialchars(date('D, j M Y', strtotime($a['unavailable_date']))); ?></strong>
                            <?php if ($role === 'Administrator'): ?> &middot; <?php echo htmlspecialchars($a['fullname']); ?><?php endif; ?>
                            <?php if (!empty($a['reason'])): ?><br><small style="color: var(--muted);"><?php echo htmlspecialchars($a['reason']); ?></small><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="dash-card">
            <div class="card-heading-row">
                <h3>Classes needing coverage</h3>
                <span class="section-hint"><?php echo count($substitutions); ?> item<?php echo count($substitutions) === 1 ? '' : 's'; ?></span>
            </div>
            <?php if (empty($substitutions)): ?>
                <p class="empty-state">No affected classes right now.</p>
            <?php else: ?>
                <div style="display:grid; gap:0.85rem; margin-top:1rem;">
                    <?php foreach ($substitutions as $sub): ?>
                        <div class="dash-card" style="box-shadow:none; border:1px solid var(--line); padding:1rem;">
                            <div class="card-heading-row">
                                <div>
                                    <strong><?php echo htmlspecialchars($sub['course_code'] . ' - ' . $sub['course_name']); ?></strong>
                                    <div style="font-size:0.85rem; color: var(--muted);">
                                        <?php echo htmlspecialchars(date('D, j M Y', strtotime($sub['absence_date']))); ?> &middot;
                                        <?php echo htmlspecialchars(substr($sub['start_time'], 0, 5) . '-' . substr($sub['end_time'], 0, 5)); ?> &middot;
                                        <?php echo htmlspecialchars($sub['program_code'] ?? ''); ?> &middot;
                                        Usually taught by <?php echo htmlspecialchars($sub['original_name']); ?>
                                    </div>
                                </div>
                                <span class="badge badge-<?php echo strtolower($sub['status']); ?>"><?php echo htmlspecialchars($sub['status']); ?></span>
                            </div>

                            <?php if ($sub['status'] === 'Assigned'): ?>
                                <p style="margin-top:0.6rem; font-size:0.9rem;">Covered by <strong><?php echo htmlspecialchars($sub['substitute_name']); ?></strong></p>
                                <?php if ($role === 'Administrator'): ?>
                                    <form method="POST" style="margin-top:0.5rem;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="cancel_substitution">
                                        <input type="hidden" name="substitution_id" value="<?php echo (int) $sub['id']; ?>">
                                        <button type="submit" class="filter-reset">Remove</button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ($role === 'Administrator'): ?>
                                <?php if (empty($sub['suggestions'])): ?>
                                    <p style="margin-top:0.6rem; font-size:0.85rem; color: var(--muted);">No free lecturers found for this slot.</p>
                                <?php else: ?>
                                    <form method="POST" style="margin-top:0.6rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="assign_substitute">
                                        <input type="hidden" name="substitution_id" value="<?php echo (int) $sub['id']; ?>">
                                        <select name="substitute_lecturer_id">
                                            <?php foreach ($sub['suggestions'] as $i => $s): ?>
                                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['fullname']); ?><?php echo $s['teaches_in_programme'] > 0 ? ' (teaches in this programme)' : ($s['day_load'] == 0 ? ' (free that day)' : ' (' . $s['day_load'] . ' class' . ($s['day_load'] == 1 ? '' : 'es') . ' that day)'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn-submit" style="padding:0.5rem 1rem;">Assign</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <p style="margin-top:0.6rem; font-size:0.85rem; color: var(--muted);">Waiting for an administrator to assign cover.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>
</body>
</html>
