<?php
/**
 * Login Page with Twig Template
 */
require_once 'bootstrap.php';
require_once 'auth.php';

use App\Validation;

// If already logged in, redirect to mailbox
if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $validator = new Validation(['username' => $username, 'password' => $password]);
    if (!$validator->validate(['username' => 'required', 'password' => 'required'])) {
        $error = 'Username and password are required';
    } elseif (login($username, $password)) {
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
