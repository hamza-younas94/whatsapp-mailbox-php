<?php
/**
 * Diagnostic Page to Check User Data Assignment
 * Shows current user info and data counts per user
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;

// Check authentication
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$currentUser = getCurrentUser();

// Get data counts per user
$users = User::all();
$userDataCounts = [];

foreach ($users as $user) {
    $userDataCounts[$user->id] = [
        'username' => $user->username,
        'email' => $user->email,
        'role' => $user->role,
        'contacts' => Contact::where('user_id', $user->id)->count(),
        'messages' => Message::where('user_id', $user->id)->count(),
    ];
}

// Show HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Data Diagnostic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 30px; }
        .container { max-width: 900px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .current-user { background: #e7f3ff; border-left: 4px solid #128C7E; padding: 20px; margin-bottom: 30px; border-radius: 4px; }
        .user-data { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 4px; border: 1px solid #ddd; }
        .user-data.current { background: #f0f8f0; border-color: #128C7E; }
        h1 { color: #128C7E; margin-bottom: 30px; }
        h3 { color: #333; margin-top: 20px; }
        .badge { margin-right: 5px; }
        .action-btn { margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 User Data Diagnostic</h1>
        
        <div class="current-user">
            <h3>Current Logged-In User</h3>
            <p><strong>User ID:</strong> <?php echo htmlspecialchars($currentUser->id); ?></p>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($currentUser->username); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($currentUser->email); ?></p>
            <p><strong>Role:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($currentUser->role); ?></span></p>
        </div>

        <h3>Data Distribution Across Users</h3>
        
        <?php foreach ($userDataCounts as $userId => $data): ?>
            <div class="user-data <?php echo ($userId === $currentUser->id) ? 'current' : ''; ?>">
                <h5>
                    <?php echo htmlspecialchars($data['username']); ?>
                    <?php if ($userId === $currentUser->id): ?>
                        <span class="badge bg-success">Currently Logged In</span>
                    <?php endif; ?>
                </h5>
                <p><strong>User ID:</strong> <?php echo $userId; ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($data['email']); ?></p>
                <p><strong>Role:</strong> <span class="badge bg-secondary"><?php echo htmlspecialchars($data['role']); ?></span></p>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <strong>Contacts:</strong> 
                        <span class="badge bg-primary"><?php echo $data['contacts']; ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Messages:</strong> 
                        <span class="badge bg-primary"><?php echo $data['messages']; ?></span>
                    </div>
                </div>

                <?php if ($userId !== $currentUser->id && ($data['contacts'] > 0 || $data['messages'] > 0)): ?>
                    <div class="action-btn">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reassign">
                            <input type="hidden" name="from_user_id" value="<?php echo $userId; ?>">
                            <input type="hidden" name="to_user_id" value="<?php echo $currentUser->id; ?>">
                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Reassign all data from <?php echo htmlspecialchars($data['username']); ?> to <?php echo htmlspecialchars($currentUser->username); ?>?')">
                                📌 Reassign to Me
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <hr class="my-4">
        
        <div>
            <h3>Summary</h3>
            <p><strong>Total Users:</strong> <?php echo count($userDataCounts); ?></p>
            <p><strong>Your Data Count:</strong> 
                <?php 
                $yourCount = ($userDataCounts[$currentUser->id]['contacts'] ?? 0) + 
                             ($userDataCounts[$currentUser->id]['messages'] ?? 0);
                echo $yourCount;
                ?>
            </p>
            
            <?php if ($yourCount === 0 && count($userDataCounts) > 1): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ No data found for your account!</strong><br>
                    You have no contacts or messages assigned. This is why nothing shows in the mailbox.
                    You can reassign data from another user above, or create new contacts/messages.
                </div>
            <?php endif; ?>
        </div>

        <hr class="my-4">
        
        <div>
            <a href="index.php" class="btn btn-primary">← Back to Mailbox</a>
        </div>
    </div>

    <script>
        // Handle data reassignment
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (this.action && this.action.includes('reassign')) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    
                    fetch('api.php/admin/reassign-user-data', {
                        method: 'POST',
                        body: JSON.stringify(Object.fromEntries(formData))
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ Data reassigned successfully!');
                            location.reload();
                        } else {
                            alert('❌ Error: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(err => {
                        alert('❌ Error: ' + err.message);
                    });
                }
            });
        });
    </script>
</body>
</html>
