<?php
/**
 * CRM Dashboard - Complete CRM View
 */
require_once 'bootstrap.php';
require_once 'auth.php';

use App\Middleware\TenantMiddleware;

// Check authentication
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

requireFeature('crm');

$user = getCurrentUser();

// Render CRM Dashboard template
render('crm_dashboard.html.twig', [
    'user' => $user
]);
