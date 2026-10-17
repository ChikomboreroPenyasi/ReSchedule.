# ReScheduleWeb

## Temporary free deployment (Render + Supabase)

The site runs on Render's free Docker web service and connects directly to Supabase PostgreSQL. Free Render services sleep after inactivity, so the first page load after sleeping can take about a minute. This setup is for a demo, not an official or reliability-sensitive deployment.

1. In Supabase, import the application schema and data from `rescheduledb_supabase.sql`. Then open **SQL Editor** and run [`database/supabase_migration.sql`](./database/supabase_migration.sql). This migration adapts the imported legacy table names (for example, `programs` and `course_programs`) to the names used by the PHP app (for example, `programmes` and `course_program`).
2. If you import or re-import the database dump after running the migration, run the migration again afterward. It is designed to be re-runnable and does not replace the imported data. Do not re-import the dump just to fix a missing app table; rerun the migration instead.
3. If a later app update changes the migration, run the updated migration in Supabase SQL Editor as well.
4. Push this repository to GitHub, then in Render choose **New > Blueprint** and select the repository. Render reads [`render.yaml`](./render.yaml) and builds the Docker service.
5. In Render, set `SUPABASE_DB_URL` to the PostgreSQL connection URI from Supabase. Use the **Session pooler** connection string if the direct database host is not reachable from Render. Keep `sslmode=require`; do not commit the URI or put it in source files.
6. Deploy the service. Render will provide a `*.onrender.com` URL.
7. To enable AI timetable import, also set `GEMINI_API_KEY` in the Render service environment. `GEMINI_MODEL` is optional and defaults to `gemini-2.5-flash`.

The repository includes a Dockerfile that installs PDO PostgreSQL and configures Apache to listen on Render's assigned port. Supabase is persistent storage; Render's local filesystem is not persistent. Uploaded files and other generated local files must not be treated as durable.

## Local XAMPP

Run Apache from `C:\xampp\htdocs\ReScheduleWeb` and enable `extension=pdo_pgsql` in `C:\xampp\php\php.ini`. Set `SUPABASE_DB_URL` in the Apache/PHP environment to the same PostgreSQL connection URI. The app no longer supports MySQL/MariaDB.

## AI timetable import

The importer uses Gemini only. Create an API key in Google AI Studio and set `GEMINI_API_KEY` in the PHP/Apache server environment. The default model is `gemini-2.5-flash`; set `GEMINI_MODEL` to use another available Gemini model.

An NVIDIA key was previously present in a local diagnostic file. Revoke/rotate it if you have not already done so.

## Timetable generation and substitute coverage

Administrator dashboard links provide draft timetable generation and publishing. Set each course's weekly session count under its programme assignment in Manage Courses before generating. Drafts are reviewed before publishing and use the programme, year, semester, lecturer, and room constraints.

Administrators and lecturers can open Substitute Coverage to report lecturer absences; administrators can assign coverage. The Supabase migration prepares the support tables, sequence defaults, schedule lecturer override, and course session-count column.