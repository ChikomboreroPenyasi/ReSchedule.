# ReScheduleWeb

ReScheduleWeb is a university scheduling platform designed to help students, lecturers, and administrators manage classes, exams, reschedules, room usage, and timetable updates in a single system.

The project combines a PHP web application and a mobile companion app for students. It is built for academic environments where timetable accuracy, communication, and quick updates matter.

## What the project does

ReScheduleWeb helps a university or college:

- manage programs, courses, rooms, lecturers, and users
- build and publish class timetables
- display student and lecturer schedules clearly
- handle reschedule requests from students and staff
- track lecturer absences and substitute coverage
- manage academic events and announcements
- import timetable data using an AI-assisted workflow
- provide a student Android app for quick timetable access

## Main features

### 1. Role-based scheduling system

The platform supports multiple roles:

- Students can view their timetable, assessments, and exam dates
- Lecturers can manage teaching schedules and cover absences
- Administrators can control courses, rooms, users, and published schedules

### 2. Student timetable and dashboard

Students can log in and access:

- weekly class timetable
- CA and exam schedules
- next class and next assessment reminders
- personal reschedule request history
- notifications and schedule updates

### 3. Reschedule request management

Students can submit a reschedule request with a reason and preferred time. Administrators and lecturers can review and act on these requests through the system.

This reduces the need for manual communication and helps keep requests visible and traceable.

### 4. Timetable generation and publishing

Administrators can generate draft timetables based on:

- programme assignments
- year level and semester
- lecturer availability
- room constraints
- course session counts

Once the draft is validated, it can be published to the wider university community.

### 5. Lecturer coverage and substitutions

The system supports lecturer unavailability and substitute assignment workflows. If a lecturer is absent, the platform helps track the issue and assign coverage.

### 6. Course and resource management

Administrators can manage:

- courses and programme mappings
- rooms and resources
- user accounts
- events and announcements
- academic schedules

### 7. Event and announcement support

The platform includes support for campus events and announcements so updates are not only tied to class timetables but also to important academic and institutional dates.

### 8. AI-assisted timetable import

The application can import timetable data using Gemini. This reduces manual entry and speeds up the setup of course and scheduling information.

### 9. Android student companion app

A separate Android app is included for students. It connects to the same web backend over HTTP and allows students to:

- log in
- view timetable data
- view assessments
- submit reschedule requests
- check notifications and events
- access the signup page

## Project structure

- `index.php` – public landing page for the platform
- `login.php` and `signup.php` – authentication and account creation
- `dashboard.php` – main role-based dashboard
- `manage_courses.php` – course and programme configuration
- `manage_users.php` – user management
- `manage_events.php` – event editing
- `manage_reschedules.php` – reschedule review
- `manage_resources.php` – room/resource administration
- `publish_schedule.php` and `generate_timetable.php` – timetable publishing and generation workflow
- `api/` – backend API endpoints used by web and mobile clients
- `student-android/` – Android Kotlin project for student access
- `db.php` – MySQL connection and automatic schema setup

## Technology stack

- PHP
- MySQL / MariaDB
- PDO database layer
- XAMPP for local development
- Android app using Kotlin and Gradle
- Gemini API for AI import support

## How it works

1. The administrator sets up programmes, courses, rooms, users, and lecturers.
2. Course and programme details are mapped for the academic year and semester.
3. The system generates or imports the timetable.
4. Students view their schedule in the web portal or Android app.
5. Students submit reschedule requests when needed.
6. Lecturers and administrators review those requests and resolve scheduling issues.
7. Final schedules are published for campus-wide access.

## Local setup with XAMPP

1. Place the project in `C:\xampp\htdocs\ReScheduleWeb`.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/phpmyadmin`.
4. Create a database named `reschedule`.
5. Make sure `pdo_mysql` is enabled in `C:\xampp\php\php.ini`.
6. Configure your local database connection using the default values or environment variables:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
   - `DB_PORT`
7. Open `http://localhost/ReScheduleWeb/` in your browser.

## AI import setup

The timetable import uses Gemini.

To enable AI import, set the following environment variables in the PHP server environment:

- `GEMINI_API_KEY`
- `GEMINI_MODEL` (optional, default is `gemini-2.5-flash`)

## Android app setup

The mobile app is located in the `student-android` folder.

To run it:

1. Start Apache and MySQL in XAMPP.
2. Make sure the web app is available on your local network.
3. Open the Android project in Android Studio.
4. Sync Gradle and run the app on an emulator or Android device.
5. Ensure the device and host machine are on the same Wi-Fi network.

The Android app connects to the web backend endpoints instead of directly connecting to MySQL, which keeps database credentials out of the app.

## Typical users

- Students: check timetable, assessments, notifications, and request reschedules
- Lecturers: review teaching schedules, manage absences, and support coverage
- Administrators: manage academic structure, timetable generation, and final publication

## Summary

ReScheduleWeb is a complete academic scheduling solution for higher education institutions. It combines timetable management, scheduling logic, student communication, admin controls, and mobile access in one practical system aimed at reducing scheduling conflicts and improving the student experience.

---

If you want, I can also turn this into a more polished version with screenshots, badges, a screenshot section, and a shorter GitHub-style profile README.