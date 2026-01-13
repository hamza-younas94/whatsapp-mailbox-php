<?php
/**
 * Advanced Search Page
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
render('search.html.twig', [
    'user' => $user
]);
