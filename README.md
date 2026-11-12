# Campus University Management System

A PHP and MySQL application for managing university students, faculty, departments, courses, and enrollments. It includes a responsive admin dashboard and password-protected administrator access.

## Requirements

- PHP 8.0 or later with the PDO MySQL extension enabled
- MySQL 8.0 or later
- A PHP-capable web server (Apache, Nginx, or PHP's built-in development server)

## Setup

1. Import `database.sql` using MySQL Workbench or phpMyAdmin, or run this in PowerShell with a MySQL account that can create databases:

   ```powershell
   Get-Content -Raw .\database.sql | mysql -u root -p
   ```

2. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in `config.php` to match your local MySQL setup.
3. From the project directory, start the PHP development server:

   ```sh
   php -S localhost:8000
   ```

4. Open `http://localhost:8000/setup.php` and create the first administrator account. Use `http://localhost:8000/login.php` for the admin workspace or `http://localhost:8000/student_login.php` for the student portal.

### Existing database upgrades

After updating an existing installation, run the student-login migration from the project directory:

```powershell
php .\migrations\001_student_login.php
```

In the admin workspace, add or edit a student and set a temporary password to enable student portal access. Existing student records remain unable to sign in until an administrator sets a password. Give the student ID and temporary password to the student through a secure, separate channel; the student must choose a new password on first sign-in.

For production, serve the site over HTTPS, restrict access to `setup.php` after creating the administrator, and use a MySQL account with only the permissions the application needs.

Add a department before creating faculty or courses, and add at least one student and one course before creating enrollments.

## Features

- Admin sign-in with securely hashed passwords and session-based access control
- Separate student sign-in with admin-provisioned accounts and a required first-login password change
- Student portal for profile details, course enrollment, enrollment status, and grades
- Dashboard summary counts for all five management areas
- Create, edit, list, and delete records for students, faculty, departments, courses, and enrollments
- Validated forms, CSRF tokens, prepared statements, and relational foreign keys
- Responsive layout for desktop and mobile screens
