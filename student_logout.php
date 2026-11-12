<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verify_csrf();
unset($_SESSION['student_id'], $_SESSION['student_name']);
session_regenerate_id(true);
header('Location: student_login.php');
exit;
