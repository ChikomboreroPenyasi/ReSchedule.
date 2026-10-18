<?php
session_start();

// Import shared database setup
require_once 'db.php';
require_once 'schedule_exceptions.php';

// Auth Guard: Admin and Lecturer access only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';
$schedule_id = $_GET['id'] ?? null;
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!$schedule_id) {
    header("Location: dashboard.php");
    exit();
}

try {
    // Fetch programmes for dropdown using shared $pdo from db.php
    $programmes = $pdo->query("SELECT id, program_code, program_name FROM programmes ORDER BY program_code ASC")->fetchAll();

    // Fetch rooms for dropdown
    $rooms = $pdo->query("SELECT id, room_name FROM rooms ORDER BY room_name ASC")->fetchAll();

    $courseMappingsStmt = $pdo->prepare('SELECT program_id, year_level, semester, periods_per_week FROM course_program WHERE course_id = :course_id');

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

    $courseMappingsStmt->execute([':course_id' => (int) $schedule['course_id']]);
    $courseMappings = [];
    foreach ($courseMappingsStmt->fetchAll() as $mapping) {
        $courseMappings[(int) $mapping['program_id']] = [
            'year_level' => (int) $mapping['year_level'],
            'semester' => (int) $mapping['semester'],
            'periods_per_week' => (int) $mapping['periods_per_week']
        ];
    }

    // Handle Form Update Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Your session has expired. Refresh and try again.');
        }
        $type = $_POST['type'] ?? 'Class';
        $selected_program_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['program_ids'] ?? []))));
        $submitted_year_levels = $_POST['year_level'] ?? [];
        $submitted_semesters = $_POST['semester'] ?? [];
        $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
        $course_name = trim($_POST['course_name'] ?? '');
        $room_id = !empty($_POST['room_id']) ? intval($_POST['room_id']) : null;
        $day_of_week = $_POST['day_of_week'] ?? null;
        $date = !empty($_POST['date']) ? $_POST['date'] : null;
        $start_time = $_POST['start_time'] ?? null;
        $end_time = $_POST['end_time'] ?? null;
        $isAssessment = in_array($type, ['CA', 'Exam'], true);
        if ($isAssessment && !$date) {
            $error = 'A specific date is required for a CA or exam.';
        } elseif ($date) {
            $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (!$dateValue || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                $error = 'Enter a valid schedule date.';
            } elseif ($isAssessment) {
                $day_of_week = $dateValue->format('l');
            }
        }

        $selectedMappings = [];
        foreach ($selected_program_ids as $selectedProgramId) {
            $selectedYear = (int) ($submitted_year_levels[$selectedProgramId] ?? 0);
            $selectedSemester = (int) ($submitted_semesters[$selectedProgramId] ?? 0);
            if ($selectedYear < 1 || $selectedYear > 4 || $selectedSemester < 1 || $selectedSemester > 2) {
                $error = 'Choose a valid study level and semester for every selected programme.';
                break;
            }
            $selectedMappings[$selectedProgramId] = [
                'year_level' => $selectedYear,
                'semester' => $selectedSemester
            ];
        }

        if (!empty($error)) {
            // Keep the validation error and do not process the update.
        } elseif (empty($selected_program_ids) || empty($course_code) || empty($course_name) || empty($start_time) || empty($end_time) || empty($room_id)) {
            $error = "Please fill in all required fields.";
        } else {
            $roomConflictSql = $isAssessment
                ? "SELECT id, type, date, program_id, year_level, semester FROM schedules WHERE room_id = :room_id AND day_of_week = :day_of_week AND id != :schedule_id AND (date IS NULL OR date = :date) AND start_time < :end_time AND end_time > :start_time"
                : "SELECT id, type, date, program_id, year_level, semester FROM schedules WHERE room_id = :room_id AND type = 'Class' AND date IS NULL AND day_of_week = :day_of_week AND id != :schedule_id AND start_time < :end_time AND end_time > :start_time";
            $roomConflictStmt = $pdo->prepare($roomConflictSql);
            $roomConflictParams = [
                ':room_id' => $room_id,
                ':day_of_week' => $day_of_week,
                ':end_time' => $end_time,
                ':start_time' => $start_time,
                ':schedule_id' => $schedule_id,
            ];
            if ($isAssessment) {
                $roomConflictParams[':date'] = $date;
            }
            $roomConflictStmt->execute($roomConflictParams);
            foreach ($roomConflictStmt->fetchAll() as $roomConflict) {
                $matchingCohort = isset($selectedMappings[(int) $roomConflict['program_id']])
                    && $selectedMappings[(int) $roomConflict['program_id']]['year_level'] === (int) $roomConflict['year_level']
                    && $selectedMappings[(int) $roomConflict['program_id']]['semester'] === (int) $roomConflict['semester'];
                if ($isAssessment && $roomConflict['type'] === 'Class' && $matchingCohort) {
                    continue;
                }
                $error = "Conflict detected: the selected room is already booked during this time slot.";
                break;
            }

            if (empty($error) && !$isAssessment) {
                $lecturerConflictSql = "SELECT s.id, s.type, s.program_id, s.year_level, s.semester FROM schedules s JOIN courses c ON s.course_id = c.id WHERE c.lecturer_id = (SELECT lecturer_id FROM courses WHERE id = :course_id) AND s.type = 'Class' AND s.date IS NULL AND s.day_of_week = :day_of_week AND s.id != :schedule_id AND s.start_time < :end_time AND s.end_time > :start_time";
                $lecturerConflictStmt = $pdo->prepare("
                    {$lecturerConflictSql}
                ");
                $lecturerConflictParams = [
                    ':course_id' => $schedule['course_id'],
                    ':day_of_week' => $day_of_week,
                    ':end_time' => $end_time,
                    ':start_time' => $start_time,
                    ':schedule_id' => $schedule_id,
                ];
                $lecturerConflictStmt->execute($lecturerConflictParams);
                foreach ($lecturerConflictStmt->fetchAll() as $lecturerConflict) {
                    $error = "Conflict detected: this lecturer already has another class scheduled during that time.";
                    break;
                }
            }

            if (empty($error)) {
                $cohortConflictSql = $isAssessment
                    ? "SELECT id FROM schedules WHERE program_id = :program_id AND year_level = :year_level AND semester = :semester AND type <> 'Class' AND date = :date AND day_of_week = :day_of_week AND id != :schedule_id AND start_time < :end_time AND end_time > :start_time LIMIT 1"
                    : "SELECT id FROM schedules WHERE program_id = :program_id AND year_level = :year_level AND semester = :semester AND type = 'Class' AND (date IS NULL OR date = :date) AND day_of_week = :day_of_week AND id != :schedule_id AND start_time < :end_time AND end_time > :start_time LIMIT 1";
                $cohortConflictStmt = $pdo->prepare($cohortConflictSql);
                foreach ($selectedMappings as $programId => $mapping) {
                    $cohortConflictParams = [
                        ':program_id' => (int) $programId,
                        ':year_level' => $mapping['year_level'],
                        ':semester' => $mapping['semester'],
                        ':day_of_week' => $day_of_week,
                        ':schedule_id' => $schedule_id,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time,
                        ':date' => $date,
                    ];
                    $cohortConflictStmt->execute($cohortConflictParams);
                    if ($cohortConflictStmt->fetch()) {
                        $error = $isAssessment
                            ? 'Conflict detected: this programme already has another assessment at that date and time.'
                            : 'Conflict detected: this programme, year, and semester already has a class during that time.';
                        break;
                    }
                }
            }

            if (empty($error)) {
                // 1. Update or Insert into 'courses' table
                $course_id = $schedule['course_id'];
                if ($course_id) {
                    $updateCourse = $pdo->prepare("UPDATE courses SET course_code = :course_code, course_name = :course_name WHERE id = :id");
                    $updateCourse->execute([':course_code' => $course_code, ':course_name' => $course_name, ':id' => $course_id]);
                } else {
                    $insertCourse = $pdo->prepare("INSERT INTO courses (course_code, course_name) VALUES (:course_code, :course_name) RETURNING id");
                    $insertCourse->execute([':course_code' => $course_code, ':course_name' => $course_name]);
                    $course_id = $insertCourse->fetchColumn();
                }

                // 3. Update 'schedules' table across both foreign keys and text columns
                $updateStmt = $pdo->prepare("
                    UPDATE schedules 
                    SET course_id = :course_id, program_id = :program_id, room_id = :room_id,
                        day_of_week = :day_of_week, start_time = :start_time, end_time = :end_time, 
                        type = :type, date = :date, course_code = :course_code, course_name = :course_name,
                        year_level = :year_level, semester = :semester
                    WHERE id = :id
                ");

                $firstProgramId = array_key_first($selectedMappings);
                $firstMapping = $selectedMappings[$firstProgramId];
                $pdo->beginTransaction();
                $replacedClassOccurrences = 0;
                $clearOldExceptions = $pdo->prepare("DELETE FROM schedule_exceptions WHERE schedule_id = :class_schedule_id OR source_schedule_id = :source_schedule_id");
                $clearOldExceptions->execute([':class_schedule_id' => $schedule_id, ':source_schedule_id' => $schedule_id]);

                $deleteMappings = $pdo->prepare('DELETE FROM course_program WHERE course_id = :course_id');
                $deleteMappings->execute([':course_id' => $course_id]);
                $insertMapping = $pdo->prepare('INSERT INTO course_program (course_id, program_id, year_level, semester, periods_per_week) VALUES (:course_id, :program_id, :year_level, :semester, :periods_per_week)');
                foreach ($selectedMappings as $programId => $mapping) {
                    $insertMapping->execute([
                        ':course_id' => $course_id,
                        ':program_id' => $programId,
                        ':year_level' => $mapping['year_level'],
                        ':semester' => $mapping['semester'],
                        ':periods_per_week' => $mapping['periods_per_week']
                    ]);
                }

                $updateStmt->execute([
                    ':course_id'   => $course_id,
                    ':program_id'  => $firstProgramId,
                    ':room_id'     => $room_id,
                    ':day_of_week' => $day_of_week,
                    ':start_time'  => $start_time,
                    ':end_time'    => $end_time,
                    ':type'        => $type,
                    ':date'        => $date,
                    ':course_code' => $course_code,
                    ':course_name' => $course_name,
                    ':year_level'  => (int) $firstMapping['year_level'],
                    ':semester'    => (int) $firstMapping['semester'],
                    ':id'          => $schedule_id
                ]);

                $insertStmt = $pdo->prepare("
                    INSERT INTO schedules
                        (program_id, course_id, room_id, type, day_of_week, start_time, end_time, date, course_code, course_name, year_level, semester)
                    VALUES
                        (:program_id, :course_id, :room_id, :type, :day_of_week, :start_time, :end_time, :date, :course_code, :course_name, :year_level, :semester)
                    RETURNING id
                ");
                $createdScheduleIds = [(int) $schedule_id];
                foreach ($selectedMappings as $programId => $mapping) {
                    if ((int) $programId === (int) $firstProgramId) {
                        continue;
                    }
                    $insertStmt->execute([
                        ':program_id' => $programId,
                        ':course_id' => $course_id,
                        ':room_id' => $room_id,
                        ':type' => $type,
                        ':day_of_week' => $day_of_week,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time,
                        ':date' => $date,
                        ':course_code' => $course_code,
                        ':course_name' => $course_name,
                        ':year_level' => (int) $mapping['year_level'],
                        ':semester' => (int) $mapping['semester']
                    ]);
                    $createdScheduleIds[] = (int) $insertStmt->fetchColumn();
                }
                if ($isAssessment) {
                    foreach ($createdScheduleIds as $createdScheduleId) {
                        $replacedClassOccurrences += recordAssessmentClassExceptions($pdo, $createdScheduleId);
                    }
                } else {
                    foreach ($createdScheduleIds as $createdScheduleId) {
                        refreshClassExceptions($pdo, $createdScheduleId);
                    }
                }
                $pdo->commit();

                $message = "Schedule entry updated for " . count($selectedMappings) . " programme assignment(s).";
                if ($isAssessment) {
                    $message .= " {$replacedClassOccurrences} regular class occurrence(s) replaced on that date; future weekly classes remain scheduled.";
                }
            
                // Refresh schedule data in scope
                $stmt->execute([':id' => $schedule_id]);
                $schedule = $stmt->fetch();
            }
        }
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "Database Error: " . $e->getMessage();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule - Edit Schedule Entry</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-body">

    <nav class="navbar">
        <div class="logo"><img src="assets/reschedule-logo.png" alt="ReSchedule. Campus Timetable System"></div>
        <div class="user-profile">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-controls="scheduleSidebar" aria-expanded="false">☰ Menu</button>
            <a href="timetable.php" class="card-link">&larr; Timetable</a>
            <a href="dashboard.php" class="card-link">Dashboard</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </nav>

    <div class="dashboard-shell">
        <aside class="dashboard-sidebar" id="scheduleSidebar">
            <div class="sidebar-heading">
                <strong><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Staff user'); ?></strong>
                <span><?php echo htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?> workspace</span>
            </div>
            <span class="sidebar-label">Workspace</span>
            <nav class="sidebar-nav" aria-label="Schedule navigation">
                <a href="dashboard.php"><span class="sidebar-icon">⌂</span>Overview</a>
                <a href="timetable.php" class="is-active"><span class="sidebar-icon">▦</span>Master timetable</a>
            </nav>
            <span class="sidebar-label">Management</span>
            <nav class="sidebar-nav" aria-label="Management navigation">
                <a href="add_schedule.php"><span class="sidebar-icon">＋</span>Add schedule</a>
                <a href="manage_courses.php"><span class="sidebar-icon">◇</span>Courses</a>
                <a href="manage_resources.php"><span class="sidebar-icon">▣</span>Resources</a>
                <a href="manage_reschedules.php"><span class="sidebar-icon">↻</span>Requests</a>
                <?php if (($_SESSION['role'] ?? '') === 'Administrator'): ?>
                    <a href="manage_users.php"><span class="sidebar-icon">◎</span>Users</a>
                    <a href="ai_import.php"><span class="sidebar-icon">✦</span>AI import</a>
                <?php endif; ?>
            </nav>
        </aside>

        <main class="dashboard-main">
        <div class="dashboard-container">
        <div class="form-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <span class="eyebrow">Academic scheduling</span>
                    <h2>Edit schedule entry</h2>
                    <p style="color: var(--muted);">Update the timetable details and keep programme information accurate.</p>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="edit_schedule.php?id=<?php echo htmlspecialchars($schedule_id); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label for="type">Schedule Category</label>
                    <select name="type" id="type" class="form-control" onchange="toggleDateRequirement()">
                        <option value="Class" <?php echo ($schedule['type'] === 'Class') ? 'selected' : ''; ?>>Regular Class</option>
                        <option value="CA" <?php echo ($schedule['type'] === 'CA') ? 'selected' : ''; ?>>Continuous Assessment (CA)</option>
                        <option value="Exam" <?php echo ($schedule['type'] === 'Exam') ? 'selected' : ''; ?>>Final Exam</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Target Programmes and Study Levels</label>
                    <p class="field-help">Select one or more programmes. Each programme keeps its own assigned year level and semester.</p>
                    <div class="checkbox-grid edit-programme-grid">
                        <?php foreach ($programmes as $prog): ?>
                            <?php $mapping = $courseMappings[(int) $prog['id']] ?? null; ?>
                            <?php $isCurrentProgramme = (int) $schedule['program_id'] === (int) $prog['id']; ?>
                            <?php $displayYear = $mapping['year_level'] ?? ($isCurrentProgramme ? (int) $schedule['year_level'] : 0); ?>
                            <?php $displaySemester = $mapping['semester'] ?? ($isCurrentProgramme ? (int) $schedule['semester'] : 0); ?>
                            <div class="programme-assignment edit-programme-assignment <?php echo ($mapping || $isCurrentProgramme) ? 'is-assigned' : ''; ?>">
                                <label class="checkbox-item">
                                <input type="checkbox" name="program_ids[]" value="<?php echo (int) $prog['id']; ?>" <?php echo ((int) $schedule['program_id'] === (int) $prog['id']) ? 'checked' : ''; ?>>
                                    <span><strong>[<?php echo htmlspecialchars($prog['program_code']); ?>]</strong> <?php echo htmlspecialchars($prog['program_name']); ?></span>
                                </label>
                                <div class="programme-fields">
                                    <select name="year_level[<?php echo (int) $prog['id']; ?>]" aria-label="Year level for <?php echo htmlspecialchars($prog['program_code']); ?>">
                                        <option value="">Year level</option>
                                        <?php for ($level = 1; $level <= 4; $level++): ?>
                                            <option value="<?php echo $level; ?>" <?php echo (int) $displayYear === $level ? 'selected' : ''; ?>>Year <?php echo $level; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select name="semester[<?php echo (int) $prog['id']; ?>]" aria-label="Semester for <?php echo htmlspecialchars($prog['program_code']); ?>">
                                        <option value="">Semester</option>
                                        <option value="1" <?php echo (int) $displaySemester === 1 ? 'selected' : ''; ?>>Sem 1</option>
                                        <option value="2" <?php echo (int) $displaySemester === 2 ? 'selected' : ''; ?>>Sem 2</option>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
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
        </div>
        </main>
    </div>

    <script>
        const scheduleSidebar = document.getElementById('scheduleSidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        sidebarToggle?.addEventListener('click', function () {
            const open = scheduleSidebar.classList.toggle('is-open');
            sidebarToggle.setAttribute('aria-expanded', String(open));
        });
        scheduleSidebar?.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                scheduleSidebar.classList.remove('is-open');
                sidebarToggle?.setAttribute('aria-expanded', 'false');
            });
        });

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
