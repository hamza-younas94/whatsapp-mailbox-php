# Form Validation Quick Reference

## Forms Status at a Glance

```
✅ FULLY VALIDATED:
  - Quick Replies (quick-replies.php)
  - Workflows (workflows.php)
  - Drip Campaigns (drip-campaigns.php)
  - Broadcasts (broadcasts.php)
  - Scheduled Messages (scheduled-messages.php)
  - Message Templates (message-templates.php)
  - Segments (segments.php)
  - Tags (tags.php)
  - Users (users.php)
  - Webhooks (webhook-manager.php)
  - Registration (register.php)

⚠️  NEEDS IMPLEMENTATION:
  - Auto-Tag Rules (auto_tag_rules.html.twig) - NO VALIDATION
  - User Settings (user-settings.html.twig) - MINIMAL VALIDATION

⚠️  NEEDS ENHANCEMENT:
  - Login Form (login.html.twig) - BASIC VALIDATION ONLY
```

---

## Implementation Commands by Priority

### Priority 1: Auto-Tag Rules Validation
**File:** `auto_tag_rules.html.twig` and backend handler

**Add to Twig form validation:**
```javascript
// In templates/auto_tag_rules.html.twig
document.addEventListener('DOMContentLoaded', function() {
    const validator = new FormValidator('ruleModal', {
        ruleName: ['required', 'min:2', 'max:100'],
        ruleTagId: ['required'],
        ruleKeywords: ['required', 'min:1'],
        ruleMatchType: ['required', 'in:any,all,exact'],
        rulePriority: ['required', 'integer', 'min:1', 'max:100']
    });
});
```

**Add to backend handler:**
```php
// In auto_tag_rules.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
        case 'update':
            $validation = validate([
                'name' => sanitize($_POST['name'] ?? ''),
                'tag_id' => $_POST['tag_id'] ?? '',
                'keywords' => $_POST['keywords'] ?? '',
                'match_type' => sanitize($_POST['match_type'] ?? ''),
                'priority' => $_POST['priority'] ?? ''
            ], [
                'name' => 'required|min:2|max:100',
                'tag_id' => 'required',
                'keywords' => 'required|min:1',
                'match_type' => 'required|in:any,all,exact',
                'priority' => 'required|numeric|min:1|max:100'
            ]);
            
            if ($validation !== true) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validation
                ]);
                exit;
            }
            
            // Process the rule...
            break;
    }
}
```

---

### Priority 2: User Settings Validation
**File:** `user-settings.html.twig` and `user-settings.php`

**Add to Twig form:**
```html
<form method="POST" class="needs-validation" novalidate>
    <div class="mb-3">
        <label class="form-label">Business Name *</label>
        <input type="text" name="business_name" class="form-control" 
               required minlength="2" maxlength="100"
               value="{{ settings.business_name ?? '' }}">
        <div class="invalid-feedback">Business name is required (2-100 chars)</div>
    </div>
    
    <div class="mb-3">
        <label class="form-label">Phone Number *</label>
        <input type="tel" name="phone_number" class="form-control" 
               required pattern="^[\d\s\-\+\(\)]+$"
               value="{{ settings.phone_number ?? '' }}">
        <div class="invalid-feedback">Valid phone number required</div>
    </div>
    
    <div class="mb-3">
        <label class="form-label">WhatsApp Access Token *</label>
        <input type="password" name="whatsapp_access_token" class="form-control" 
               required minlength="50"
               value="{{ settings.whatsapp_access_token ?? '' }}">
        <div class="invalid-feedback">Token must be at least 50 characters</div>
    </div>
    
    <div class="mb-3">
        <label class="form-label">Phone Number ID *</label>
        <input type="text" name="whatsapp_phone_number_id" class="form-control" 
               required pattern="^\d{15,}$"
               value="{{ settings.whatsapp_phone_number_id ?? '' }}">
        <div class="invalid-feedback">Must be numeric, 15+ digits</div>
    </div>
    
    <div class="mb-3">
        <label class="form-label">API Version</label>
        <select name="whatsapp_api_version" class="form-select" required>
            <option value="v18.0" {% if settings.whatsapp_api_version == 'v18.0' %}selected{% endif %}>v18.0</option>
            <option value="v17.0" {% if settings.whatsapp_api_version == 'v17.0' %}selected{% endif %}>v17.0</option>
            <option value="v16.0" {% if settings.whatsapp_api_version == 'v16.0' %}selected{% endif %}>v16.0</option>
        </select>
    </div>
</form>
```

**Add to backend validation:**
```php
// In user-settings.php after POST handling
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName = trim($_POST['business_name'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $token = trim($_POST['whatsapp_access_token'] ?? '');
    $phoneId = trim($_POST['whatsapp_phone_number_id'] ?? '');
    $apiVersion = sanitize($_POST['whatsapp_api_version'] ?? '');
    
    // Validate business name
    if (empty($businessName) || strlen($businessName) < 2 || strlen($businessName) > 100) {
        $errors['business_name'] = 'Business name must be 2-100 characters';
    }
    
    // Validate phone number
    if (empty($phoneNumber) || !preg_match('/^[\d\s\-\+\(\)]+$/', $phoneNumber)) {
        $errors['phone_number'] = 'Enter a valid phone number';
    }
    
    // Validate access token
    if (empty($token) || strlen($token) < 50) {
        $errors['whatsapp_access_token'] = 'Access token must be at least 50 characters';
    }
    
    // Validate phone ID
    if (empty($phoneId) || !preg_match('/^\d{15,}$/', $phoneId)) {
        $errors['whatsapp_phone_number_id'] = 'Phone ID must be numeric, 15+ digits';
    }
    
    // Validate API version
    if (!in_array($apiVersion, ['v18.0', 'v17.0', 'v16.0'])) {
        $errors['whatsapp_api_version'] = 'Invalid API version';
    }
    
    if (!empty($errors)) {
        $messageType = 'danger';
        $message = 'Validation errors found. Please correct them.';
    } else {
        // Process valid data
        $data = [
            'whatsapp_access_token' => Encryption::encrypt($token),
            'whatsapp_phone_number_id' => Encryption::encrypt($phoneId),
            'phone_number' => $phoneNumber,
            'business_name' => $businessName,
            'whatsapp_api_version' => $apiVersion
        ];
        
        if (!empty($token) && !empty($phoneId)) {
            $data['is_configured'] = true;
        }
        
        $userSettings->update($data);
        $messageType = 'success';
        $message = 'Settings updated successfully!';
    }
}
```

