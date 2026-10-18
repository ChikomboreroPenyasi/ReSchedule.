<?php

function recordAssessmentClassExceptions(PDO $pdo, int $assessmentId): int
{
    $stmt = $pdo->prepare("
        INSERT INTO schedule_exceptions
            (schedule_id, occurrence_date, source_type, source_schedule_id)
        SELECT class_schedule.id, assessment.date, 'schedule', assessment.id
        FROM schedules assessment
        JOIN schedules class_schedule
          ON class_schedule.type = 'Class'
         AND LOWER(class_schedule.day_of_week) = LOWER(TO_CHAR(assessment.date, 'FMDay'))
         AND (class_schedule.date IS NULL OR class_schedule.date = assessment.date)
         AND class_schedule.start_time < assessment.end_time
         AND class_schedule.end_time > assessment.start_time
         AND class_schedule.program_id = assessment.program_id
         AND class_schedule.year_level = assessment.year_level
         AND class_schedule.semester = assessment.semester
        WHERE assessment.id = :assessment_id
          AND assessment.type IN ('CA', 'Exam')
          AND assessment.date IS NOT NULL
        ON CONFLICT DO NOTHING
    ");
    $stmt->execute([':assessment_id' => $assessmentId]);
    return $stmt->rowCount();
}

function recordEventClassExceptions(PDO $pdo, int $eventId): int
{
    $stmt = $pdo->prepare("
        INSERT INTO schedule_exceptions
            (schedule_id, occurrence_date, source_type, source_event_id)
        SELECT class_schedule.id, occurrence.day::date, 'event', event.id
        FROM events event
        CROSS JOIN LATERAL generate_series(
            event.start_datetime::date,
            event.end_datetime::date,
            INTERVAL '1 day'
        ) AS occurrence(day)
        JOIN schedules class_schedule
          ON class_schedule.type = 'Class'
         AND LOWER(class_schedule.day_of_week) = LOWER(TO_CHAR(occurrence.day::date, 'FMDay'))
         AND (class_schedule.date IS NULL OR class_schedule.date = occurrence.day::date)
         AND (occurrence.day::date + class_schedule.start_time) < event.end_datetime
         AND (occurrence.day::date + class_schedule.end_time) > event.start_datetime
        WHERE event.id = :event_id
          AND event.visibility IN ('Everyone', 'Students')
        ON CONFLICT DO NOTHING
    ");
    $stmt->execute([':event_id' => $eventId]);
    return $stmt->rowCount();
}

function refreshClassExceptions(PDO $pdo, int $classScheduleId): int
{
    $clearStmt = $pdo->prepare('DELETE FROM schedule_exceptions WHERE schedule_id = :schedule_id');
    $clearStmt->execute([':schedule_id' => $classScheduleId]);
    $exceptionCount = 0;

    $assessmentStmt = $pdo->prepare("
        INSERT INTO schedule_exceptions
            (schedule_id, occurrence_date, source_type, source_schedule_id)
        SELECT class_schedule.id, assessment.date, 'schedule', assessment.id
        FROM schedules class_schedule
        JOIN schedules assessment
          ON assessment.type IN ('CA', 'Exam')
         AND assessment.date IS NOT NULL
         AND LOWER(class_schedule.day_of_week) = LOWER(TO_CHAR(assessment.date, 'FMDay'))
         AND (class_schedule.date IS NULL OR class_schedule.date = assessment.date)
         AND class_schedule.start_time < assessment.end_time
         AND class_schedule.end_time > assessment.start_time
         AND class_schedule.program_id = assessment.program_id
         AND class_schedule.year_level = assessment.year_level
         AND class_schedule.semester = assessment.semester
        WHERE class_schedule.id = :schedule_id
          AND class_schedule.type = 'Class'
        ON CONFLICT DO NOTHING
    ");
    $assessmentStmt->execute([':schedule_id' => $classScheduleId]);
    $exceptionCount += $assessmentStmt->rowCount();

    $eventStmt = $pdo->prepare("
        INSERT INTO schedule_exceptions
            (schedule_id, occurrence_date, source_type, source_event_id)
        SELECT class_schedule.id, occurrence.day::date, 'event', event.id
        FROM schedules class_schedule
        JOIN events event
          ON event.visibility IN ('Everyone', 'Students')
        CROSS JOIN LATERAL generate_series(
            event.start_datetime::date,
            event.end_datetime::date,
            INTERVAL '1 day'
        ) AS occurrence(day)
        WHERE class_schedule.id = :schedule_id
          AND class_schedule.type = 'Class'
          AND LOWER(class_schedule.day_of_week) = LOWER(TO_CHAR(occurrence.day::date, 'FMDay'))
          AND (class_schedule.date IS NULL OR class_schedule.date = occurrence.day::date)
          AND (occurrence.day::date + class_schedule.start_time) < event.end_datetime
          AND (occurrence.day::date + class_schedule.end_time) > event.start_datetime
        ON CONFLICT DO NOTHING
    ");
    $eventStmt->execute([':schedule_id' => $classScheduleId]);
    $exceptionCount += $eventStmt->rowCount();

    return $exceptionCount;
}
