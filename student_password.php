<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$student = require_student(true);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($password) < 10) {
        $error = 'Choose a password with at least 10 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } elseif (password_verify($password, $student['password_hash'])) {
        $error = 'Choose a password different from your temporary password.';
    } else {
        try {
            $statement = db()->prepare(
                'UPDATE students SET password_hash = ?, must_change_password = 0 WHERE id = ?'
            );
            $statement->execute([password_hash($password, PASSWORD_DEFAULT), $student['id']]);
            session_regenerate_id(true);
            set_flash('Your password has been updated.');
            header('Location: student.php');
            exit;
        } catch (PDOException $exception) {
            error_log('Student password update failed: ' . $exception->getMessage());
            $error = 'Your password could not be updated. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Change your password · Student portal</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <a class="brand-mark" href="student.php">U</a>
    <p class="eyebrow">STUDENT PORTAL</p>
    <h1>Choose a new password</h1>
    <p class="muted">For your security, change the temporary password before continuing.</p>
    <?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>New password <span class="field-hint">At least 10 characters</span><input name="password" type="password" required minlength="10" autocomplete="new-password"></label>
      <label>Confirm new password<input name="confirm_password" type="password" required minlength="10" autocomplete="new-password"></label>
      <button class="button full-width" type="submit">Update password</button>
    </form>
    <form method="post" action="student_logout.php" class="auth-logout">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <button class="text-action" type="submit">Sign out</button>
    </form>
  </main>
</body>
</html>
