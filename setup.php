<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$error = '';
$complete = false;
try {
    $complete = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
} catch (PDOException $exception) {
    error_log('University system setup check failed: ' . $exception->getMessage());
    $error = 'Cannot connect to the database. Check config.php and make sure database.sql has been imported.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && !$complete) {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter your name and a valid email address.';
    } elseif (strlen($password) < 10) {
        $error = 'Choose a password with at least 10 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The passwords do not match.';
    } else {
        $statement = db()->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)');
        try {
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php');
            exit;
        } catch (PDOException $exception) {
            error_log('University system administrator setup failed: ' . $exception->getMessage());
            $error = 'The administrator could not be created. Check that the email is not already in use.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Set up your university system</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <a class="brand-mark" href="login.php">U</a>
    <p class="eyebrow">UNIVERSITY MANAGEMENT</p>
    <h1>Set up your admin account</h1>
    <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($complete): ?>
      <div class="alert success">An administrator account already exists. Sign in to continue.</div>
      <a class="button full-width" href="login.php">Go to sign in</a>
    <?php elseif ($error !== '' && !isset($_POST['name'])): ?>
      <p class="muted">Import <code>database.sql</code>, then check the database credentials in <code>config.php</code>.</p>
    <?php else: ?>
      <p class="muted">Create the first administrator to start managing your campus.</p>
      <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Full name<input name="name" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>"></label>
        <label>Email address<input name="email" type="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label>Password <span class="field-hint">At least 10 characters</span><input name="password" type="password" required minlength="10" autocomplete="new-password"></label>
        <label>Confirm password<input name="confirm_password" type="password" required minlength="10" autocomplete="new-password"></label>
        <button class="button full-width" type="submit">Create admin account</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
