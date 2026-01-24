<?php
/**
 * User Registration Page
 * 
 * Allows new users to create accounts and become tenants
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\User;
use App\Models\UserApiCredential;
use App\Models\UserSubscription;
use App\Models\UserSettings;
use App\Validation;

$error = null;
$success = null;

// Handle registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $businessName = trim($_POST['business_name'] ?? '');
    
    // Validation using Validation class
    $validator = new Validation([
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'confirm_password' => $confirmPassword,
        'business_name' => $businessName
    ]);
    
    if (!$validator->validate([
        'username' => 'required|min:3|max:50',
        'email' => 'required|email|max:255',
        'password' => 'required|min:8',
        'confirm_password' => 'required|same:password',
        'business_name' => 'required|min:2|max:150'
    ])) {
        $errors = $validator->errors();
        $error = reset($errors)[0] ?? 'Validation failed';
    } else {
        // Check if username or email already exists
        $existingUser = User::where('username', $username)
            ->orWhere('email', $email)
            ->first();
        
        if ($existingUser) {
            $error = 'Username or email already exists';
        } else {
            try {
                // Start transaction
                $db->pdo->beginTransaction();
                
                // Create user
                $user = User::create([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'user',
                    'is_active' => true,
                ]);
                
                logger("[REGISTER] Created user {$user->id}: {$username}");
                
                // Create empty API credentials (to be filled later)
                UserApiCredential::create([
                    'user_id' => $user->id,
                    'business_name' => $businessName,
                    'api_access_token' => '',
                    'api_phone_number_id' => '',
                    'webhook_verify_token' => bin2hex(random_bytes(16)),
                    'api_version' => 'v18.0',
                    'is_active' => false, // Inactive until credentials are set
                ]);
                
                logger("[REGISTER] Created API credentials for user {$user->id}");
                
                // Create free subscription
                UserSubscription::create([
                    'user_id' => $user->id,
                    'plan' => 'free',
                    'status' => 'active',
                    'message_limit' => 100,
                    'contact_limit' => 50,
                    'messages_sent' => 0,
                    'starts_at' => date('Y-m-d H:i:s'),
                    'expires_at' => null, // Free plan never expires
                ]);
                
                logger("[REGISTER] Created subscription for user {$user->id}");
                
                // Create default settings
                UserSettings::create([
                    'user_id' => $user->id,
                    'timezone' => 'UTC',
                    'language' => 'en',
                    'date_format' => 'Y-m-d',
                    'time_format' => 'H:i:s',
                    'enable_email_notifications' => true,
                    'enable_webhook_notifications' => false,
                ]);
                
                logger("[REGISTER] Created settings for user {$user->id}");
                
                // Commit transaction
                $db->pdo->commit();
                
                $success = 'Account created successfully! Please log in.';
                
                // Redirect to login after 2 seconds
                header('Refresh: 2; url=login.php');
                
            } catch (\Exception $e) {
                $db->pdo->rollBack();
                $error = 'Registration failed: ' . $e->getMessage();
                logger("[REGISTER] Error: " . $e->getMessage(), 'error');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - WhatsApp Mailbox</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-header h1 {
            color: #667eea;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .register-header p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>🚀 Create Account</h1>
            <p>Join WhatsApp Mailbox - Connect Your Business</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success); ?>
                <p class="mb-0 mt-2"><small>Redirecting to login...</small></p>
            </div>
        <?php else: ?>
            <form method="POST" action="" data-validate='{"username":"required|min:3|max:50","email":"required|email|max:255","password":"required|min:8","confirm_password":"required|same:password","business_name":"required|min:2|max:150"}'>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="business_name" class="form-label">Business Name</label>
                    <input type="text" class="form-control" id="business_name" name="business_name" 
                           value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>" 
                           placeholder="Your Company Name" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           minlength="8" required>
                    <small class="text-muted">At least 8 characters</small>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           minlength="8" required>
                </div>

                <button type="submit" class="btn btn-primary btn-register">
                    Create Account
                </button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Log in here</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
