<?php
/**
 * Logout Handler
 * Network Switch Inventory Management System
 */

session_start();
require_once 'auth.php';

// Perform logout
logout();

// Redirect to login page
header('Location: login.php');
exit;
