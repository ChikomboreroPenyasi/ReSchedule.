<?php
session_start();
require_once 'db.php';

// Auth Guard: Admin and Lecturer access only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Administrator', 'Lecturer'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$statusClass = "";
$editingCourse = null;
$editingMappings = [];
$lecturers = [];
$programmes = [];
$courseList = [];
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            $message = 'Your session has expired. Refresh and try again.';
            $statusClass = 'error';
        } else {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $scheduleCountStmt = $pdo->prepare('SELECT COUNT(*) FROM schedules WHERE course_id = :course_id');
            $scheduleCountStmt->execute([':course_id' => $courseId]);
            if ($courseId <= 0) {
                $message = 'Choose a valid course to delete.';
                $statusClass = 'error';
            } elseif ((int) $scheduleCountStmt->fetchColumn() > 0) {
                $message = 'This course has timetable slots. Remove or reassign those slots before deleting the course.';
                $statusClass = 'error';
            } else {
                $pdo->beginTransaction();
                $deleteMappingsStmt = $pdo->prepare('DELETE FROM course_program WHERE course_id = :course_id');
                $deleteMappingsStmt->execute([':course_id' => $courseId]);
                $deleteCourseStmt = $pdo->prepare('DELETE FROM courses WHERE id = :course_id');
                $deleteCourseStmt->execute([':course_id' => $courseId]);
                $pdo->commit();
                $message = $deleteCourseStmt->rowCount() ? 'Course deleted successfully.' : 'Course was not found.';
                $statusClass = $deleteCourseStmt->rowCount() ? 'success' : 'error';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['create_course']) || isset($_POST['update_course']))) {
        $validCsrf = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '');
        if (!$validCsrf) {
            $message = 'Your session has expired. Refresh and try again.';
            $statusClass = 'error';
        }
        $isUpdate = isset($_POST['update_course']);
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
        $course_name = trim($_POST['course_name'] ?? '');
        $lecturer_id = intval($_POST['lecturer_id'] ?? 0);
        $selected_programmes = $_POST['programmes'] ?? []; 
        $year_levels = $_POST['year_level'] ?? [];
        $semesters = $_POST['semester'] ?? [];
        $periodsPerWeek = $_POST['periods_per_week'] ?? [];

        if ($isUpdate && $courseId <= 0) {
            $message = 'Choose a valid course to edit.';
            $statusClass = 'error';
        } elseif (empty($course_code) || empty($course_name)) {
            $message = "Course Code and Course Name are required.";
            $statusClass = "error";
        } elseif (empty($selected_programmes) || !is_array($selected_programmes)) {
            $message = "Select at least one programme for this course.";
            $statusClass = "error";
        } else {
            foreach ($selected_programmes as $prog_id) {
                $prog_id = intval($prog_id);
                $level = intval($year_levels[$prog_id] ?? 0);
                $programSemester = intval($semesters[$prog_id] ?? 0);
                $periodCount = intval($periodsPerWeek[$prog_id] ?? 3);
                if ($level < 1 || $level > 4 || $programSemester < 1 || $programSemester > 2 || $periodCount < 1 || $periodCount > 12) {
                    $message = "Choose a valid year level, semester, and 1 to 12 weekly sessions for every selected programme.";
                    $statusClass = "error";
                    break;
                }
            }
        }

        if (!$validCsrf) {
            $message = 'Your session has expired. Refresh and try again.';
            $statusClass = 'error';
        }
        if ($statusClass !== 'error') {
            $pdo->beginTransaction();
            if ($isUpdate) {
                $saveCourseStmt = $pdo->prepare('UPDATE courses SET course_code = :code, course_name = :name, lecturer_id = :lecturer_id WHERE id = :id');
                $saveCourseStmt->execute([':code' => $course_code, ':name' => $course_name, ':lecturer_id' => $lecturer_id > 0 ? $lecturer_id : null, ':id' => $courseId]);
                $existsStmt = $pdo->prepare('SELECT id FROM courses WHERE id = :id');
                $existsStmt->execute([':id' => $courseId]);
                if (!$existsStmt->fetch()) throw new RuntimeException('Course not found. Refresh and try again.');
                $new_course_id = $courseId;
                $deleteMappingsStmt = $pdo->prepare('DELETE FROM course_program WHERE course_id = :course_id');
                $deleteMappingsStmt->execute([':course_id' => $courseId]);
            } else {
                $insertCourseStmt = $pdo->prepare('INSERT INTO courses (course_code, course_name, lecturer_id) VALUES (:code, :name, :lecturer_id) RETURNING id');
                $insertCourseStmt->execute([':code' => $course_code, ':name' => $course_name, ':lecturer_id' => $lecturer_id > 0 ? $lecturer_id : null]);
                $new_course_id = (int) $insertCourseStmt->fetchColumn();
            }

            $linkStmt = $pdo->prepare('INSERT INTO course_program (course_id, program_id, year_level, semester, periods_per_week) VALUES (:course_id, :program_id, :year_level, :semester, :periods_per_week)');
            foreach ($selected_programmes as $prog_id) {
                $prog_id = (int) $prog_id;
                $linkStmt->execute([':course_id' => $new_course_id, ':program_id' => $prog_id, ':year_level' => (int) $year_levels[$prog_id], ':semester' => (int) $semesters[$prog_id], ':periods_per_week' => (int) ($periodsPerWeek[$prog_id] ?? 3)]);
            }
            $pdo->commit();

            $message = $isUpdate
                ? "Course '" . htmlspecialchars($course_code) . "' updated successfully."
                : "Course '" . htmlspecialchars($course_code) . "' created and assigned successfully!";
            if ($isUpdate) $_GET['edit_course'] = null;
            $statusClass = "success";
        }
    }

    // Fetch Lecturers for Dropdown
    $lecturers = $pdo->query("SELECT id, fullname FROM users WHERE role IN ('Lecturer', 'Administrator') ORDER BY fullname ASC")->fetchAll();

    // Fetch All Programmes
    $programmes = $pdo->query("SELECT id, program_code, program_name, level FROM programmes ORDER BY level ASC, program_code ASC")->fetchAll();

    if (!empty($_GET['edit_course'])) {
        $editCourseStmt = $pdo->prepare('SELECT id, course_code, course_name, lecturer_id FROM courses WHERE id = :id LIMIT 1');
        $editCourseStmt->execute([':id' => (int) $_GET['edit_course']]);
        $editingCourse = $editCourseStmt->fetch();
        if (!$editingCourse) {
            $message = 'Course not found.';
            $statusClass = 'error';
        } else {
            $editMappingsStmt = $pdo->prepare('SELECT program_id, year_level, semester, periods_per_week FROM course_program WHERE course_id = :course_id');
            $editMappingsStmt->execute([':course_id' => (int) $editingCourse['id']]);
            foreach ($editMappingsStmt->fetchAll() as $mapping) {
                $editingMappings[(int) $mapping['program_id']] = ['year_level' => (int) $mapping['year_level'], 'semester' => (int) $mapping['semester'], 'periods_per_week' => (int) $mapping['periods_per_week']];
            }
        }
    }

    // Fetch Existing Courses with assigned Programme Codes and Levels
    $coursesQuery = "
        SELECT c.id, c.course_code, c.course_name, u.fullname AS lecturer_name,
               STRING_AGG(DISTINCT CONCAT(p.program_code, ' (Yr ', cp.year_level, ', Sem ', cp.semester, ')'), ', ' ORDER BY CONCAT(p.program_code, ' (Yr ', cp.year_level, ', Sem ', cp.semester, ')')) AS program_codes
        FROM courses c
        LEFT JOIN users u ON c.lecturer_id = u.id
        LEFT JOIN course_program cp ON c.id = cp.course_id
        LEFT JOIN programmes p ON cp.program_id = p.id
        GROUP BY c.id, c.course_code, c.course_name, u.fullname
        ORDER BY c.course_code ASC
    ";
    $courseList = $pdo->query($coursesQuery)->fetchAll();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = "Database Error: " . $e->getMessage();
    $statusClass = "error";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = $e->getMessage();
    $statusClass = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule - Manage Courses & Programmes</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.5rem;
            max-height: 220px;
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
        .programme-assignment .checkbox-item {
            align-items: flex-start;
            gap: 0.6rem;
            cursor: pointer;
        }
        .programme-assignment-checkbox {
            width: 1.2rem;
            height: 1.2rem;
            flex: 0 0 auto;
            margin: 0.1rem 0 0;
            accent-color: #0879c9;
            cursor: pointer;
        }
        .programme-assignment-copy { display: grid; gap: 0.2rem; }
        .programme-assignment-state {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .programme-assignment-checkbox:checked + .programme-assignment-copy .programme-assignment-state {
            color: #087847;
        }
        .programme-assignment { display: grid; gap: 0.45rem; padding: 0.55rem; border: 1px solid #e2e8f0; border-radius: 5px; background: #ffffff; }
        .unassigned-programme-panel {
            margin-top: 0.65rem;
            padding: 0.75rem;
            border: 1px solid #e9b44c;
            border-radius: 6px;
            background: #fffaf0;
        }
        .unassigned-programme-panel > strong {
            display: block;
            margin-bottom: 0.5rem;
            color: #7a4b00;
            font-size: 0.78rem;
            text-transform: uppercase;
        }
        .unassigned-programme-panel .programme-assignment {
            background: #fff;
            border-color: #edcf91;
        }
        .programme-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.4rem; padding-left: 1.4rem; }
        .programme-fields select { min-width: 0; padding: 0.35rem; border: 1px solid #b8c7d8; border-radius: 4px; font-size: 0.75rem; }
    </style>
</head>
<body class="dashboard-body">

    <nav class="navbar">
        <div class="logo"><img src="assets/reschedule-logo.png" alt="ReSchedule. Campus Timetable System"></div>
        <div class="user-profile">
            <a href="timetable.php" class="card-link">&larr; Go to Timetable</a>
            <a href="dashboard.php" class="card-link">Dashboard</a>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </nav>

    <main class="dashboard-container">
        <header class="welcome-banner">
            <h1>Course & Programme Management</h1>
            <p>Add courses and assign them to degree or diploma programmes and year levels.</p>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert-box <?php echo $statusClass; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Create Course Form -->
        <div class="dash-card" style="margin-bottom: 2rem;">
            <h3><?php echo $editingCourse ? 'Edit Course' : 'Create New Course'; ?></h3>
            <form id="course-form" action="manage_courses.php<?php echo $editingCourse ? '?edit_course=' . (int) $editingCourse['id'] : ''; ?>" method="POST" style="display: grid; gap: 1rem; margin-top: 1rem;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <?php if ($editingCourse): ?><input type="hidden" name="course_id" value="<?php echo (int) $editingCourse['id']; ?>"><?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 2fr 1.5fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label for="course_code">Course Code</label>
                        <input type="text" id="course_code" name="course_code" placeholder="e.g. ICT211" value="<?php echo htmlspecialchars($editingCourse['course_code'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="course_name">Course Title</label>
                        <input type="text" id="course_name" name="course_name" placeholder="e.g. Mobile Application Development" value="<?php echo htmlspecialchars($editingCourse['course_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="lecturer_id">Assigned Lecturer</label>
                        <select id="lecturer_id" name="lecturer_id">
                            <option value="">Select Lecturer...</option>
                            <?php foreach ($lecturers as $lec): ?>
                                <option value="<?php echo $lec['id']; ?>" <?php echo (int) ($editingCourse['lecturer_id'] ?? 0) === (int) $lec['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($lec['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label for="programmeFilter">Course programme assignments</label>
                    <p class="field-help">Checked programmes are assigned to this course. The NOT-ASSIGNED-PROGRAM control is kept below the scrollable list so it is always visible.</p>
                    <input type="search" id="programmeFilter" placeholder="Filter programmes by code or name">
                    <div class="checkbox-grid">
                        <?php foreach ($programmes as $p): ?>
                            <?php if (strtoupper(trim($p['program_code'])) === 'NOT-ASSIGNED-PROGRAM') continue; ?>
                            <div class="programme-assignment" data-programme-search="<?php echo htmlspecialchars(strtolower($p['program_code'] . ' ' . $p['program_name']), ENT_QUOTES); ?>">
                                <label class="checkbox-item">
                                    <input class="programme-assignment-checkbox" type="checkbox" name="programmes[]" value="<?php echo $p['id']; ?>" <?php echo isset($editingMappings[(int) $p['id']]) ? 'checked' : ''; ?>>
                                    <span class="programme-assignment-copy">
                                        <span><strong>Include [<?php echo htmlspecialchars($p['program_code']); ?>]</strong> <?php echo htmlspecialchars($p['program_name']); ?></span>
                                        <span class="programme-assignment-state"><?php echo isset($editingMappings[(int) $p['id']]) ? 'Included' : 'Excluded'; ?></span>
                                    </span>
                                </label>
                                <div class="programme-fields">
                                    <select name="year_level[<?php echo $p['id']; ?>]" aria-label="Year level for <?php echo htmlspecialchars($p['program_code']); ?>">
                                        <?php for ($year = 1; $year <= 4; $year++): ?><option value="<?php echo $year; ?>" <?php echo ($editingMappings[(int) $p['id']]['year_level'] ?? 1) === $year ? 'selected' : ''; ?>>Year <?php echo $year; ?></option><?php endfor; ?>
                                    </select>
                                        <select name="semester[<?php echo $p['id']; ?>]" aria-label="Semester for <?php echo htmlspecialchars($p['program_code']); ?>">
                                        <?php for ($semester = 1; $semester <= 2; $semester++): ?><option value="<?php echo $semester; ?>" <?php echo ($editingMappings[(int) $p['id']]['semester'] ?? 1) === $semester ? 'selected' : ''; ?>>Sem <?php echo $semester; ?></option><?php endfor; ?>
                                    </select>
                                        <select name="periods_per_week[<?php echo $p['id']; ?>]" aria-label="Weekly sessions for <?php echo htmlspecialchars($p['program_code']); ?>">
                                            <?php for ($periodCount = 1; $periodCount <= 12; $periodCount++): ?><option value="<?php echo $periodCount; ?>" <?php echo ($editingMappings[(int) $p['id']]['periods_per_week'] ?? 3) === $periodCount ? 'selected' : ''; ?>><?php echo $periodCount; ?> / week</option><?php endfor; ?>
                                        </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($programmes as $p): ?>
                        <?php if (strtoupper(trim($p['program_code'])) === 'NOT-ASSIGNED-PROGRAM'): ?>
                            <div class="unassigned-programme-panel">
                                <strong>Final assignment option</strong>
                                <div class="programme-assignment" data-programme-search="<?php echo htmlspecialchars(strtolower($p['program_code'] . ' ' . $p['program_name']), ENT_QUOTES); ?>">
                                    <label class="checkbox-item">
                                        <input class="programme-assignment-checkbox" type="checkbox" name="programmes[]" value="<?php echo (int) $p['id']; ?>" <?php echo isset($editingMappings[(int) $p['id']]) ? 'checked' : ''; ?>>
                                        <span class="programme-assignment-copy">
                                            <span><strong>Include [<?php echo htmlspecialchars($p['program_code']); ?>]</strong> <?php echo htmlspecialchars($p['program_name']); ?></span>
                                            <span class="programme-assignment-state"><?php echo isset($editingMappings[(int) $p['id']]) ? 'Included' : 'Excluded'; ?></span>
                                        </span>
                                    </label>
                                    <div class="programme-fields">
                                        <select name="year_level[<?php echo (int) $p['id']; ?>]" aria-label="Year level for <?php echo htmlspecialchars($p['program_code']); ?>">
                                            <?php for ($year = 1; $year <= 4; $year++): ?><option value="<?php echo $year; ?>" <?php echo ($editingMappings[(int) $p['id']]['year_level'] ?? 1) === $year ? 'selected' : ''; ?>>Year <?php echo $year; ?></option><?php endfor; ?>
                                        </select>
                                        <select name="semester[<?php echo (int) $p['id']; ?>]" aria-label="Semester for <?php echo htmlspecialchars($p['program_code']); ?>">
                                            <?php for ($semester = 1; $semester <= 2; $semester++): ?><option value="<?php echo $semester; ?>" <?php echo ($editingMappings[(int) $p['id']]['semester'] ?? 1) === $semester ? 'selected' : ''; ?>>Sem <?php echo $semester; ?></option><?php endfor; ?>
                                        </select>
                                        <select name="periods_per_week[<?php echo (int) $p['id']; ?>]" aria-label="Weekly sessions for <?php echo htmlspecialchars($p['program_code']); ?>">
                                            <?php for ($periodCount = 1; $periodCount <= 12; $periodCount++): ?><option value="<?php echo $periodCount; ?>" <?php echo ($editingMappings[(int) $p['id']]['periods_per_week'] ?? 3) === $periodCount ? 'selected' : ''; ?>><?php echo $periodCount; ?> / week</option><?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php break; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex; gap:.75rem; align-items:center; flex-wrap:wrap;">
                    <button type="submit" name="<?php echo $editingCourse ? 'update_course' : 'create_course'; ?>" class="btn-submit" style="width: 200px;"><?php echo $editingCourse ? 'Update Course' : 'Save Course'; ?></button>
                    <?php if ($editingCourse): ?><a class="filter-reset" href="manage_courses.php">Cancel edit</a><?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Course Directory Table -->
        <div class="dash-card">
            <h3>Registered Courses & Program Assignments</h3>
            
            <?php if (empty($courseList)): ?>
                <p style="color: #64748b; margin-top: 1rem;">No courses created yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto; margin-top: 1rem;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="background-color: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
                                <th style="padding: 0.75rem;">Code</th>
                                <th style="padding: 0.75rem;">Course Title</th>
                                <th style="padding: 0.75rem;">Lecturer</th>
                                <th style="padding: 0.75rem;">Associated Programmes & Levels</th>
                                <th style="padding: 0.75rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courseList as $cl): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 0.75rem; font-weight: 600; color: #2563eb;"><?php echo htmlspecialchars($cl['course_code']); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($cl['course_name']); ?></td>
                                    <td style="padding: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($cl['lecturer_name'] ?? 'Unassigned'); ?></td>
                                    <td style="padding: 0.75rem;">
                                        <span style="background: #f1f5f9; color: #475569; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.82rem; font-weight: 500;">
                                            <?php echo htmlspecialchars($cl['program_codes'] ?? 'Unassigned'); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem; white-space: nowrap;">
                                        <a class="schedule-edit-link" href="manage_courses.php?edit_course=<?php echo (int) $cl['id']; ?>#course-form">Edit</a>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this course? Courses with scheduled slots cannot be deleted.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="course_id" value="<?php echo (int) $cl['id']; ?>">
                                            <button type="submit" name="delete_course" class="btn-delete">Delete</button>
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
    document.querySelectorAll('.programme-assignment-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const state = this.closest('.checkbox-item')?.querySelector('.programme-assignment-state');
            if (state) state.textContent = this.checked ? 'Included' : 'Excluded';
        });
    });

    document.getElementById('programmeFilter')?.addEventListener('input', function () {
        const query = this.value.trim().toLowerCase();
        document.querySelectorAll('.programme-assignment[data-programme-search]').forEach(function (assignment) {
            assignment.hidden = !assignment.dataset.programmeSearch.includes(query);
        });
    });
</script>
</body>
</html>
