<?php
session_start();
require_once 'db.php';

// Auth Guard: Administrator only — this can bulk-write the schedules table.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Administrator') {
    header('Location: dashboard.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';
$preview = $_SESSION['generated_preview'] ?? null;
$ALL_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/**
 * Build weekly "cards" — one per required session — from the courses mapped
 * to a programme/year/semester, then greedily place them onto a day x period
 * grid, avoiding lecturer, room, and class clashes. Runs several randomised
 * attempts and keeps the best (fewest unplaced, lowest penalty) result.
 */
function generateTimetable(PDO $pdo, int $programId, int $yearLevel, int $semester, array $days, int $periodsPerDay, int $startHour, int $periodMinutes, int $attempts, bool $clearExisting): array
{
    $courseRows = $pdo->prepare("
        SELECT cp.course_id, cp.periods_per_week, c.course_code, c.course_name, c.lecturer_id, u.fullname AS lecturer_name
        FROM course_program cp
        JOIN courses c ON cp.course_id = c.id
        LEFT JOIN users u ON c.lecturer_id = u.id
        WHERE cp.program_id = :pid AND cp.year_level = :yl AND cp.semester = :sem
    ");
    $courseRows->execute([':pid' => $programId, ':yl' => $yearLevel, ':sem' => $semester]);
    $courses = $courseRows->fetchAll();

    if (empty($courses)) {
        return ['error' => 'No courses are mapped to that programme, year level, and semester yet. Add some in Manage Courses first.'];
    }

    // Optional pool of other lecturers also qualified to teach each course —
    // when present, the generator load-balances across the pool per attempt
    // instead of always using the single primary lecturer above.
    $courseIds = array_column($courses, 'course_id');
    $poolByCourse = [];
    if ($courseIds) {
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        $poolStmt = $pdo->prepare("SELECT course_id, lecturer_id FROM course_lecturers WHERE course_id IN ($placeholders)");
        $poolStmt->execute($courseIds);
        foreach ($poolStmt->fetchAll() as $row) {
            $poolByCourse[$row['course_id']][] = $row['lecturer_id'];
        }
    }
    $lecturerNames = [];
    foreach ($pdo->query("SELECT id, fullname FROM users WHERE role IN ('Lecturer', 'Administrator')")->fetchAll() as $u) {
        $lecturerNames[$u['id']] = $u['fullname'];
    }

    $rooms = $pdo->query('SELECT id, room_name, capacity FROM rooms ORDER BY capacity DESC')->fetchAll();
    if (empty($rooms)) {
        return ['error' => 'No rooms exist yet. Add at least one room in Manage Resources first.'];
    }

    // Preload existing busy slots (lecturer + room) from every OTHER schedule
    // in the system, so the generator never clashes with what's already live.
    // COALESCE reads a per-schedule lecturer override where the generator (or
    // a manual edit) recorded one, falling back to the course's own lecturer.
    $existingSql = '
        SELECT s.day_of_week, s.start_time, s.end_time, s.room_id, s.program_id, s.year_level, s.semester,
               COALESCE(s.lecturer_id, c.lecturer_id) AS lecturer_id
        FROM schedules s
        LEFT JOIN courses c ON s.course_id = c.id';
    if ($clearExisting) {
        $existingSql .= ' WHERE NOT (s.program_id = :pid AND s.year_level = :yl AND s.semester = :sem)';
    }
    $existing = $pdo->prepare($existingSql);
    if ($clearExisting) {
        $existing->execute([':pid' => $programId, ':yl' => $yearLevel, ':sem' => $semester]);
    } else {
        $existing->execute();
    }

    $dayIndex = array_flip($days);
    $slotCount = count($days) * $periodsPerDay;

    $slotTimes = [];
    for ($p = 0; $p < $periodsPerDay; $p++) {
        $minutes = $startHour * 60 + $p * $periodMinutes;
        $slotTimes[$p] = sprintf('%02d:%02d:00', intdiv($minutes, 60) % 24, $minutes % 60);
        $endMinutes = $minutes + $periodMinutes;
        $slotTimes[$p . '_end'] = sprintf('%02d:%02d:00', intdiv($endMinutes, 60) % 24, $endMinutes % 60);
    }

    $baseLecturerBusy = []; // "lecturerId|slot" => true
    $baseRoomBusy = [];     // "roomId|slot" => true
    $baseClassBusy = [];    // "slot" => true for the target cohort when retaining existing slots
    foreach ($existing->fetchAll() as $row) {
        if (!isset($dayIndex[$row['day_of_week']])) continue;
        foreach ($slotTimes as $p => $time) {
            if (is_string($p)) continue;
            $slotEnd = $slotTimes[$p . '_end'];
            if ($row['start_time'] >= $slotEnd || $row['end_time'] <= $time) continue;
            $slot = $dayIndex[$row['day_of_week']] * $periodsPerDay + $p;
            if ($row['lecturer_id']) $baseLecturerBusy[$row['lecturer_id'] . '|' . $slot] = true;
            $baseRoomBusy[$row['room_id'] . '|' . $slot] = true;
            if (!$clearExisting && (int) $row['program_id'] === $programId && (int) $row['year_level'] === $yearLevel && (int) $row['semester'] === $semester) {
                $baseClassBusy[$slot] = true;
            }
        }
    }

    $totalCards = 0;
    foreach ($courses as $course) {
        $totalCards += max(1, (int) $course['periods_per_week']);
    }

    $bestPlacements = [];
    $bestUnplaced = PHP_INT_MAX;
    $bestPenalty = PHP_FLOAT_MAX;

    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        // Load-balance across each course's qualified-lecturer pool for this
        // attempt: process courses in a random order, always handing the
        // course to whichever eligible lecturer has the lightest load so far.
        $courseOrderForLoad = $courses;
        shuffle($courseOrderForLoad);
        $lecturerLoad = [];
        $chosenLecturerFor = [];
        foreach ($courseOrderForLoad as $course) {
            $candidates = $poolByCourse[$course['course_id']] ?? [];
            if ($course['lecturer_id'] && !in_array($course['lecturer_id'], $candidates, true)) {
                $candidates[] = $course['lecturer_id'];
            }
            if (empty($candidates)) {
                $chosenLecturerFor[$course['course_id']] = null;
                continue;
            }
            usort($candidates, fn($a, $b) => ($lecturerLoad[$a] ?? 0) <=> ($lecturerLoad[$b] ?? 0));
            $chosen = $candidates[0];
            $chosenLecturerFor[$course['course_id']] = $chosen;
            $lecturerLoad[$chosen] = ($lecturerLoad[$chosen] ?? 0) + max(1, (int) $course['periods_per_week']);
        }

        // Expand into one "card" per weekly session, using this attempt's chosen lecturer.
        $order = [];
        foreach ($courses as $course) {
            $chosen = $chosenLecturerFor[$course['course_id']];
            $card = $course;
            $card['lecturer_id'] = $chosen;
            $card['lecturer_name'] = $chosen ? ($lecturerNames[$chosen] ?? $course['lecturer_name']) : null;
            for ($i = 0; $i < max(1, (int) $course['periods_per_week']); $i++) {
                $order[] = $card;
            }
        }
        shuffle($order);
        // Courses with more weekly sessions are harder to fit — place them first.
        usort($order, fn($a, $b) => $b['periods_per_week'] <=> $a['periods_per_week']);

        $classBusy = $baseClassBusy;
        $lecturerBusy = $baseLecturerBusy;
        $roomBusy = $baseRoomBusy;
        $courseDayCount = []; // "courseId|day" => count, to spread sessions across the week
        $placements = [];
        $unplaced = 0;
        $penalty = 0;

        foreach ($order as $card) {
            $bestSlot = null;
            $bestRoomId = null;
            $bestScore = PHP_FLOAT_MAX;

            for ($slot = 0; $slot < $slotCount; $slot++) {
                if (isset($classBusy[$slot])) continue;
                $lecKey = $card['lecturer_id'] . '|' . $slot;
                if ($card['lecturer_id'] && isset($lecturerBusy[$lecKey])) continue;

                $roomChoice = null;
                foreach ($rooms as $room) {
                    if (!isset($roomBusy[$room['id'] . '|' . $slot])) {
                        $roomChoice = $room['id'];
                        break;
                    }
                }
                if ($roomChoice === null) continue;

                $dayOfSlot = intdiv($slot, $periodsPerDay);
                $periodOfSlot = $slot % $periodsPerDay;
                $sameDayCount = $courseDayCount[$card['course_id'] . '|' . $dayOfSlot] ?? 0;

                $score = $sameDayCount * 50           // discourage repeating a course same day
                    + $periodOfSlot * 2                // mild preference for earlier periods
                    + mt_rand(0, 20);                  // randomise ties across attempts

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestSlot = $slot;
                    $bestRoomId = $roomChoice;
                }
            }

            if ($bestSlot === null) {
                $unplaced++;
                continue;
            }

            $classBusy[$bestSlot] = true;
            if ($card['lecturer_id']) $lecturerBusy[$card['lecturer_id'] . '|' . $bestSlot] = true;
            $roomBusy[$bestRoomId . '|' . $bestSlot] = true;
            $dayOfSlot = intdiv($bestSlot, $periodsPerDay);
            $periodOfSlot = $bestSlot % $periodsPerDay;
            $courseDayCount[$card['course_id'] . '|' . $dayOfSlot] = ($courseDayCount[$card['course_id'] . '|' . $dayOfSlot] ?? 0) + 1;
            $penalty += $bestScore;

            $room = current(array_filter($rooms, fn($r) => $r['id'] === $bestRoomId));
            $placements[] = [
                'course_id' => $card['course_id'],
                'course_code' => $card['course_code'],
                'course_name' => $card['course_name'],
                'lecturer_id' => $card['lecturer_id'],
                'lecturer_name' => $card['lecturer_name'] ?? 'Unassigned',
                'room_id' => $bestRoomId,
                'room_name' => $room ? $room['room_name'] : 'Room',
                'day_of_week' => $days[$dayOfSlot],
                'start_time' => $slotTimes[$periodOfSlot],
                'end_time' => $slotTimes[$periodOfSlot . '_end'],
            ];
        }

        if ($unplaced < $bestUnplaced || ($unplaced === $bestUnplaced && $penalty < $bestPenalty)) {
            $bestUnplaced = $unplaced;
            $bestPenalty = $penalty;
            $bestPlacements = $placements;
        }
        if ($bestUnplaced === 0) break;
    }

    $quality = max(0, min(100, round(100 - $bestUnplaced * 15 - min(20, $bestPenalty / max(1, count($bestPlacements)) / 4))));

    return [
        'placements' => $bestPlacements,
        'placed' => count($bestPlacements),
        'total' => $totalCards,
        'unplaced' => $bestUnplaced,
        'quality' => (int) $quality,
    ];
}

