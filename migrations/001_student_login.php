<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/app.php';

try {
    $statement = db()->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute(['students']);
    $columns = $statement->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('password_hash', $columns, true)) {
        db()->exec('ALTER TABLE students ADD COLUMN password_hash VARCHAR(255) NULL AFTER status');
    }
    if (!in_array('must_change_password', $columns, true)) {
        db()->exec(
            'ALTER TABLE students
             ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash'
        );
    }

    echo "Student login migration completed successfully.\n";
} catch (Throwable $exception) {
    error_log('Student login migration failed: ' . $exception->getMessage());
    fwrite(STDERR, "Student login migration failed. Check the PHP error log and database credentials.\n");
    exit(1);
}
