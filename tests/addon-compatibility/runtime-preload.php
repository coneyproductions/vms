<?php
declare(strict_types=1);

if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}

@ini_set('memory_limit', '512M');

$page = getenv('BVM_COMPAT_REQUEST_PAGE');
if (is_string($page) && $page !== '') {
	$_GET['page'] = $page;
	$_REQUEST['page'] = $page;
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php' . ($page !== false && $page !== '' ? '?page=' . rawurlencode($page) : '');
