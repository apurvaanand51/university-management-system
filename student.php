<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$student = require_student();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') !== 'enroll') {
        http_response_code(400);
        exit('Invalid action.');
    }

    $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
    $term = (string) ($_POST['term'] ?? '');
    $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
    if (!$courseId || !in_array($term, ['Spring', 'Summer', 'Fall', 'Winter'], true)
        || !preg_match('/^20[0-9]{2}-[0-9]{2}$/D', $academicYear)) {
        set_flash('Choose a valid course, term, and academic year.', 'error');
    } else {
        try {
            $course = db()->prepare('SELECT id FROM courses WHERE id = ?');
            $course->execute([$courseId]);
            if (!$course->fetch()) {
                throw new InvalidArgumentException('That course is no longer available.');
            }

            $existing = db()->prepare(
                'SELECT id FROM enrollments
                 WHERE student_id = ? AND course_id = ? AND term = ? AND academic_year = ?'
            );
            $existing->execute([$student['id'], $courseId, $term, $academicYear]);
            if ($existing->fetch()) {
                throw new InvalidArgumentException('You already have an enrollment for that course and term.');
            }

            $statement = db()->prepare(
                'INSERT INTO enrollments (student_id, course_id, term, academic_year, status)
                 VALUES (?, ?, ?, ?, "Enrolled")'
            );
            $statement->execute([$student['id'], $courseId, $term, $academicYear]);
            set_flash('You have enrolled in the course.');
        } catch (InvalidArgumentException $exception) {
            set_flash($exception->getMessage(), 'error');
        } catch (PDOException $exception) {
            error_log('Student course enrollment failed: ' . $exception->getMessage());
            $message = $exception->getCode() === '23000'
                ? 'You already have an enrollment for that course and term.'
                : 'We could not complete your enrollment. Please try again.';
            set_flash($message, 'error');
        }
    }
    header('Location: student.php#courses');
    exit;
}

