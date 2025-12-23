<?php
/**
 * Dashboard Redirect
 * This file redirects to the main ERP dashboard (index.php)
 * The actual dashboard functionality is in index.php
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, if not redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redirect to main dashboard
header('Location: index.php');
exit;
