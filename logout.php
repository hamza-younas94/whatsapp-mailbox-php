<?php
/**
 * Logout
 */
require_once 'config.php';
require_once 'auth.php';

logout();
header('Location: login.php');
exit;
