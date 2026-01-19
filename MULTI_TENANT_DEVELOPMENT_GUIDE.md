# Multi-Tenant Development Guide

## Quick Reference for Adding Tenant Scoping

### When Creating a New Page or Feature

#### 1. Import Required Classes
```php
<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\YourModel;
use App\Middleware\TenantMiddleware;

$user = getCurrentUser();
```

#### 2. Filter Queries by User
```php
// ❌ DON'T DO THIS (loads all data)
$records = YourModel::all();

// ✅ DO THIS (filters by user)
$records = YourModel::where('user_id', $user->id)->get();

// ✅ For admins (show all)
if ($user->role === 'admin') {
    $records = YourModel::all();
} else {
    $records = YourModel::where('user_id', $user->id)->get();
}
```

#### 3. When Creating Records
```php
// ❌ DON'T DO THIS
$record = YourModel::create([
    'name' => $data['name'],
    'description' => $data['description']
]);

// ✅ DO THIS (add user_id)
$record = YourModel::create([
    'user_id' => $user->id,  // Required!
    'name' => $data['name'],
    'description' => $data['description']
]);
```

#### 4. When Updating/Deleting Records
```php
// ❌ DON'T DO THIS (someone could update another user's data)
$record = YourModel::find($id);
$record->update($data);

// ✅ DO THIS (verify ownership first)
$record = YourModel::where('user_id', $user->id)->findOrFail($id);
if (!TenantMiddleware::canAccess($record, $user->id)) {
    throw new Exception('Access denied');
}
$record->update($data);

// Or shorter version:
$record = YourModel::where('user_id', $user->id)->findOrFail($id);
$record->update($data); // Already scoped by user
```

#### 5. Handle Relationships
```php
// When loading related data, specify user_id
$contact = Contact::where('user_id', $user->id)->with('messages')->findOrFail($id);

// Eager load to avoid N+1 queries
$records = YourModel::where('user_id', $user->id)
    ->with('user', 'contact', 'creator')
    ->get();
```

---

## Common Patterns

### List Page Template
```php
<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\YourModel;
use App\Middleware\TenantMiddleware;

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $data = [
                    'user_id' => $user->id,  // ← IMPORTANT
                    'name' => sanitize($_POST['name']),
                    'other_field' => sanitize($_POST['other_field'])
                ];
                $record = YourModel::create($data);
                echo json_encode(['success' => true, 'record' => $record]);
                break;
            
            case 'update':
                $record = YourModel::where('user_id', $user->id)->findOrFail($_POST['id']);
                $record->update([
                    'name' => sanitize($_POST['name']),
                    'other_field' => sanitize($_POST['other_field'])
                ]);
                echo json_encode(['success' => true, 'record' => $record]);
                break;
            
            case 'delete':
                $record = YourModel::where('user_id', $user->id)->findOrFail($_POST['id']);
                $record->delete();
                echo json_encode(['success' => true]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch data for current user only
$records = YourModel::where('user_id', $user->id)
    ->orderBy('created_at', 'desc')
    ->get();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>Your Items</h1>
        <button class="btn btn-primary" onclick="openItemModal()">
            New Item
        </button>
    </div>
    
    <!-- Display items -->
    <?php foreach ($records as $record): ?>
        <!-- item display -->
    <?php endforeach; ?>
</div>
```

### API Endpoint Pattern
```php
<?php
// api/get-items.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth.php';

use App\Models\YourModel;

header('Content-Type: application/json');

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // Get items for current user only
    $items = YourModel::where('user_id', $user->id)
        ->with('relationships')
        ->get();
    
    echo json_encode(['success' => true, 'items' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
```

---

## TenantMiddleware Helper Methods

### Scope a Query
```php
$query = YourModel::query();
TenantMiddleware::scopeToCurrentUser($query, $user->id);
$records = $query->get();
```

### Add User ID to Data
```php
$data = ['name' => 'test', 'description' => 'test desc'];
TenantMiddleware::addCurrentUser($data, $user->id);
// Now $data has 'user_id' => $user->id
```

### Check Access
```php
$record = YourModel::find($id);
if (TenantMiddleware::canAccess($record, $user->id)) {
    // User can access this record
} else {
    // Access denied
}
```

### Admin Check
```php
if (TenantMiddleware::canAccessUser($userId, $user->id)) {
    // Current user can access that user's data
}
```

---

## Database Queries - After & Before Comparison

### SELECT - Filter by User
```php
// ❌ BEFORE (all data)
$contacts = Contact::all();

// ✅ AFTER (user's data only)
$contacts = Contact::where('user_id', $user->id)->get();

// ✅ With relationships
$contacts = Contact::where('user_id', $user->id)
    ->with('messages', 'tags', 'deals')
    ->get();
```

### INSERT - Include User ID
```php
// ❌ BEFORE
Message::create(['contact_id' => 123, 'text' => 'Hello']);

// ✅ AFTER
Message::create([
    'user_id' => $user->id,
    'contact_id' => 123,
    'text' => 'Hello'
]);
```

### UPDATE - Verify Access
```php
// ❌ BEFORE (anyone could update anyone's data)
$message = Message::find($id);
$message->update(['text' => 'Updated']);

// ✅ AFTER (verify user owns it first)
$message = Message::where('user_id', $user->id)->findOrFail($id);
$message->update(['text' => 'Updated']);
```

### DELETE - Verify Access
```php
// ❌ BEFORE
Message::destroy($id);

// ✅ AFTER
Message::where('user_id', $user->id)->findOrFail($id)->delete();
```

