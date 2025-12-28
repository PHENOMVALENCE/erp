<?php
// Bridge file to reuse existing leave type management
define('APP_ACCESS', true);
session_start();

// Reuse the main leave types implementation
require_once '../leave/leave-types.php';
