<?php
session_start();
require_once 'db.php';
require_once 'schedule_exceptions.php';

// Auth Guard: Admin and Lecturer access only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch dropdown data from the local MySQL database.
$programmes = $pdo->query('SELECT id, program_code, program_name FROM programmes ORDER BY program_name ASC')->fetchAll();
$courses = $pdo->query('SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC')->fetchAll();
$rooms = $pdo->query('SELECT id, room_name FROM rooms ORDER BY room_name ASC')->fetchAll();
$courseMappings = [];
$mappingRows = $pdo->query('SELECT course_id, program_id, year_level, semester FROM course_program ORDER BY program_id')->fetchAll();
foreach ($mappingRows as $mappingRow) {
    $courseMappings[(int) $mappingRow['course_id']][] = [
        'program_id' => (int) $mappingRow['program_id'],
        'year_level' => (int) $mappingRow['year_level'],
        'semester' => (int) $mappingRow['semester']
    ];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your session has expired. Refresh and try again.';
    } else {
    $selected_programmes = array_values(array_unique(array_map('intval', $_POST['program_ids'] ?? [])));
    $course_id   = $_POST['course_id'] ?? '';
    $room_id     = $_POST['room_id'] ?? '';
    $type        = $_POST['type'] ?? 'Class';
    $day_of_week = $_POST['day_of_week'] ?? 'Monday';
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';
    $date        = !empty($_POST['date']) ? $_POST['date'] : null;

    if (!in_array($type, ['Class', 'CA', 'Exam'], true)) {
        $error = 'Choose a valid schedule category.';
    } elseif (in_array($type, ['CA', 'Exam'], true) && !$date) {
        $error = 'A specific date is required for a CA or exam.';
    } elseif (empty($selected_programmes) || empty($course_id) || empty($room_id) || empty($start_time) || empty($end_time)) {
        $error = "Please select at least one programme and fill in all required fields.";
    } else {
        if ($date) {
            $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (!$dateValue || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                $error = 'Enter a valid schedule date.';
            } elseif (in_array($type, ['CA', 'Exam'], true)) {
                $day_of_week = $dateValue->format('l');
            }
        }
        $inserted_count = 0;
        $replacedClassOccurrences = 0;
        $has_error = $error !== '';

        $courseProgrammeStmt = $pdo->prepare('SELECT program_id, year_level, semester FROM course_program WHERE course_id = :course_id');
        $courseProgrammeStmt->execute([':course_id' => (int) $course_id]);
        $courseProgrammeLevels = [];
        foreach ($courseProgrammeStmt->fetchAll() as $courseProgramme) {
            $courseProgrammeLevels[(int) $courseProgramme['program_id']] = [
                'year_level' => (int) $courseProgramme['year_level'],
                'semester' => (int) $courseProgramme['semester']
            ];
        }

        $unassignedProgrammes = array_diff(array_map('intval', $selected_programmes), array_keys($courseProgrammeLevels));
        if (!$courseProgrammeLevels) {
            $error = 'This course has no programme assignments. Assign the course to programmes with their study levels first.';
            $has_error = true;
        } elseif ($unassignedProgrammes) {
            $error = 'One or more selected programmes are not assigned to this course. Update the course assignment first.';
            $has_error = true;
        }

        if (!$has_error) {
            $cohortConflictSql = in_array($type, ['CA', 'Exam'], true)
                ? "SELECT id FROM schedules WHERE program_id = :program_id AND year_level = :year_level AND semester = :semester AND type <> 'Class' AND date = :date AND start_time < :end_time AND end_time > :start_time LIMIT 1"
                : "SELECT id FROM schedules WHERE program_id = :program_id AND year_level = :year_level AND semester = :semester AND type = 'Class' AND (date IS NULL OR date = :date) AND day_of_week = :day AND start_time < :end_time AND end_time > :start_time LIMIT 1";
            $cohortConflictStmt = $pdo->prepare($cohortConflictSql);
            foreach ($selected_programmes as $programId) {
                $assignment = $courseProgrammeLevels[$programId] ?? null;
                if (!$assignment) {
                    continue;
                }
                $params = [
                    ':program_id' => $programId,
                    ':year_level' => $assignment['year_level'],
                    ':semester' => $assignment['semester'],
                    ':date' => $date,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time
                ];
                if (!in_array($type, ['CA', 'Exam'], true)) {
                    $params[':day'] = $day_of_week;
                }
                $cohortConflictStmt->execute($params);
                if ($cohortConflictStmt->fetch()) {
                    $error = in_array($type, ['CA', 'Exam'], true)
                        ? 'Conflict detected: this programme already has another assessment at that date and time.'
                        : 'Conflict detected: this programme, year, and semester already has a class during that time.';
                    $has_error = true;
                    break;
                }
            }
        }

        if (!$has_error) {
            $roomConflictSql = in_array($type, ['CA', 'Exam'], true)
                ? 'SELECT id, type, program_id, year_level, semester FROM schedules WHERE room_id = :room_id AND day_of_week = :day AND (date IS NULL OR date = :date) AND start_time < :end_time AND end_time > :start_time'
                : "SELECT id, type, program_id, year_level, semester FROM schedules WHERE room_id = :room_id AND type = 'Class' AND date IS NULL AND day_of_week = :day AND start_time < :end_time AND end_time > :start_time";
            $roomConflictStmt = $pdo->prepare($roomConflictSql);
            $roomConflictParams = [':room_id' => (int) $room_id, ':day' => $day_of_week, ':start_time' => $start_time, ':end_time' => $end_time];
            if (in_array($type, ['CA', 'Exam'], true)) {
                $roomConflictParams[':date'] = $date;
            }
            $roomConflictStmt->execute($roomConflictParams);
            foreach ($roomConflictStmt->fetchAll() as $roomConflict) {
                $willBeReplaced = $type !== 'Class'
                    && $roomConflict['type'] === 'Class'
                    && isset($courseProgrammeLevels[(int) $roomConflict['program_id']])
                    && $courseProgrammeLevels[(int) $roomConflict['program_id']]['year_level'] === (int) $roomConflict['year_level']
                    && $courseProgrammeLevels[(int) $roomConflict['program_id']]['semester'] === (int) $roomConflict['semester']
                    && in_array((int) $roomConflict['program_id'], $selected_programmes, true);
                if (!$willBeReplaced) {
                    $error = 'Conflict detected: the selected room is already booked during that time.';
                    $has_error = true;
                    break;
                }
            }
        }

        if (!$has_error) {
            $lecturerConflictSql = in_array($type, ['CA', 'Exam'], true)
                ? "SELECT s.id, s.type, s.program_id, s.year_level, s.semester FROM schedules s JOIN courses existing_course ON s.course_id = existing_course.id WHERE existing_course.lecturer_id = (SELECT lecturer_id FROM courses WHERE id = :course_id) AND s.day_of_week = :day AND (s.date IS NULL OR s.date = :date) AND s.start_time < :end_time AND s.end_time > :start_time"
                : "SELECT s.id, s.type, s.program_id, s.year_level, s.semester FROM schedules s JOIN courses existing_course ON s.course_id = existing_course.id WHERE existing_course.lecturer_id = (SELECT lecturer_id FROM courses WHERE id = :course_id) AND s.type = 'Class' AND s.date IS NULL AND s.day_of_week = :day AND s.start_time < :end_time AND s.end_time > :start_time";
            $lecturerConflictStmt = $pdo->prepare($lecturerConflictSql);
            $lecturerConflictParams = [':course_id' => (int) $course_id, ':day' => $day_of_week, ':start_time' => $start_time, ':end_time' => $end_time];
            if (in_array($type, ['CA', 'Exam'], true)) {
                $lecturerConflictParams[':date'] = $date;
            }
            $lecturerConflictStmt->execute($lecturerConflictParams);
            foreach ($lecturerConflictStmt->fetchAll() as $lecturerConflict) {
                $willBeReplaced = $type !== 'Class'
                    && $lecturerConflict['type'] === 'Class'
                    && isset($courseProgrammeLevels[(int) $lecturerConflict['program_id']])
                    && $courseProgrammeLevels[(int) $lecturerConflict['program_id']]['year_level'] === (int) $lecturerConflict['year_level']
                    && $courseProgrammeLevels[(int) $lecturerConflict['program_id']]['semester'] === (int) $lecturerConflict['semester']
                    && in_array((int) $lecturerConflict['program_id'], $selected_programmes, true);
                if (!$willBeReplaced) {
                    $error = 'Conflict detected: the lecturer already has a schedule during that time.';
                    $has_error = true;
                    break;
                }
            }
        }

        if (!$has_error) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO schedules
                        (program_id, course_id, room_id, type, day_of_week, start_time, end_time, year_level, semester, date)
                    VALUES
                        (:program_id, :course_id, :room_id, :type, :day_of_week, :start_time, :end_time, :year_level, :semester, :date)
                    RETURNING id
                ');

                foreach ($selected_programmes as $program_id) {
                    $program_id = (int) $program_id;
                    $assignment = $courseProgrammeLevels[$program_id];
                    $stmt->execute([
                        ':program_id' => $program_id,
                        ':course_id' => (int) $course_id,
                        ':room_id' => (int) $room_id,
                        ':type' => $type,
                        ':day_of_week' => $day_of_week,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time,
                        ':year_level' => $assignment['year_level'],
                        ':semester' => $assignment['semester'],
                        ':date' => $date
                    ]);
                    $scheduleId = (int) $stmt->fetchColumn();
                    $inserted_count++;
                    if (in_array($type, ['CA', 'Exam'], true)) {
                        $replacedClassOccurrences += recordAssessmentClassExceptions($pdo, $scheduleId);
                    } else {
                        refreshClassExceptions($pdo, $scheduleId);
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Unable to publish the schedule: ' . $e->getMessage();
                $has_error = true;
            }
        }

        if (!$has_error && $inserted_count > 0) {
            $message = in_array($type, ['CA', 'Exam'], true)
                ? "Assessment published for {$inserted_count} programme(s). {$replacedClassOccurrences} regular class occurrence(s) on that date were replaced; future weekly classes remain scheduled."
                : "Schedule published successfully for {$inserted_count} programme(s), using each course assignment's level and semester.";
        }
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
    <nav class="navbar">
        <div class="logo"><img src="assets/reschedule-logo.png" alt="ReSchedule. Campus Timetable System"></div>
        <div class="user-profile">
            <a href="timetable.php" class="card-link">&larr; Timetable</a>
            <a href="dashboard.php" class="card-link">Dashboard</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </nav>
    <main class="dashboard-container">
        <div class="dash-card style-form-box" style="max-width: 650px; margin: 2rem auto;">
            <span class="eyebrow">Academic scheduling</span>
            <h2>Publish a schedule</h2>
            <p style="color: var(--muted);">Create a class, assessment, or examination slot for the selected programmes.</p>

            <?php if (!empty($message)): ?>
                <div class="alert-box success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert-box error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="add_schedule.php" method="POST" style="display: grid; gap: 1rem; margin-top: 1rem;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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
                    <label>Select assigned programmes:</label>
                    <p class="field-help">The course's programme mapping supplies the correct year and semester for each selected programme.</p>
                    <div class="checkbox-grid">
                        <?php foreach ($programmes as $p): ?>
                            <label class="checkbox-item assigned-programme" data-program-id="<?php echo (int) $p['id']; ?>">
                                <input type="checkbox" name="program_ids[]" value="<?php echo htmlspecialchars($p['id']); ?>" disabled>
                                <strong>[<?php echo htmlspecialchars($p['program_code'] ?? ''); ?>]</strong> <?php echo htmlspecialchars($p['program_name'] ?? ''); ?>
                                <small class="assignment-level"></small>
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
                            <option value="<?php echo htmlspecialchars($c['id']); ?>" data-programmes="<?php echo htmlspecialchars(json_encode($courseMappings[(int) $c['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(($c['course_code'] ?? '') . ' - ' . ($c['course_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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

    <script>
        const courseSelect = document.getElementById('course_id');
        const scheduleType = document.getElementById('type');
        const scheduleDate = document.getElementById('date');
        const scheduleDay = document.getElementById('day_of_week');
        const programmeChoices = Array.from(document.querySelectorAll('.assigned-programme'));

        function updateAssessmentDate() {
            const requiresDate = ['CA', 'Exam'].includes(scheduleType?.value);
            scheduleDate.required = requiresDate;
            if (requiresDate && scheduleDate.value) {
                const selectedDate = new Date(`${scheduleDate.value}T00:00:00`);
                scheduleDay.value = selectedDate.toLocaleDateString('en-US', { weekday: 'long' });
            }
        }

        function updateAssignedProgrammes() {
            const selectedOption = courseSelect?.selectedOptions[0];
            const assignments = selectedOption ? JSON.parse(selectedOption.dataset.programmes || '[]') : [];
            const assignmentByProgramme = new Map(assignments.map((assignment) => [String(assignment.program_id), assignment]));

            programmeChoices.forEach((choice) => {
                const input = choice.querySelector('input');
                const level = choice.querySelector('.assignment-level');
                const assignment = assignmentByProgramme.get(choice.dataset.programId);
                input.disabled = !assignment;
                if (!assignment) input.checked = false;
                choice.classList.toggle('is-assigned', Boolean(assignment));
                level.textContent = assignment ? `Year ${assignment.year_level}, Sem ${assignment.semester}` : 'Not assigned to this course';
            });
        }

        courseSelect?.addEventListener('change', updateAssignedProgrammes);
        scheduleType?.addEventListener('change', updateAssessmentDate);
        scheduleDate?.addEventListener('change', updateAssessmentDate);
        updateAssignedProgrammes();
        updateAssessmentDate();
    </script>
</body>
</html>
