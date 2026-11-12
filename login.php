<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$setupNeeded = false;
try {
    $setupNeeded = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;
} catch (PDOException $exception) {
    error_log('University system login check failed: ' . $exception->getMessage());
    $error = 'Cannot connect to the database. Check config.php and import database.sql.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && !$setupNeeded) {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $statement = db()->prepare('SELECT id, name, email, password_hash FROM admins WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $admin = $statement->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        header('Location: index.php');
        exit;
    }
    $error = 'The email or password is incorrect.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin sign in · Campus</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <a class="brand-mark" href="login.php">U</a>
    <p class="eyebrow">UNIVERSITY MANAGEMENT</p>
    <h1>Welcome back</h1>
    <p class="muted">Sign in to manage your campus.</p>
    <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($setupNeeded): ?>
      <div class="alert">Your system is ready for its first administrator.</div>
      <a class="button full-width" href="setup.php">Set up admin account</a>
    <?php elseif ($error !== '' && !str_contains($error, 'incorrect')): ?>
      <p class="muted">Import <code>database.sql</code>, then verify your database credentials in <code>config.php</code>.</p>
    <?php else: ?>
      <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Email address<input name="email" type="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
        <button class="button full-width" type="submit">Sign in</button>
      </form>
      <p class="auth-footnote">First time here? <a href="setup.php">Set up your admin account</a></p>
      <p class="auth-footnote">Are you a student? <a href="student_login.php">Student sign in</a></p>
    <?php endif; ?>
  </main>
</body>
</html>