### COUNT - Filter by User
```php
// ❌ BEFORE
$count = Message::count();

// ✅ AFTER
$count = Message::where('user_id', $user->id)->count();

// ✅ With conditions
$unread = Message::where('user_id', $user->id)
    ->where('is_read', false)
    ->count();
```

### JOIN/RELATIONSHIP - Maintain User Filter
```php
// ❌ BEFORE
$messages = Message::with('contact')->get();

// ✅ AFTER
$messages = Message::where('user_id', $user->id)
    ->with('contact')
    ->get();

// ✅ Ensure related data is also filtered
$messages = Message::where('user_id', $user->id)
    ->whereHas('contact', function($q) use ($user) {
        $q->where('user_id', $user->id);
    })
    ->get();
```

---

## Admin vs User Access

### Show User's Own Data
```php
$records = YourModel::where('user_id', $user->id)->get();
```

### Show All Data (Admin Only)
```php
if ($user->role === 'admin') {
    $records = YourModel::all();
} else {
    $records = YourModel::where('user_id', $user->id)->get();
}

// Or more elegant:
$query = YourModel::query();
if ($user->role !== 'admin') {
    $query->where('user_id', $user->id);
}
$records = $query->get();
```

---

## Model Creation Checklist

When adding a new model that needs tenant support:

- [ ] Add `'user_id'` to `$fillable` array
- [ ] Add `user()` relationship method returning `belongsTo(User::class)`
- [ ] Update create operations to include `'user_id' => $user->id`
- [ ] Update list/query operations to include `.where('user_id', $user->id)`
- [ ] Update update/delete operations to verify user ownership
- [ ] Add migration if new table (include `user_id` FK)
- [ ] Update any API endpoints to scope by user
- [ ] Test access control between different users

---

## Security Best Practices

### ✅ DO
- Always check `where('user_id', $user->id)` in queries
- Always add `user_id` when creating records
- Always verify ownership before update/delete
- Use database constraints (FK with cascade)
- Log sensitive operations per user
- Test with multiple users simultaneously

### ❌ DON'T
- Never trust user input for user_id (always use `$user->id`)
- Never skip user_id filter in queries
- Never use global scope (e.g., `Model::all()`)
- Never allow direct ID-based access without verification
- Never store user credentials in session (use UserSettings)
- Never display data from other users in error messages

---

## Performance Tips

### Use Indexes
```sql
ALTER TABLE your_table ADD INDEX idx_user_id (user_id);
ALTER TABLE your_table ADD INDEX idx_user_created (user_id, created_at);
```

### Eager Load Relationships
```php
// ❌ N+1 queries
foreach ($records as $record) {
    echo $record->user->name; // Query per record
}

// ✅ One query
$records = YourModel::with('user')->get();
foreach ($records as $record) {
    echo $record->user->name; // No extra queries
}
```

### Limit Results
```php
// For large datasets
$records = YourModel::where('user_id', $user->id)
    ->limit(100)
    ->get();

// With pagination
$page = $_GET['page'] ?? 1;
$perPage = 20;
$records = YourModel::where('user_id', $user->id)
    ->paginate($perPage, ['*'], 'page', $page);
```

---

## Testing Tenant Isolation

### Test Script
```php
<?php
require_once __DIR__ . '/bootstrap.php';

use App\Models\Contact;
use App\Models\User;

// Get two users
$user1 = User::find(1);
$user2 = User::find(2);

// Create contacts for each user
Contact::create(['user_id' => $user1->id, 'name' => 'User 1 Contact']);
Contact::create(['user_id' => $user2->id, 'name' => 'User 2 Contact']);

// Test isolation
echo "User 1 contacts: " . Contact::where('user_id', $user1->id)->count() . "\n";
echo "User 2 contacts: " . Contact::where('user_id', $user2->id)->count() . "\n";

// User 1 should only see 1 contact
assert(Contact::where('user_id', $user1->id)->count() === 1, 'User 1 isolation failed');
assert(Contact::where('user_id', $user2->id)->count() === 1, 'User 2 isolation failed');

echo "✅ Tenant isolation working correctly!\n";
```

---

## Debugging

### Check User ID in Session
```php
$user = getCurrentUser();
var_dump($user->id); // Should match database user_id
```

### Verify Query Filtering
```php
$records = YourModel::where('user_id', $user->id)->get();
dd($records->toSql()); // See the actual SQL query
```

### Check Record Ownership
```php
$record = YourModel::find($id);
if ($record->user_id === $user->id) {
    echo "✅ User owns this record\n";
} else {
    echo "❌ User does NOT own this record\n";
}
```

---

## Common Issues & Solutions

### Issue: "Record not found" when updating
```php
// ❌ PROBLEM
$record = YourModel::find($id); // Finds ANY record
$record->update($data); // Even if not user's

// ✅ SOLUTION
$record = YourModel::where('user_id', $user->id)->findOrFail($id);
$record->update($data);
```

### Issue: User sees another user's data
```php
// ❌ PROBLEM
$records = YourModel::all(); // No user filter

// ✅ SOLUTION
$records = YourModel::where('user_id', $user->id)->get();
```

### Issue: Can't find own record after creating
```php
// ❌ PROBLEM
YourModel::create($data); // Missing user_id

// ✅ SOLUTION
YourModel::create([
    'user_id' => $user->id, // Must be included
    ...$data
]);
```

---

## Related Files

- **Core Middleware**: `app/Middleware/TenantMiddleware.php`
- **User Settings**: `app/Models/UserSettings.php`
- **Example Pages**: `quick-replies.php`, `broadcasts.php`, `tags.php`
- **Full Documentation**: `MULTI_TENANT_MIGRATION.md`

---

**Last Updated**: 2024
**Version**: 1.0
