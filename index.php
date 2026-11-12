<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_admin();

$allModules = modules();
$page = (string) ($_GET['page'] ?? 'dashboard');
if ($page !== 'dashboard' && !isset($allModules[$page])) {
    http_response_code(404);
    exit('Page not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $moduleKey = (string) ($_POST['page'] ?? '');
    if (!isset($allModules[$moduleKey])) {
        http_response_code(400);
        exit('Invalid module.');
    }
    $module = $allModules[$moduleKey];
    $table = $module['table'];
    $submittedValues = [];
    foreach ($module['fields'] as $name => $field) {
        $submittedValues[$name] = trim((string) ($_POST[$name] ?? ''));
    }
    $submittedId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    try {
        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid record.');
            }
            $statement = db()->prepare("DELETE FROM {$table} WHERE id = ?");
            $statement->execute([$id]);
            set_flash(ucfirst($module['singular']) . ' deleted.');
        } elseif ($action === 'save') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $temporaryPassword = (string) ($_POST['temporary_password'] ?? '');
            $values = [];
            $errors = [];
            foreach ($module['fields'] as $name => $field) {
                $value = trim((string) ($_POST[$name] ?? ''));
                if ($value === '') {
                    if (!empty($field['required'])) {
                        $errors[] = $field['label'] . ' is required.';
                    }
                    $values[$name] = null;
                    continue;
                }
                if ($field['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Enter a valid ' . strtolower($field['label']) . '.';
                } elseif ($field['type'] === 'number') {
                    $number = filter_var($value, FILTER_VALIDATE_INT);
                    if ($number === false || $number < 1 || $number > 255) {
                        $errors[] = $field['label'] . ' must be a whole number from 1 to 255.';
                    } else {
                        $value = $number;
                    }
                } elseif ($field['type'] === 'select') {
                    $options = field_options($field);
                    if (!array_key_exists($value, $options)) {
                        $errors[] = 'Choose a valid option for ' . strtolower($field['label']) . '.';
                    } elseif (isset($field['options'])) {
                        $value = (int) $value;
                    }
                } elseif ($field['type'] === 'date') {
                    $date = DateTime::createFromFormat('!Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) {
                        $errors[] = 'Enter a valid ' . strtolower($field['label']) . '.';
                    }
                }
                $values[$name] = $value;
            }

            if ($moduleKey === 'students') {
                if (!$id && $temporaryPassword === '') {
                    $errors[] = 'Set a temporary password to enable this student login.';
                } elseif ($temporaryPassword !== '' && strlen($temporaryPassword) < 10) {
                    $errors[] = 'The temporary password must have at least 10 characters.';
                }
            }

            if ($errors !== []) {
                throw new InvalidArgumentException(implode(' ', $errors));
            }
            $columns = array_keys($module['fields']);
            if ($id) {
                $assignments = implode(', ', array_map(static fn(string $column): string => "{$column} = ?", $columns));
                $parameters = array_values($values);
                if ($moduleKey === 'students' && $temporaryPassword !== '') {
                    $assignments .= ', password_hash = ?, must_change_password = 1';
                    $parameters[] = password_hash($temporaryPassword, PASSWORD_DEFAULT);
                }
                $statement = db()->prepare("UPDATE {$table} SET {$assignments} WHERE id = ?");
                $statement->execute([...$parameters, $id]);
                set_flash(ucfirst($module['singular']) . ' updated.');
            } else {
                $insertValues = array_values($values);
                if ($moduleKey === 'students') {
                    $columns[] = 'password_hash';
                    $columns[] = 'must_change_password';
                    $insertValues[] = password_hash($temporaryPassword, PASSWORD_DEFAULT);
                    $insertValues[] = 1;
                }
                $columnList = implode(', ', $columns);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $statement = db()->prepare("INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})");
                $statement->execute($insertValues);
                set_flash(ucfirst($module['singular']) . ' added.');
            }
            unset($_SESSION['form_values']);
        } else {
            http_response_code(400);
            exit('Invalid action.');
        }
        header('Location: index.php?page=' . rawurlencode($moduleKey));
        exit;
    } catch (InvalidArgumentException $exception) {
        set_flash($exception->getMessage(), 'error');
        if ($action === 'save') {
            $_SESSION['form_values'] = ['page' => $moduleKey, 'id' => $submittedId ?: null, 'values' => $submittedValues];
        }
    } catch (PDOException $exception) {
        error_log('University system save failed: ' . $exception->getMessage());
        $message = $exception->getCode() === '23000'
            ? 'This record conflicts with an existing record or is still in use.'
            : 'The record could not be saved. Check the submitted information and try again.';
        set_flash($message, 'error');
        if ($action === 'save') {
            $_SESSION['form_values'] = ['page' => $moduleKey, 'id' => $submittedId ?: null, 'values' => $submittedValues];
        }
    }
    header('Location: index.php?page=' . rawurlencode($moduleKey));
    exit;
}

