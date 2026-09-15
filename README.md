# ReScheduleWeb

## Check locally with XAMPP

1. Copy this project folder to `C:\xampp\htdocs\ReScheduleWeb`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, create a database named `reschedule`, and import your MySQL schema/data tables.
4. In `C:\xampp\php\php.ini`, enable `extension=pdo_mysql`, then restart Apache.
5. The default local connection is `root` with an empty password. To use different values, set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `DB_PORT` in the Apache/PHP environment.
6. Open http://localhost/ReScheduleWeb/ in a browser and test authentication and schedule workflows.

The UI uses relative stylesheet and script paths, so it works from the XAMPP subfolder without changing application routes. The application now uses PDO MySQL and no longer requires the PostgreSQL or Supabase connection. Keep database credentials outside version control before deploying to a shared university server.