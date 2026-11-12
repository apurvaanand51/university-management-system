<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $connection = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('Invalid form token. Please go back, refresh the page, and try again.');
    }
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_student(bool $allowPasswordChange = false): array
{
    $studentId = filter_var($_SESSION['student_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$studentId) {
        header('Location: student_login.php');
        exit;
    }

    $statement = db()->prepare(
        'SELECT s.id, s.student_no, s.first_name, s.last_name, s.email, s.phone,
                s.admission_date, s.status, s.password_hash, s.must_change_password,
                d.name AS department_name
         FROM students s
         LEFT JOIN departments d ON d.id = s.department_id
         WHERE s.id = ?'
    );
    $statement->execute([$studentId]);
    $student = $statement->fetch();
    if (!$student || $student['password_hash'] === null) {
        unset($_SESSION['student_id'], $_SESSION['student_name']);
        header('Location: student_login.php');
        exit;
    }

    if (!$allowPasswordChange && (bool) $student['must_change_password']) {
        header('Location: student_password.php');
        exit;
    }

    return $student;
}

function set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function modules(): array
{
    return [
        'students' => [
            'title' => 'Students',
            'table' => 'students',
            'singular' => 'student',
            'fields' => [
                'student_no' => ['label' => 'Student ID', 'type' => 'text', 'required' => true],
                'first_name' => ['label' => 'First name', 'type' => 'text', 'required' => true],
                'last_name' => ['label' => 'Last name', 'type' => 'text', 'required' => true],
                'email' => ['label' => 'Email', 'type' => 'email', 'required' => true],
                'phone' => ['label' => 'Phone', 'type' => 'text'],
                'department_id' => ['label' => 'Department', 'type' => 'select', 'options' => 'departments'],
                'admission_date' => ['label' => 'Admission date', 'type' => 'date'],
                'status' => ['label' => 'Status', 'type' => 'select', 'choices' => ['Active', 'On leave', 'Graduated'], 'required' => true],
            ],
            'columns' => ['student_no' => 'Student ID', 'student_name' => 'Name', 'email' => 'Email', 'department_name' => 'Department', 'status' => 'Status', 'portal_access' => 'Student login'],
            'list_sql' => 'SELECT s.id, s.student_no, CONCAT(s.first_name, " ", s.last_name) AS student_name, s.email, COALESCE(d.name, "—") AS department_name, s.status, CASE WHEN s.password_hash IS NULL THEN "Not enabled" ELSE "Enabled" END AS portal_access FROM students s LEFT JOIN departments d ON d.id = s.department_id ORDER BY s.created_at DESC',
        ],
        'faculty' => [
            'title' => 'Faculty',
            'table' => 'faculty',
            'singular' => 'faculty member',
            'fields' => [
                'employee_no' => ['label' => 'Employee ID', 'type' => 'text', 'required' => true],
                'first_name' => ['label' => 'First name', 'type' => 'text', 'required' => true],
                'last_name' => ['label' => 'Last name', 'type' => 'text', 'required' => true],
                'email' => ['label' => 'Email', 'type' => 'email', 'required' => true],
                'phone' => ['label' => 'Phone', 'type' => 'text'],
                'department_id' => ['label' => 'Department', 'type' => 'select', 'options' => 'departments', 'required' => true],
            ],
            'columns' => ['employee_no' => 'Employee ID', 'faculty_name' => 'Name', 'email' => 'Email', 'department_name' => 'Department'],
            'list_sql' => 'SELECT f.id, f.employee_no, CONCAT(f.first_name, " ", f.last_name) AS faculty_name, f.email, d.name AS department_name FROM faculty f LEFT JOIN departments d ON d.id = f.department_id ORDER BY f.created_at DESC',
        ],
        'departments' => [
            'title' => 'Departments',
            'table' => 'departments',
            'singular' => 'department',
            'fields' => [
                'name' => ['label' => 'Department name', 'type' => 'text', 'required' => true],
                'code' => ['label' => 'Department code', 'type' => 'text', 'required' => true],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
            ],
            'columns' => ['name' => 'Department', 'code' => 'Code', 'description' => 'Description'],
            'list_sql' => 'SELECT id, name, code, description FROM departments ORDER BY name',
        ],
        'courses' => [
            'title' => 'Courses',
            'table' => 'courses',
            'singular' => 'course',
            'fields' => [
                'course_code' => ['label' => 'Course code', 'type' => 'text', 'required' => true],
                'title' => ['label' => 'Course title', 'type' => 'text', 'required' => true],
                'credits' => ['label' => 'Credits', 'type' => 'number', 'required' => true],
                'department_id' => ['label' => 'Department', 'type' => 'select', 'options' => 'departments', 'required' => true],
                'faculty_id' => ['label' => 'Instructor', 'type' => 'select', 'options' => 'faculty'],
            ],
            'columns' => ['course_code' => 'Code', 'title' => 'Course', 'credits' => 'Credits', 'department_name' => 'Department', 'faculty_name' => 'Instructor'],
            'list_sql' => 'SELECT c.id, c.course_code, c.title, c.credits, d.name AS department_name, COALESCE(CONCAT(f.first_name, " ", f.last_name), "—") AS faculty_name FROM courses c LEFT JOIN departments d ON d.id = c.department_id LEFT JOIN faculty f ON f.id = c.faculty_id ORDER BY c.course_code',
        ],
        'enrollments' => [
            'title' => 'Enrollments',
            'table' => 'enrollments',
            'singular' => 'enrollment',
            'fields' => [
                'student_id' => ['label' => 'Student', 'type' => 'select', 'options' => 'students', 'required' => true],
                'course_id' => ['label' => 'Course', 'type' => 'select', 'options' => 'courses', 'required' => true],
                'term' => ['label' => 'Term', 'type' => 'select', 'choices' => ['Spring', 'Summer', 'Fall', 'Winter'], 'required' => true],
                'academic_year' => ['label' => 'Academic year', 'type' => 'text', 'required' => true],
                'status' => ['label' => 'Status', 'type' => 'select', 'choices' => ['Enrolled', 'Completed', 'Withdrawn'], 'required' => true],
                'grade' => ['label' => 'Grade', 'type' => 'text'],
            ],
            'columns' => ['student_name' => 'Student', 'course_code' => 'Course', 'term' => 'Term', 'academic_year' => 'Academic year', 'status' => 'Status', 'grade' => 'Grade'],
            'list_sql' => 'SELECT e.id, CONCAT(s.first_name, " ", s.last_name) AS student_name, c.course_code, e.term, e.academic_year, e.status, COALESCE(e.grade, "—") AS grade FROM enrollments e JOIN students s ON s.id = e.student_id JOIN courses c ON c.id = e.course_id ORDER BY e.created_at DESC',
        ],
    ];
}

function field_options(array $field): array
{
    if (isset($field['choices'])) {
        return array_combine($field['choices'], $field['choices']);
    }

    $queries = [
        'departments' => 'SELECT id, name AS label FROM departments ORDER BY name',
        'faculty' => 'SELECT id, CONCAT(first_name, " ", last_name) AS label FROM faculty ORDER BY last_name, first_name',
        'students' => 'SELECT id, CONCAT(student_no, " — ", first_name, " ", last_name) AS label FROM students ORDER BY last_name, first_name',
        'courses' => 'SELECT id, CONCAT(course_code, " — ", title) AS label FROM courses ORDER BY course_code',
    ];
    $key = $field['options'] ?? '';
    if (!isset($queries[$key])) {
        return [];
    }

    $options = [];
    foreach (db()->query($queries[$key]) as $row) {
        $options[$row['id']] = $row['label'];
    }
    return $options;
}
