BEGIN;

DO $$
BEGIN
    IF to_regclass('public.programmes') IS NULL AND to_regclass('public.programs') IS NOT NULL THEN
        ALTER TABLE public.programs RENAME TO programmes;
    END IF;
    IF to_regclass('public.course_program') IS NULL AND to_regclass('public.course_programs') IS NOT NULL THEN
        ALTER TABLE public.course_programs RENAME TO course_program;
    END IF;
    IF to_regclass('public.events') IS NULL AND to_regclass('public.calendar_events') IS NOT NULL THEN
        ALTER TABLE public.calendar_events RENAME TO events;
    END IF;
    IF to_regclass('public.generated_timetables') IS NULL AND to_regclass('public.schedule_generations') IS NOT NULL THEN
        ALTER TABLE public.schedule_generations RENAME TO generated_timetables;
    END IF;
    IF to_regclass('public.reschedule_requests') IS NULL AND to_regclass('public.schedule_change_requests') IS NOT NULL THEN
        ALTER TABLE public.schedule_change_requests RENAME TO reschedule_requests;
    END IF;
    IF to_regclass('public.substitutions') IS NULL AND to_regclass('public.lecturer_absences') IS NOT NULL THEN
        ALTER TABLE public.lecturer_absences RENAME TO substitutions;
    END IF;
END
$$;

ALTER TABLE public.course_program
    ADD COLUMN IF NOT EXISTS periods_per_week BIGINT NOT NULL DEFAULT 3;
ALTER TABLE public.schedules
    ADD COLUMN IF NOT EXISTS lecturer_id BIGINT;
ALTER TABLE public.events
    ADD COLUMN IF NOT EXISTS event_type TEXT NOT NULL DEFAULT 'Other',
    ADD COLUMN IF NOT EXISTS description TEXT,
    ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP,
    ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.generated_timetables
    ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.lecturer_unavailability
    ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.reschedule_requests
    ADD COLUMN IF NOT EXISTS review_note TEXT,
    ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMPTZ,
    ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE public.substitutions
    ADD COLUMN IF NOT EXISTS notes TEXT,
    ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP,
    ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP;

DO $$
BEGIN
    IF (SELECT data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'events' AND column_name = 'created_by') = 'text' THEN
        ALTER TABLE public.events ALTER COLUMN created_by TYPE BIGINT USING NULLIF(created_by, '')::BIGINT;
    END IF;
    IF (SELECT data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'generated_timetables' AND column_name = 'generated_by') = 'text' THEN
        ALTER TABLE public.generated_timetables ALTER COLUMN generated_by TYPE BIGINT USING NULLIF(generated_by, '')::BIGINT;
    END IF;
    IF (SELECT data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'substitutions' AND column_name = 'created_by') = 'text' THEN
        ALTER TABLE public.substitutions ALTER COLUMN created_by TYPE BIGINT USING NULLIF(created_by, '')::BIGINT;
    END IF;
END
$$;

DO $$
DECLARE
    table_name TEXT;
    sequence_name TEXT;
BEGIN
    FOREACH table_name IN ARRAY ARRAY[
        'users', 'programmes', 'courses', 'rooms', 'schedules',
        'course_lecturers', 'events', 'generated_timetables',
        'lecturer_unavailability', 'reschedule_requests', 'substitutions'
    ] LOOP
        IF to_regclass(format('public.%I', table_name)) IS NOT NULL THEN
            IF NOT EXISTS (
                SELECT 1
                FROM pg_constraint
                WHERE conrelid = to_regclass(format('public.%I', table_name))
                  AND contype = 'p'
            ) THEN
                EXECUTE format('ALTER TABLE public.%I ALTER COLUMN id SET NOT NULL', table_name);
                EXECUTE format('ALTER TABLE public.%I ADD CONSTRAINT %I PRIMARY KEY (id)', table_name, table_name || '_pkey');
            END IF;

            sequence_name := table_name || '_id_seq';
            EXECUTE format('CREATE SEQUENCE IF NOT EXISTS public.%I', sequence_name);
            EXECUTE format(
                'ALTER TABLE public.%I ALTER COLUMN id SET DEFAULT nextval(%L)',
                table_name,
                'public.' || sequence_name
            );
            EXECUTE format('ALTER SEQUENCE public.%I OWNED BY public.%I.id', sequence_name, table_name);
            EXECUTE format(
                'SELECT setval(%L, COALESCE(MAX(id), 0) + 1, false) FROM public.%I',
                'public.' || sequence_name,
                table_name
            );
        END IF;
    END LOOP;
END
$$;

CREATE UNIQUE INDEX IF NOT EXISTS substitutions_schedule_absence_key
    ON public.substitutions (schedule_id, absence_date);

COMMIT;
