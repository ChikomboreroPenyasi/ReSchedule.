# ReSchedule. Student Android App

This is the student-only Android client for the ReSchedule PHP website.

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

## Connect to the deployed website

1. Open this `student-android` folder in Android Studio.
2. Sync Gradle and run the `app` configuration on an emulator or device.
3. The app uses `https://reschedule-1.onrender.com/` for login, timetable data, and signup.

The Android app calls the website's PHP API over HTTPS. Render's PHP service reads and writes the same Supabase PostgreSQL database as the website, so both clients share user accounts, schedules, and reschedule requests. The app does not connect directly to Supabase and contains no database credentials.

To use a different deployment, change `API_BASE_URL` and `SIGNUP_URL` in `app/build.gradle.kts` to that website's HTTPS URLs, then rebuild the app. The website and its database can be updated independently of an installed Android APK; installing an updated app build is required to receive Android client changes.

## Local XAMPP testing

For local testing only, change the URLs in `app/build.gradle.kts` to the computer's reachable HTTPS or HTTP address. If using HTTP, Android cleartext traffic must be enabled for a development build. The phone and computer must be on the same Wi-Fi network, and Windows Firewall must allow Apache traffic.