$flash = take_flash();
$courses = db()->query(
    'SELECT c.id, c.course_code, c.title, c.credits, d.name AS department_name,
            COALESCE(CONCAT(f.first_name, " ", f.last_name), "Instructor to be assigned") AS faculty_name
     FROM courses c
     JOIN departments d ON d.id = c.department_id
     LEFT JOIN faculty f ON f.id = c.faculty_id
     ORDER BY c.course_code'
)->fetchAll();
$statement = db()->prepare(
    'SELECT e.term, e.academic_year, e.status, e.grade, c.course_code, c.title, c.credits,
            COALESCE(CONCAT(f.first_name, " ", f.last_name), "Instructor to be assigned") AS faculty_name
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     LEFT JOIN faculty f ON f.id = c.faculty_id
     WHERE e.student_id = ?
     ORDER BY e.academic_year DESC, FIELD(e.term, "Fall", "Winter", "Spring", "Summer"), c.course_code'
);
$statement->execute([$student['id']]);
$enrollments = $statement->fetchAll();
$currentYear = (int) date('Y');
$defaultAcademicYear = $currentYear . '-' . substr((string) ($currentYear + 1), -2);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student portal · Campus</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-page">
  <aside class="sidebar">
    <a class="brand" href="student.php"><span class="brand-mark small">U</span><span>Campus<span class="brand-sub">STUDENT PORTAL</span></span></a>
    <p class="nav-label">MY CAMPUS</p>
    <nav>
      <a class="nav-item active" href="student.php"><span class="nav-icon">⌂</span>My overview</a>
      <a class="nav-item" href="#courses"><span class="nav-icon">C</span>Courses</a>
      <a class="nav-item" href="#enrollments"><span class="nav-icon">E</span>My enrollments</a>
      <a class="nav-item" href="#profile"><span class="nav-icon">P</span>My profile</a>
    </nav>
    <div class="sidebar-bottom">
      <div class="user-chip"><span class="avatar"><?= e(strtoupper(substr($student['first_name'], 0, 1))) ?></span><span><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong><small>Student · <?= e($student['student_no']) ?></small></span></div>
      <form method="post" action="student_logout.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="signout" type="submit">Sign out <span aria-hidden="true">↗</span></button>
      </form>
    </div>
  </aside>
  <main class="main-content">
    <header class="topbar"><span class="breadcrumb">Campus <span>/</span> Student portal</span><span class="today"><?= e(date('l, F j, Y')) ?></span></header>
    <div class="content">
      <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
      <section class="page-heading">
        <div><p class="eyebrow">YOUR STUDENT PORTAL</p><h1>Welcome, <?= e($student['first_name']) ?></h1><p class="muted">View your campus profile, explore courses, and keep track of your grades.</p></div>
      </section>
      <section class="stat-grid student-stat-grid">
        <a class="stat-card" href="#courses"><span class="stat-label">Available courses</span><strong><?= count($courses) ?></strong><span class="stat-link">Explore courses <span>→</span></span></a>
        <a class="stat-card" href="#enrollments"><span class="stat-label">My enrollments</span><strong><?= count($enrollments) ?></strong><span class="stat-link">View enrollment history <span>→</span></span></a>
        <a class="stat-card" href="#profile"><span class="stat-label">Student ID</span><strong class="student-number"><?= e($student['student_no']) ?></strong><span class="stat-link"><?= e($student['status']) ?> student account</span></a>
      </section>
      <section class="panel profile-panel" id="profile">
        <div class="panel-heading"><div><h2>My profile</h2><p>Your university student record</p></div></div>
        <div class="profile-grid">
          <div><span class="profile-label">Full name</span><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong></div>
          <div><span class="profile-label">Student ID</span><strong><?= e($student['student_no']) ?></strong></div>
          <div><span class="profile-label">Email</span><strong><?= e($student['email']) ?></strong></div>
          <div><span class="profile-label">Phone</span><strong><?= e($student['phone'] ?: 'Not provided') ?></strong></div>
          <div><span class="profile-label">Department</span><strong><?= e($student['department_name'] ?: 'Not assigned') ?></strong></div>
          <div><span class="profile-label">Admission date</span><strong><?= e($student['admission_date'] ?: 'Not provided') ?></strong></div>
        </div>
      </section>
      <section class="panel" id="courses">
        <div class="panel-heading"><div><h2>Course catalog</h2><p>Browse courses and enroll for a term.</p></div></div>
        <?php if ($courses === []): ?>
          <div class="empty-state"><span class="empty-icon">C</span><h3>No courses available yet</h3><p>Your university will add courses to the catalog.</p></div>
        <?php else: ?>
          <div class="course-grid">
            <?php foreach ($courses as $course): ?>
              <article class="course-card">
                <span class="course-code"><?= e($course['course_code']) ?></span>
                <h3><?= e($course['title']) ?></h3>
                <p class="course-meta"><?= e($course['department_name']) ?> <span>·</span> <?= e($course['credits']) ?> credits</p>
                <p class="course-instructor">Instructor: <?= e($course['faculty_name']) ?></p>
                <form method="post" class="enroll-form">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="enroll">
                  <input type="hidden" name="course_id" value="<?= e($course['id']) ?>">
                  <label>Term<select name="term" required><option>Fall</option><option>Winter</option><option>Spring</option><option>Summer</option></select></label>
                  <label>Academic year<input name="academic_year" value="<?= e($defaultAcademicYear) ?>" pattern="20[0-9]{2}-[0-9]{2}" maxlength="7" required></label>
                  <button class="button full-width" type="submit">Enroll in course</button>
                </form>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
      <section class="panel" id="enrollments">
        <div class="panel-heading"><div><h2>My enrollments &amp; grades</h2><p>Grades appear here when they are entered by your university.</p></div></div>
        <?php if ($enrollments === []): ?>
          <div class="empty-state"><span class="empty-icon">E</span><h3>You have no enrollments yet</h3><p>Choose a course from the catalog to get started.</p></div>
        <?php else: ?>
          <div class="table-wrap"><table><thead><tr><th>Course</th><th>Term</th><th>Academic year</th><th>Credits</th><th>Instructor</th><th>Status</th><th>Grade</th></tr></thead><tbody>
            <?php foreach ($enrollments as $enrollment): ?><tr>
              <td><?= e($enrollment['course_code'] . ' — ' . $enrollment['title']) ?></td>
              <td><?= e($enrollment['term']) ?></td>
              <td><?= e($enrollment['academic_year']) ?></td>
              <td><?= e($enrollment['credits']) ?></td>
              <td><?= e($enrollment['faculty_name']) ?></td>
              <td><?= e($enrollment['status']) ?></td>
              <td><?= e($enrollment['grade'] ?: '—') ?></td>
            </tr><?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </section>
      <footer class="footer">Campus Student Portal <span>·</span> Contact your administrator for account support</footer>
    </div>
  </main>
</body>
</html>
