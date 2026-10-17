<?php
session_start();
require_once 'db.php';

// Auth Guard: Administrator (sees everyone) and Lecturer (sees only themselves).
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'], true)) {
    header('Location: dashboard.php');
    exit();
}

$role = $_SESSION['role'];
$userId = (int) $_SESSION['user_id'];
$DAY_ORDER = ['Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6];

/** Convert "HH:MM" (or "HH:MM:SS") into minutes since midnight. */
function toMinutes(string $time): int
{
    $parts = explode(':', $time);
    return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
}

try {
    $query = "
        SELECT COALESCE(s.lecturer_id, c.lecturer_id) AS lecturer_id, u.fullname, u.max_daily_periods,
               s.day_of_week, s.start_time, s.end_time, c.course_code, c.course_name
        FROM schedules s
        JOIN courses c ON s.course_id = c.id
        JOIN users u ON u.id = COALESCE(s.lecturer_id, c.lecturer_id)
        WHERE s.type = 'Class'
    ";
    if ($role !== 'Administrator') {
        $query .= ' AND COALESCE(s.lecturer_id, c.lecturer_id) = :uid';
    }
    $stmt = $pdo->prepare($query);
    $role !== 'Administrator' ? $stmt->execute([':uid' => $userId]) : $stmt->execute();
    $rows = $stmt->fetchAll();

    // Group by lecturer, then by day, so gaps can be measured per day.
    $byLecturer = [];
    foreach ($rows as $row) {
        $lid = $row['lecturer_id'];
        if (!isset($byLecturer[$lid])) {
            $byLecturer[$lid] = [
                'fullname' => $row['fullname'],
                'max_daily_periods' => $row['max_daily_periods'] ?: 4,
                'sessions' => [],
                'by_day' => [],
            ];
        }
        $byLecturer[$lid]['sessions'][] = $row;
        $byLecturer[$lid]['by_day'][$row['day_of_week']][] = $row;
    }

    $report = [];
    foreach ($byLecturer as $lid => $data) {
        $totalSessions = count($data['sessions']);
        $perDayCounts = [];
        $gapCount = 0;
        $gapMinutes = 0;

        foreach ($data['by_day'] as $day => $sessions) {
            $perDayCounts[$day] = count($sessions);
            usort($sessions, fn($a, $b) => toMinutes($a['start_time']) <=> toMinutes($b['start_time']));
            for ($i = 1; $i < count($sessions); $i++) {
                $gap = toMinutes($sessions[$i]['start_time']) - toMinutes($sessions[$i - 1]['end_time']);
                if ($gap > 0) {
                    $gapCount++;
                    $gapMinutes += $gap;
                }
            }
        }

        $busiestDay = '—';
        $busiestCount = 0;
        foreach ($perDayCounts as $day => $count) {
            if ($count > $busiestCount) {
                $busiestCount = $count;
                $busiestDay = $day;
            }
        }

        $weeklyCapacity = max(1, $data['max_daily_periods']) * max(1, count($data['by_day']) ?: 5);
        $loadPercent = min(100, (int) round(($totalSessions / $weeklyCapacity) * 100));

        $report[] = [
            'lecturer_id' => $lid,
            'fullname' => $data['fullname'],
            'total_sessions' => $totalSessions,
            'days_active' => count($data['by_day']),
            'busiest_day' => $busiestDay,
            'busiest_count' => $busiestCount,
            'gap_count' => $gapCount,
            'gap_minutes' => $gapMinutes,
            'load_percent' => $loadPercent,
        ];
    }
    usort($report, fn($a, $b) => $b['total_sessions'] <=> $a['total_sessions']);
} catch (Throwable $e) {
    $message = $e->getMessage();
    $report = $report ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReSchedule. - Lecturer Workload</title>
    <link rel="icon" type="image/jpeg" href="assets/reschedule-app-icon.jpeg">
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="dashboard-body">
<nav class="navbar">
    <div class="logo">ReSchedule<span>.</span></div>
    <div class="user-profile">
        <a href="dashboard.php" class="card-link">&larr; Dashboard</a>
        <a href="logout.php" class="btn-logout">Log Out</a>
    </div>
</nav>
<main class="dashboard-container">
    <header class="welcome-banner">
        <div>
            <span class="eyebrow"><?php echo $role === 'Administrator' ? 'Administrator workspace' : 'Lecturer workspace'; ?></span>
            <h1>Lecturer workload</h1>
            <p><?php echo $role === 'Administrator' ? 'Weekly teaching load, busiest day, and schedule gaps for every lecturer.' : 'Your weekly teaching load and schedule gaps.'; ?></p>
        </div>
    </header>

    <?php if (!empty($message)): ?>
        <div class="alert-box error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="dash-card">
        <div class="card-heading-row">
            <h3>Weekly load</h3>
            <button type="button" class="filter-export no-print" onclick="window.print()">Print report</button>
        </div>
        <?php if (empty($report)): ?>
            <p class="empty-state">No class schedules found yet.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Lecturer</th>
                            <th>Sessions / week</th>
                            <th>Days teaching</th>
                            <th>Busiest day</th>
                            <th>Weekly gaps</th>
                            <th>Load</th>
                            <?php if ($role === 'Administrator'): ?><th>View</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['fullname']); ?></td>
                                <td><?php echo (int) $r['total_sessions']; ?></td>
                                <td><?php echo (int) $r['days_active']; ?></td>
                                <td><?php echo htmlspecialchars($r['busiest_day']); ?><?php echo $r['busiest_count'] ? ' (' . (int) $r['busiest_count'] . ')' : ''; ?></td>
                                <td><?php echo (int) $r['gap_count']; ?><?php echo $r['gap_minutes'] ? ' &middot; ' . (int) $r['gap_minutes'] . ' min idle' : ''; ?></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <div style="flex:1; max-width:100px; height:8px; background:var(--line); border-radius:999px; overflow:hidden;">
                                            <div style="height:100%; width:<?php echo (int) $r['load_percent']; ?>%; background:<?php echo $r['load_percent'] >= 90 ? 'var(--danger)' : ($r['load_percent'] >= 60 ? 'var(--gold)' : 'var(--success)'); ?>;"></div>
                                        </div>
                                        <span style="font-size:0.8rem; color: var(--muted);"><?php echo (int) $r['load_percent']; ?>%</span>
                                    </div>
                                </td>
                                <?php if ($role === 'Administrator'): ?>
                                    <td><a href="timetable.php?lecturer=<?php echo urlencode($r['fullname']); ?>" class="card-link">Timetable</a></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
