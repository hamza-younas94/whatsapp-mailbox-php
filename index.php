<?php
/**
 * Main Mailbox Interface with Twig Template
 */
require_once 'bootstrap.php';
require_once 'auth.php';

// Check authentication
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

requireFeature('mailbox');

$user = getCurrentUser();

// Render Twig template
render('dashboard.html.twig', [
    'user' => $user
]);
