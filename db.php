<?php
// XAMPP MySQL/MariaDB connection.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'reschedule';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '3306';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $scheduleColumns = $pdo->query('SHOW COLUMNS FROM schedules')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('year_level', $scheduleColumns, true)) {
        $pdo->exec('ALTER TABLE schedules ADD COLUMN year_level INT NULL DEFAULT 1');
    }
    if (!in_array('semester', $scheduleColumns, true)) {
        $pdo->exec('ALTER TABLE schedules ADD COLUMN semester INT NULL DEFAULT 1');
    }
    if (!in_array('lecturer_id', $scheduleColumns, true)) {
        $pdo->exec('ALTER TABLE schedules ADD COLUMN lecturer_id INT NULL');
    }

    $courseProgramColumns = $pdo->query('SHOW COLUMNS FROM course_program')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('periods_per_week', $courseProgramColumns, true)) {
        $pdo->exec('ALTER TABLE course_program ADD COLUMN periods_per_week INT NOT NULL DEFAULT 3');
    }

    $requestColumns = $pdo->query('SHOW COLUMNS FROM reschedule_requests')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('review_note', $requestColumns, true)) {
        $pdo->exec('ALTER TABLE reschedule_requests ADD COLUMN review_note TEXT NULL');
    }
    if (!in_array('reviewed_at', $requestColumns, true)) {
        $pdo->exec('ALTER TABLE reschedule_requests ADD COLUMN reviewed_at DATETIME NULL');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(180) NOT NULL,
        description TEXT NULL,
        event_type VARCHAR(40) NOT NULL DEFAULT 'Other',
        start_datetime DATETIME NOT NULL,
        end_datetime DATETIME NULL,
        is_all_day TINYINT(1) NOT NULL DEFAULT 0,
        venue VARCHAR(180) NULL,
        visibility VARCHAR(30) NOT NULL DEFAULT 'Everyone',
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_events_date (start_datetime, end_datetime),
        KEY idx_events_visibility (visibility),
        KEY idx_events_type (event_type),
        KEY idx_events_creator (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $eventColumns = $pdo->query('SHOW COLUMNS FROM events')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('description', $eventColumns, true)) {
        $pdo->exec('ALTER TABLE events ADD COLUMN description TEXT NULL AFTER title');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS course_lecturers (
        course_id INT NOT NULL,
        lecturer_id INT NOT NULL,
        PRIMARY KEY (course_id, lecturer_id),
        KEY idx_course_lecturers_lecturer (lecturer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS generated_timetables (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        program_id INT NOT NULL,
        year_level INT NOT NULL,
        semester INT NOT NULL,
        generated_by INT NOT NULL,
        placed_count INT NOT NULL DEFAULT 0,
        total_count INT NOT NULL DEFAULT 0,
        quality_score INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_generated_timetables_scope (program_id, year_level, semester),
        KEY idx_generated_timetables_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS lecturer_unavailability (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        lecturer_id INT NOT NULL,
        unavailable_date DATE NOT NULL,
        reason VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_lecturer_unavailability_date (lecturer_id, unavailable_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS substitutions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        schedule_id INT NOT NULL,
        absence_date DATE NOT NULL,
        original_lecturer_id INT NOT NULL,
        substitute_lecturer_id INT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_substitution_schedule_date (schedule_id, absence_date),
        KEY idx_substitutions_status_date (status, absence_date),
        KEY idx_substitutions_original_lecturer (original_lecturer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}
?>
