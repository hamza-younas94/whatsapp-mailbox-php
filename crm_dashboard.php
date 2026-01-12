<?php
/**
 * CRM Dashboard - Complete CRM View
 */
require_once 'bootstrap.php';
require_once 'auth.php';

// Check authentication
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

// Render CRM Dashboard template
render('crm_dashboard.html.twig', [
    'user' => $user
]);