try {
    $programmes = $pdo->query("SELECT id, program_code, program_name FROM programmes WHERE LOWER(TRIM(program_code)) NOT IN ('not-assigned-program', 'not assigned') AND LOWER(TRIM(program_name)) <> 'not assigned' ORDER BY program_code ASC")->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Your session has expired. Refresh and try again.');
        }

        if (isset($_POST['run_generate'])) {
            $programId = (int) ($_POST['program_id'] ?? 0);
            $yearLevel = (int) ($_POST['year_level'] ?? 1);
            $semester = (int) ($_POST['semester'] ?? 1);
            $selectedDays = array_values(array_intersect($ALL_DAYS, $_POST['days'] ?? []));
            $periodsPerDay = max(1, min(12, (int) ($_POST['periods_per_day'] ?? 6)));
            $startHour = max(0, min(20, (int) ($_POST['start_hour'] ?? 8)));
            $periodMinutes = max(30, min(180, (int) ($_POST['period_minutes'] ?? 60)));
            $effort = max(1, min(5, (int) ($_POST['effort'] ?? 3)));

            if ($programId < 1 || empty($selectedDays)) {
                throw new RuntimeException('Choose a programme and at least one day.');
            }

            $clearExisting = isset($_POST['clear_existing']);
            $result = generateTimetable($pdo, $programId, $yearLevel, $semester, $selectedDays, $periodsPerDay, $startHour, $periodMinutes, $effort * 6, $clearExisting);

            if (isset($result['error'])) {
                throw new RuntimeException($result['error']);
            }

            $result['program_id'] = $programId;
            $result['year_level'] = $yearLevel;
            $result['semester'] = $semester;
            $result['clear_existing'] = $clearExisting;
            $_SESSION['generated_preview'] = $result;
            $preview = $result;
            $message = "Draft generated: {$result['placed']} of {$result['total']} sessions placed (quality {$result['quality']}%). Review below, then confirm to publish.";
            $messageType = $result['unplaced'] > 0 ? 'error' : 'success';
        }

        if (isset($_POST['discard_preview'])) {
            unset($_SESSION['generated_preview']);
            $preview = null;
            $message = 'Draft discarded.';
            $messageType = 'success';
        }

        if (isset($_POST['confirm_generate']) && $preview) {
            if ((int) $preview['total'] < 1 || (int) $preview['unplaced'] > 0) {
                throw new RuntimeException('Only a complete timetable draft can be published. Adjust the generation settings and generate a complete draft first.');
            }
            $pdo->beginTransaction();
            if (!empty($preview['clear_existing'])) {
                $ids = $pdo->prepare("SELECT id FROM schedules WHERE program_id = :pid AND year_level = :yl AND semester = :sem AND type = 'Class'");
                $ids->execute([':pid' => $preview['program_id'], ':yl' => $preview['year_level'], ':sem' => $preview['semester']]);
                $scheduleIds = $ids->fetchAll(PDO::FETCH_COLUMN);
                if ($scheduleIds) {
                    $placeholders = implode(',', array_fill(0, count($scheduleIds), '?'));
                    $pdo->prepare("DELETE FROM reschedule_requests WHERE schedule_id IN ($placeholders)")->execute($scheduleIds);
                    $pdo->prepare("DELETE FROM schedules WHERE id IN ($placeholders)")->execute($scheduleIds);
                }
            }

            $insert = $pdo->prepare("
                INSERT INTO schedules (program_id, course_id, room_id, lecturer_id, type, day_of_week, start_time, end_time, year_level, semester)
                VALUES (:program_id, :course_id, :room_id, :lecturer_id, 'Class', :day, :start, :end, :year_level, :semester)
            ");
            foreach ($preview['placements'] as $row) {
                $insert->execute([
                    ':program_id' => $preview['program_id'],
                    ':course_id' => $row['course_id'],
                    ':room_id' => $row['room_id'],
                    ':lecturer_id' => $row['lecturer_id'],
                    ':day' => $row['day_of_week'],
                    ':start' => $row['start_time'],
                    ':end' => $row['end_time'],
                    ':year_level' => $preview['year_level'],
                    ':semester' => $preview['semester'],
                ]);
            }

            $log = $pdo->prepare('INSERT INTO generated_timetables (program_id, year_level, semester, generated_by, placed_count, total_count, quality_score) VALUES (:pid, :yl, :sem, :by, :placed, :total, :quality)');
            $log->execute([
                ':pid' => $preview['program_id'],
                ':yl' => $preview['year_level'],
                ':sem' => $preview['semester'],
                ':by' => $_SESSION['user_id'],
                ':placed' => $preview['placed'],
                ':total' => $preview['total'],
                ':quality' => $preview['quality'],
            ]);

            $pdo->commit();
            unset($_SESSION['generated_preview']);
            $preview = null;
            $message = 'Generated timetable published to the master schedule.';
            $messageType = 'success';
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule - Generate Timetable</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int) filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="dashboard-body">
<nav class="navbar">
    <div class="logo">Reschedule<span>.</span></div>
    <div class="user-profile">
        <a href="dashboard.php" class="card-link">&larr; Dashboard</a>
        <a href="logout.php" class="btn-logout">Log Out</a>
    </div>
</nav>
<main class="dashboard-container">
    <header class="welcome-banner">
        <div>
            <span class="eyebrow">Administrator workspace</span>
            <h1>Generate timetable</h1>
            <p>Auto-place weekly class sessions for a programme, year, and semester — conflict-checked against lecturers and rooms already in use.</p>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="alert-box <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="event-management-grid">
        <article class="dash-card">
            <div class="card-heading-row">
                <h3>Generation settings</h3>
            </div>
            <form method="POST" class="event-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="program_id">Programme</label>
                    <select id="program_id" name="program_id" required>
                        <option value="">Select programme...</option>
                        <?php foreach ($programmes as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo (isset($_POST['program_id']) && (int) $_POST['program_id'] === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['program_code'] . ' - ' . $p['program_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="year_level">Year level</label>
                        <select id="year_level" name="year_level">
                            <option value="1">Year 1</option><option value="2">Year 2</option><option value="3">Year 3</option><option value="4">Year 4</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester">
                            <option value="1">Semester 1</option><option value="2">Semester 2</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Days to include</label>
                    <div class="checkbox-grid" style="max-height:none;">
                        <?php foreach ($ALL_DAYS as $d): ?>
                            <label class="checkbox-item"><input type="checkbox" name="days[]" value="<?php echo $d; ?>" <?php echo in_array($d, ['Monday','Tuesday','Wednesday','Thursday','Friday'], true) ? 'checked' : ''; ?>> <?php echo $d; ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="periods_per_day">Periods per day</label>
                        <input type="number" id="periods_per_day" name="periods_per_day" min="1" max="12" value="6">
                    </div>
                    <div class="form-group">
                        <label for="start_hour">Day starts at (hour, 24h)</label>
                        <input type="number" id="start_hour" name="start_hour" min="0" max="20" value="8">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="period_minutes">Minutes per period</label>
                        <input type="number" id="period_minutes" name="period_minutes" min="30" max="180" step="5" value="60">
                    </div>
                    <div class="form-group">
                        <label for="effort">Effort (attempts)</label>
                        <select id="effort" name="effort">
                            <option value="1">Fast</option>
                            <option value="3" selected>Balanced</option>
                            <option value="5">Thorough</option>
                        </select>
                    </div>
                </div>
                <label class="event-all-day"><input type="checkbox" name="clear_existing" value="1"> Replace this programme's existing class schedule first</label>
                <button type="submit" name="run_generate" class="btn-submit">Generate draft</button>
            </form>
        </article>

        <article class="dash-card">
            <div class="card-heading-row">
                <h3>Draft preview</h3>
                <?php if ($preview): ?><span class="section-hint">Quality <?php echo (int) $preview['quality']; ?>% &middot; <?php echo (int) $preview['placed']; ?>/<?php echo (int) $preview['total']; ?> placed</span><?php endif; ?>
            </div>
            <?php if (!$preview): ?>
                <p class="empty-state">Run a generation to see a draft timetable here before publishing it.</p>
            <?php else: ?>
                <?php if ($preview['unplaced'] > 0): ?>
                    <div class="alert-box error"><?php echo (int) $preview['unplaced']; ?> session(s) could not be placed — try more days, more periods, or another room/lecturer.</div>
                <?php endif; ?>
                <div style="overflow-x:auto;">
                    <table class="schedule-table">
                        <thead>
                            <tr><th>Day</th><th>Time</th><th>Course</th><th>Lecturer</th><th>Room</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview['placements'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['day_of_week']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($row['start_time'], 0, 5) . ' - ' . substr($row['end_time'], 0, 5)); ?></td>
                                    <td><?php echo htmlspecialchars($row['course_code'] . ' - ' . $row['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['lecturer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <form method="POST" style="margin-top:1rem; display:flex; gap:0.6rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <?php if ((int) $preview['unplaced'] === 0 && (int) $preview['total'] > 0): ?>
                        <button type="submit" name="confirm_generate" class="btn-submit">Confirm &amp; publish</button>
                    <?php else: ?>
                        <span class="section-hint">Complete all sessions before publishing.</span>
                    <?php endif; ?>
                    <button type="submit" name="discard_preview" class="filter-reset">Discard draft</button>
                </form>
            <?php endif; ?>
        </article>
    </section>
</main>
</body>
</html>
