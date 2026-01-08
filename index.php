<?php
/**
 * Main Mailbox Interface with Twig Template
 */
require_once 'config.php';
require_once 'auth.php';

// Check authentication
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

// Render Twig template
render('dashboard.html.twig', [
    'user' => $user
]);
