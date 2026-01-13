<?php
/**
 * Auto-Tag Rules Management Page
 */
require_once 'bootstrap.php';
require_once 'auth.php';

// Check authentication
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

// Render Twig template
render('auto_tag_rules.html.twig', [
    'user' => $user
]);
