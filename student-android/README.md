# ReSchedule. Student Android App

This is the student-only Android client for the existing PHP/XAMPP website.

## Features

- Student number and password login
- Native splash screen
- Staff accounts are rejected by the mobile API
- Persistent student session
- Student timetable and assessment loading
- Student dashboard summary
- Timetable and assessment navigation
- Reschedule request submission and history
- Student notifications and shared events
- In-app navigation between dashboard, timetable, requests, and events
- Logout
- Signup opens the existing website signup form

## Run with XAMPP and Android device

1. Start Apache and MySQL in XAMPP.
2. Confirm the website opens at `http://192.168.1.186/ReScheduleWeb/` on the phone or emulator browser.
3. Open this `student-android` folder in Android Studio.
4. Sync Gradle and run the `app` configuration on an emulator.
5. The app connects to `http://192.168.1.186/ReScheduleWeb/` for login, timetable data, and signup.

The phone and computer must be on the same Wi-Fi network, and Windows Firewall must allow Apache traffic on port 80.

## Database connection

The Android app does not connect directly to MySQL. It connects to `api_login.php` and `api_schedules.php` over HTTP. Those PHP endpoints load `db.php`, which connects to the XAMPP MySQL database using the existing `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` settings. This keeps database credentials out of the APK.