---

### Priority 3: Login Form Enhancement
**File:** `login.html.twig` and `login.php`

**Add validation and security:**
```php
// In login.php
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_TIME = 900; // 15 minutes

$error = '';
$clientIp = $_SERVER['REMOTE_ADDR'];
$sessionKey = "login_attempts_$clientIp";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check rate limiting
    $attempts = $_SESSION[$sessionKey] ?? 0;
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $error = 'Too many login attempts. Please try again later.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($username) || empty($password)) {
            $error = 'Username and password required';
        } elseif (strlen($username) < 3 || strlen($password) < 6) {
            $error = 'Invalid credentials';
        } else {
            if (login($username, $password)) {
                $_SESSION[$sessionKey] = 0; // Reset attempts on success
                header('Location: index.php');
                exit;
            } else {
                $_SESSION[$sessionKey] = $attempts + 1;
                $error = 'Invalid username or password';
            }
        }
    }
}
```

---

## Validation Rules Reference

### Text Fields
```
required          - Field must have a value
min:N             - Minimum N characters
max:N             - Maximum N characters
regex:pattern     - Match regex pattern
```

### Email Fields
```
required          - Field must have a value
email             - Valid email format
max:255           - Maximum 255 characters
```

### Number Fields
```
required          - Field must have a value
numeric           - Must be numeric
min:N             - Minimum value N
max:N             - Maximum value N
integer           - Must be integer
```

### Select/Choice Fields
```
required          - Field must have a value
in:val1,val2,val3 - Value must be in list
```

### Password Fields
```
required          - Field must have a value
min:6             - Minimum 6 characters
match:fieldname   - Must match another field
```

### Complex/JSON Fields
```
required          - Field must have a value
json              - Valid JSON format
```

---

## Error Handling Pattern

All AJAX forms should handle errors consistently:

```javascript
fetch(url, {
    method: 'POST',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new FormData(form)
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        showToast('Success!', 'success');
        // Handle success
    } else {
        // Display field-level errors
        if (data.errors) {
            Object.keys(data.errors).forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('is-invalid');
                    const feedback = input.nextElementSibling;
                    if (feedback) {
                        feedback.textContent = data.errors[field].join(', ');
                    }
                }
            });
        }
        showToast(data.error || 'An error occurred', 'error');
    }
})
.catch(error => {
    console.error('Error:', error);
    showToast('An error occurred', 'error');
});
```

---

## Testing Form Validation

### Test Cases for Every Form

1. **Empty/Required Fields**
   - Submit form with empty required fields
   - Should show validation error

2. **Min/Max Length**
   - Submit with text less than minimum
   - Submit with text more than maximum
   - Should show appropriate error

3. **Format Validation**
   - Email: test "abc@def" (invalid)
   - URL: test "not a url" (invalid)
   - Phone: test "letters" (invalid)

4. **Allowed Values**
   - For select fields, try invalid options
   - For role/status fields, try values not in list

5. **JSON Fields**
   - Try invalid JSON syntax
   - Should catch JSON parsing errors

6. **Numeric Fields**
   - Try text in number fields
   - Try negative values where min > 0

---

## Implementation Checklist

### For Each Form Needing Validation:

- [ ] Create validation rules in backend
- [ ] Add client-side HTML5 validation attributes
- [ ] Add JavaScript form validation
- [ ] Create test cases
- [ ] Document validation in code comments
- [ ] Add error messages for each field
- [ ] Test with invalid inputs
- [ ] Test with edge cases (0, empty, null)
- [ ] Test with SQL injection attempts
- [ ] Test with XSS payloads
- [ ] Update documentation

---

## File Locations

### Key Files to Modify

1. **Backend Validation:**
   - `bootstrap.php` - Contains `validate()` function
   - Individual form handlers (*.php files)

2. **Frontend Validation:**
   - `templates/*.html.twig` - Form HTML
   - `assets/js/validation.js` - Client-side validation
   - Individual form scripts

3. **Documentation:**
   - This file - Implementation guide
   - `FORMS_VALIDATION_AUDIT.json` - Complete form audit

---

## Support Functions

### Sanitization
```php
sanitize($input)  // Remove/escape dangerous characters
```

### Validation
```php
validate($data, $rules)  // Validate array against rules
```

### Encryption
```php
Encryption::encrypt($data)   // Encrypt sensitive data
Encryption::decrypt($data)   // Decrypt sensitive data
```

---

## Resources

- [OWASP Form Validation Guidelines](https://cheatsheetseries.owasp.org/cheatsheets/Input_Validation_Cheat_Sheet.html)
- [HTML5 Validation Attributes](https://developer.mozilla.org/en-US/docs/Learn/Forms/Form_validation)
- [PHP Filter Functions](https://www.php.net/manual/en/function.filter-var.php)
- [Regular Expressions](https://www.regular-expressions.info/tutorial.html)
