<?php
/**
 * admin/logout.php
 * Logs the user out and redirects to the login page. Supports both
 * POST-based logout (preferred, with CSRF) and GET fallback for
 * convenience. Server-side session cleanup is performed via
 * `do_logout()` in `config.php`.
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	if (!verify_csrf($token)) {
		header('HTTP/1.1 400 Bad Request');
		echo 'Invalid CSRF token';
		exit;
	}
	do_logout();
	header('Location: login.php');
	exit;
}

// fallback: GET
do_logout();
header('Location: login.php');
exit;
