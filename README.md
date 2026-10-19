# ReSchedule

ReSchedule is a university timetable and academic scheduling portal with a PHP web application, a Supabase PostgreSQL database, and a student Android client. It supports administrator, lecturer, and student workflows for managing weekly classes, dated assessments, campus events, schedule changes, resources, and lecturer cover.

## Project author

- **Name:** Chikomborero A. Peniyasi
- **Student ID:** 2024061326

The author attribution is shown in the signed-in web dashboard and documented here.

## Main features

### Accounts, roles, and student profiles

- Users sign in with their account credentials; students can sign in with their student number.
- Administrators manage student and lecturer accounts, including programme and academic year/semester details.
- Administrators and lecturers have schedule-management tools. Administrator-only tools include bulk timetable generation, document import, and user management.
- Students see schedules for their programme and academic cohort and can submit reschedule requests.
- The site uses PHP sessions for web authentication. The Android app uses the website's login API and keeps a local student session.

### Weekly timetable and schedule publishing

- The master timetable shows class slots by weekday, time, course, lecturer, and room.
- Administrators and lecturers can publish or edit class, CA, and exam slots for programmes assigned to a course.
- Course-to-programme mappings provide the year level and semester for each selected programme.
- The form checks cohort, room, and lecturer conflicts before publishing regular classes. Dated CA/exam entries require a date and are checked against conflicting assessments and room use. A regular class in the assessment cohort's slot is allowed because that occurrence is replaced for that date; the lecturer's regular teaching schedule does not block an assessment.
- Weekly class entries remain recurring. A date on a schedule row represents a one-off entry.

### Date-specific class replacements

- A dated CA or exam replaces only overlapping regular class occurrences for the same programme, year, and semester on that date.
- A shared event visible to students replaces overlapping class occurrences on each applicable event date. Staff-only events do not suppress student classes.
- Replacements are stored as schedule exceptions rather than by deleting the recurring class. The original weekly class remains scheduled for other dates.
- Deleting the assessment or event removes its linked exceptions. The dashboard calendar suppresses only the affected occurrence.
- The schedule API returns upcoming class exceptions for the student Android app.

### Timetable importer

The administrator-only **Timetable importer** accepts PDF, CSV, TXT, PNG, and JPG documents. It extracts rows into a reviewable preview; administrators can correct dates before confirming the import. The importer creates or reuses programme, course, room, and course-programme records, then publishes the schedule rows in a database transaction.

Gemini is used to interpret uploaded documents. Searchable PDFs are first converted to text locally with Poppler; scanned PDFs and image, CSV, and text inputs are sent to Gemini. The importer supports CA/exam types and one-off dates when present or entered in the preview. Set `GEMINI_API_KEY` on the server; `GEMINI_MODEL` is optional and defaults to `gemini-2.5-flash`.

### Timetable generation

Administrators can generate a weekly draft for a selected programme, year, and semester. Course assignment session counts determine how many class slots are needed. The generator tries multiple candidate placements while checking cohort, room, and lecturer availability, then presents a draft for review. Only a complete draft can be published. Administrators can optionally replace the existing weekly class entries for the selected cohort.

### Courses and campus resources

- Administrators and lecturers maintain courses, assign them to programmes and academic cohorts, and configure weekly session counts and lecturer assignments.
- Room and resource management provides the halls/labs and capacity information used by schedule planning.
- Students can view their timetable and the programme master timetable, including room allocations.

### Events and academic calendar

- Administrators and lecturers create and delete campus events with event type, description, venue, date/time range, all-day setting, and visibility.
- Student-facing events appear in the dashboard calendar and Android events feed.
- Events visible to students can create date-specific class replacement exceptions when their time overlaps scheduled classes.

### Reschedule requests

- Students submit a request for a class or assessment with a reason and optional preferred time.
- Students can see request status and review notes in the dashboard and Android request history.
- Administrators and lecturers review requests and approve or reject them, with review notes.

### Substitute lecturer coverage

- Lecturers report a date of unavailability; administrators can also report an absence for a lecturer.
- The system identifies classes taught by that lecturer on the weekday and creates pending coverage records.
- Administrators can assign a substitute lecturer or remove a coverage record. Candidate suggestions consider programme familiarity, daily schedule load, time clashes, and reported unavailability.

### Student Android app

The Android app is a student-facing client of the same PHP APIs and Supabase-backed data. It provides login, a dashboard, weekly timetable, dated assessments, class-replacement notices, master timetable, reschedule request history/submission, student-facing events, profile, notifications, and campus hub navigation. Assessment cards show their scheduled date and time.

The Android client does **not** connect directly to Supabase and contains no database credentials. It talks to the website over HTTPS; the server talks to PostgreSQL. Install a newly built APK to receive Android UI or data-model changes.

### API status and current scope

The Android-facing PHP APIs include login, schedules, requests, events, announcements, courses, programmes, assignments, attendance, and grades. Some are compatibility/demo endpoints: the assignments, attendance, and grades APIs currently return placeholder/empty data rather than integrated academic records. The Android UI may therefore show empty or demo content for those areas until those services are implemented.

## Technology

- PHP 8 with PDO and Apache
- Supabase PostgreSQL
- Render Docker web service
- Kotlin, Jetpack Compose, Retrofit, and Room for Android
- Gemini API and Poppler for timetable document interpretation

## Setup and deployment

### Supabase database

1. Import the database schema and data from `rescheduledb_supabase.sql` into Supabase.
2. Run [`database/supabase_migration.sql`](./database/supabase_migration.sql) in the Supabase SQL Editor. It adapts legacy table names and prepares application columns, defaults, indexes, substitute-coverage support, and schedule exceptions.
3. If you later import or re-import the database dump, run the migration again. The migration is designed to be re-runnable and does not replace imported data.
4. Run any future updated migration supplied with an application release.

### Render deployment

1. Push the repository to GitHub and create a Render Blueprint from it. Render uses [`render.yaml`](./render.yaml) and the repository's Dockerfile.
2. Set `SUPABASE_DB_URL` in the Render service environment to the PostgreSQL connection URI from Supabase. Use the Supabase session pooler if the direct host is unreachable; retain `sslmode=require`.
3. Set `GEMINI_API_KEY` as a secret environment variable to enable timetable document interpretation. Optionally set `GEMINI_MODEL`.
4. Deploy the service and use its `*.onrender.com` URL.

The free Render service may sleep while inactive, so its first response can be delayed. Its local filesystem is not durable; do not rely on it for persistent uploads or generated files. This hosting configuration is intended for demonstrations, not reliability-sensitive production use.

### Local XAMPP

Run Apache from the repository folder, enable `pdo_pgsql` in `C:\xampp\php\php.ini`, and configure `SUPABASE_DB_URL` in the Apache/PHP environment. The application uses Supabase PostgreSQL; it does not support MySQL/MariaDB.

### Android build

Open `student-android` in Android Studio, sync Gradle, then run the `app` configuration on a device/emulator or build the debug APK. The deployed website base URL and signup URL are set in `student-android/app/build.gradle.kts`. For local testing, change these to a host reachable from the device.

## Important configuration and security notes

- Never commit database connection strings, Gemini keys, or other credentials. Configure them as server environment variables.
- Use HTTPS for deployed web and Android API traffic.
- Use a dedicated, rotated Gemini API key. Revoke any key that was previously exposed.
- The student ID in the author attribution is intentionally visible on the signed-in dashboard and in this project documentation, as requested by the author.