$flash = take_flash();
$adminName = $_SESSION['admin_name'] ?? 'Administrator';
$editing = null;
$rows = [];
$counts = [];
$formValues = null;
if ($page === 'dashboard') {
    foreach ($allModules as $key => $module) {
        $counts[$key] = (int) db()->query("SELECT COUNT(*) FROM {$module['table']}")->fetchColumn();
    }
} else {
    $module = $allModules[$page];
    $rows = db()->query($module['list_sql'])->fetchAll();
    if (isset($_SESSION['form_values'])) {
        $savedForm = $_SESSION['form_values'];
        unset($_SESSION['form_values']);
        if (($savedForm['page'] ?? '') === $page) {
            $formValues = $savedForm['values'] ?? null;
            if (!isset($_GET['edit']) && !empty($savedForm['id'])) {
                $_GET['edit'] = (string) $savedForm['id'];
            }
        }
    }
    if (isset($_GET['edit'])) {
        $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
        if ($editId) {
            $statement = db()->prepare("SELECT * FROM {$module['table']} WHERE id = ?");
            $statement->execute([$editId]);
            $editing = $statement->fetch() ?: null;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page === 'dashboard' ? 'Dashboard' : $allModules[$page]['title']) ?> · Campus</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-page">
  <aside class="sidebar">
    <a class="brand" href="index.php"><span class="brand-mark small">U</span><span>Campus<span class="brand-sub">MANAGEMENT</span></span></a>
    <p class="nav-label">WORKSPACE</p>
    <nav>
      <a class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <?php foreach ($allModules as $key => $item): ?>
        <a class="nav-item <?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= e($key) ?>"><span class="nav-icon"><?= e(substr($item['title'], 0, 1)) ?></span><?= e($item['title']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
      <div class="user-chip"><span class="avatar"><?= e(strtoupper(substr($adminName, 0, 1))) ?></span><span><strong><?= e($adminName) ?></strong><small>Administrator</small></span></div>
      <form method="post" action="logout.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="signout" type="submit">Sign out <span aria-hidden="true">↗</span></button>
      </form>
    </div>
  </aside>
  <main class="main-content">
    <header class="topbar"><span class="breadcrumb">Campus <span>/</span> <?= e($page === 'dashboard' ? 'Overview' : $allModules[$page]['title']) ?></span><span class="today"><?= e(date('l, F j, Y')) ?></span></header>
    <div class="content">
      <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
      <?php if ($page === 'dashboard'): ?>
        <section class="page-heading">
          <div><p class="eyebrow">CAMPUS AT A GLANCE</p><h1>Good day, <?= e(explode(' ', trim($adminName))[0]) ?> <span class="wave">✦</span></h1><p class="muted">Here’s what’s happening across your university.</p></div>
        </section>
        <section class="stat-grid">
          <?php foreach ($allModules as $key => $item): ?>
            <a class="stat-card" href="index.php?page=<?= e($key) ?>"><span class="stat-label"><?= e($item['title']) ?></span><strong><?= e($counts[$key]) ?></strong><span class="stat-link">Manage <?= e(strtolower($item['title'])) ?> <span>→</span></span></a>
          <?php endforeach; ?>
        </section>
        <section class="welcome-card"><div><p class="eyebrow">GET STARTED</p><h2>Your campus, in one place.</h2><p>Keep student records, faculty, courses, and enrollments organized from a single workspace.</p><a class="button" href="index.php?page=students">Manage students <span>→</span></a></div><div class="welcome-orbit"><span>U</span></div></section>
      <?php else: ?>
        <section class="page-heading module-heading">
          <div><p class="eyebrow">CAMPUS DIRECTORY</p><h1><?= e($module['title']) ?></h1><p class="muted">Manage your university <?= e(strtolower($module['title'])) ?> records.</p></div>
          <a class="button" href="index.php?page=<?= e($page) ?>#record-form">+ Add <?= e($module['singular']) ?></a>
        </section>
        <section class="panel">
          <div class="panel-heading"><div><h2>All <?= e($module['title']) ?></h2><p><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></p></div></div>
          <?php if ($rows === []): ?>
            <div class="empty-state"><span class="empty-icon">＋</span><h3>No <?= e(strtolower($module['title'])) ?> yet</h3><p>Add your first <?= e($module['singular']) ?> using the form below.</p></div>
          <?php else: ?>
            <div class="table-wrap"><table><thead><tr><?php foreach ($module['columns'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?><th class="actions-heading">Actions</th></tr></thead><tbody>
              <?php foreach ($rows as $row): ?><tr>
                <?php foreach ($module['columns'] as $column => $label): ?><td><?= e($row[$column] ?? '—') ?></td><?php endforeach; ?>
                <td class="row-actions"><a class="text-action" href="index.php?page=<?= e($page) ?>&edit=<?= e($row['id']) ?>#record-form">Edit</a><form method="post" onsubmit="return confirm('Delete this record? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="page" value="<?= e($page) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="action" value="delete"><button class="text-action danger-text" type="submit">Delete</button></form></td>
              </tr><?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </section>
        <section class="panel form-panel" id="record-form">
          <div class="panel-heading"><div><h2><?= $editing ? 'Edit' : 'Add' ?> <?= e($module['singular']) ?></h2><p>Fields marked with * are required.</p></div><?php if ($editing): ?><a class="text-action" href="index.php?page=<?= e($page) ?>#record-form">Cancel edit</a><?php endif; ?></div>
          <form method="post" class="record-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="page" value="<?= e($page) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($_GET['edit'] ?? ($editing['id'] ?? '')) ?>">
            <?php foreach ($module['fields'] as $name => $field): $value = $formValues[$name] ?? ($editing[$name] ?? ''); ?>
              <label class="field-label"><?= e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?>
                <?php if ($field['type'] === 'select'): $options = field_options($field); ?>
                  <select name="<?= e($name) ?>" <?= !empty($field['required']) ? 'required' : '' ?>><option value="">Select <?= e(strtolower($field['label'])) ?></option><?php foreach ($options as $optionValue => $optionLabel): ?><option value="<?= e($optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option><?php endforeach; ?></select>
                <?php elseif ($field['type'] === 'textarea'): ?>
                  <textarea name="<?= e($name) ?>" rows="3" <?= !empty($field['required']) ? 'required' : '' ?>><?= e($value) ?></textarea>
                <?php else: ?>
                  <input name="<?= e($name) ?>" type="<?= e($field['type']) ?>" value="<?= e($value) ?>" <?= !empty($field['required']) ? 'required' : '' ?> <?= $field['type'] === 'number' ? 'min="1" max="255"' : '' ?>>
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
            <?php if ($page === 'students'): ?>
              <label class="field-label"><?= $editing ? 'Reset student password' : 'Temporary password *' ?>
                <input name="temporary_password" type="password" minlength="10" <?= $editing ? '' : 'required' ?> autocomplete="new-password">
                <span class="field-hint"><?= $editing ? 'Leave blank to keep the current password. A new password requires the student to change it at next login.' : 'At least 10 characters. Give this temporary password to the student securely.' ?></span>
              </label>
            <?php endif; ?>
            <div class="form-actions"><button class="button" type="submit"><?= $editing ? 'Save changes' : 'Add ' . e($module['singular']) ?></button><?php if ($editing): ?><a class="button secondary" href="index.php?page=<?= e($page) ?>">Cancel</a><?php endif; ?></div>
          </form>
        </section>
      <?php endif; ?>
      <footer class="footer">Campus Management <span>·</span> Built for better campus administration</footer>
    </div>
  </main>
</body>
</html>
