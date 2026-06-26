<?php
require_once 'config/config.php';
require_once 'includes/auth_check.php';

// Log logout action
logAudit('logout', 'user', getCurrentUserId(), 'User logged out');

// Destroy session
session_unset();
session_destroy();

// Clear remember me cookie
setcookie('remember_user', '', time() - 3600, '/');

// Redirect to login
redirect('/IP-Address-Inventory/login.php?logout=1');
