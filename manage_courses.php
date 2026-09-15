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

try {
    // Handle Course Creation & Programme/Level Mapping
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_course'])) {
        $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
        $course_name = trim($_POST['course_name'] ?? '');
        $lecturer_id = intval($_POST['lecturer_id'] ?? 0);
        $selected_programmes = $_POST['programmes'] ?? []; 
        $year_levels = $_POST['year_level'] ?? [];
        $semesters = $_POST['semester'] ?? [];

        if (empty($course_code) || empty($course_name)) {
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
                if ($level < 1 || $level > 4 || $programSemester < 1 || $programSemester > 2) {
                    $message = "Choose a valid year level and semester for every selected programme.";
                    $statusClass = "error";
                    break;
                }
            }
        }

        if ($statusClass !== 'error') {
            // 1. Insert Course
            $insertCourseStmt = $pdo->prepare("
                INSERT INTO courses (course_code, course_name, lecturer_id) 
                VALUES (:code, :name, :lecturer_id)
            ");
            $insertCourseStmt->execute([
                ':code' => $course_code,
                ':name' => $course_name,
                ':lecturer_id' => ($lecturer_id > 0) ? $lecturer_id : null
            ]);
            $new_course_id = $pdo->lastInsertId();

            // 2. Link Course to Selected Programmes WITH Year Level & Semester
            if (!empty($selected_programmes) && is_array($selected_programmes)) {
                $linkStmt = $pdo->prepare("
                    INSERT INTO course_program (course_id, program_id, year_level, semester) 
                    VALUES (:course_id, :program_id, :year_level, :semester)
                ");
                foreach ($selected_programmes as $prog_id) {
                    $prog_id = intval($prog_id);
                    $linkStmt->execute([
                        ':course_id'  => $new_course_id,
                        ':program_id' => $prog_id,
                        ':year_level' => intval($year_levels[$prog_id]),
                        ':semester'   => intval($semesters[$prog_id])
                    ]);
                }
            }

            $message = "Course '" . htmlspecialchars($course_code) . "' created and assigned successfully!";
            $statusClass = "success";
        }
    }

    // Fetch Lecturers for Dropdown
    $lecturers = $pdo->query("SELECT id, fullname FROM users WHERE role IN ('Lecturer', 'Administrator') ORDER BY fullname ASC")->fetchAll();

    // Fetch All Programmes
    $programmes = $pdo->query("SELECT id, program_code, program_name, level FROM programmes ORDER BY level ASC, program_code ASC")->fetchAll();

    // Fetch Existing Courses with assigned Programme Codes and Levels
    $coursesQuery = "
        SELECT c.id, c.course_code, c.course_name, u.fullname AS lecturer_name,
               GROUP_CONCAT(DISTINCT CONCAT(p.program_code, ' (Yr ', cp.year_level, ', Sem ', cp.semester, ')') ORDER BY p.program_code SEPARATOR ', ') AS program_codes
        FROM courses c
        LEFT JOIN users u ON c.lecturer_id = u.id
        LEFT JOIN course_program cp ON c.id = cp.course_id
        LEFT JOIN programmes p ON cp.program_id = p.id
        GROUP BY c.id, c.course_code, c.course_name, u.fullname
        ORDER BY c.course_code ASC
    ";
    $courseList = $pdo->query($coursesQuery)->fetchAll();

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
        .programme-assignment { display: grid; gap: 0.45rem; padding: 0.55rem; border: 1px solid #e2e8f0; border-radius: 5px; background: #ffffff; }
        .programme-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; padding-left: 1.4rem; }
        .programme-fields select { min-width: 0; padding: 0.35rem; border: 1px solid #b8c7d8; border-radius: 4px; font-size: 0.75rem; }
    </style>
</head>
<body class="dashboard-body">

    <nav class="navbar">
        <div class="logo">Reschedule<span>.</span></div>
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
            <h3>Create New Course</h3>
            <form action="manage_courses.php" method="POST" style="display: grid; gap: 1rem; margin-top: 1rem;">
                
                <div style="display: grid; grid-template-columns: 1fr 2fr 1.5fr; gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label for="course_code">Course Code</label>
                        <input type="text" id="course_code" name="course_code" placeholder="e.g. ICT211" required>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="course_name">Course Title</label>
                        <input type="text" id="course_name" name="course_name" placeholder="e.g. Mobile Application Development" required>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="lecturer_id">Assigned Lecturer</label>
                        <select id="lecturer_id" name="lecturer_id">
                            <option value="">Select Lecturer...</option>
                            <?php foreach ($lecturers as $lec): ?>
                                <option value="<?php echo $lec['id']; ?>"><?php echo htmlspecialchars($lec['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label>Select Offering Programmes and Assign Level/Semester:</label>
                    <div class="checkbox-grid">
                        <?php foreach ($programmes as $p): ?>
                            <div class="programme-assignment">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="programmes[]" value="<?php echo $p['id']; ?>">
                                    <span><strong>[<?php echo htmlspecialchars($p['program_code']); ?>]</strong> <?php echo htmlspecialchars($p['program_name']); ?></span>
                                </label>
                                <div class="programme-fields">
                                    <select name="year_level[<?php echo $p['id']; ?>]" aria-label="Year level for <?php echo htmlspecialchars($p['program_code']); ?>">
                                        <option value="1">Year 1</option><option value="2">Year 2</option><option value="3">Year 3</option><option value="4">Year 4</option>
                                    </select>
                                    <select name="semester[<?php echo $p['id']; ?>]" aria-label="Semester for <?php echo htmlspecialchars($p['program_code']); ?>">
                                        <option value="1">Sem 1</option><option value="2">Sem 2</option>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" name="create_course" class="btn-submit" style="width: 200px;">Save Course</button>
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
