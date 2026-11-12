<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

if (!empty($_SESSION['student_id'])) {
    header('Location: student.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $studentNo = trim((string) ($_POST['student_no'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $statement = db()->prepare(
        'SELECT id, student_no, first_name, password_hash, must_change_password
         FROM students WHERE student_no = ? LIMIT 1'
    );
    $statement->execute([$studentNo]);
    $student = $statement->fetch();
    if ($student && $student['password_hash'] !== null && password_verify($password, $student['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['student_id'] = (int) $student['id'];
        $_SESSION['student_name'] = $student['first_name'];
        header('Location: ' . ((bool) $student['must_change_password'] ? 'student_password.php' : 'student.php'));
        exit;
    }
    $error = 'The student ID or password is incorrect, or student portal access has not been enabled.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student sign in · Campus</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <a class="brand-mark" href="student_login.php">U</a>
    <p class="eyebrow">STUDENT PORTAL</p>
    <h1>Welcome back</h1>
    <p class="muted">Sign in with the student ID and temporary password provided by your administrator.</p>
    <?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Student ID<input name="student_no" required autocomplete="username" value="<?= e($_POST['student_no'] ?? '') ?>"></label>
      <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
      <button class="button full-width" type="submit">Sign in to student portal</button>
    </form>
    <p class="auth-footnote"><a href="login.php">Administrator sign in</a></p>
  </main>
</body>
</html>
