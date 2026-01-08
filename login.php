<?php
/**
 * Login Page with Twig Template
 */
require_once 'config.php';
require_once 'auth.php';

// If already logged in, redirect to mailbox
if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

// Render Twig template
render('login.html.twig', [
    'error' => $error
]);
