# Form Validation Guide

## Overview

This application includes a comprehensive form validation system with both frontend (JavaScript) and backend (PHP) validation. All forms automatically get Bootstrap-styled validation feedback without additional code.

## Backend Validation (PHP)

### Using the Validation Class

```php
<?php
use App\Validation;

// Get form data
$input = json_decode(file_get_contents('php://input'), true);

// Define validation rules
$rules = [
    'email' => 'required|email',
    'name' => 'required|min:3|max:50',
    'phone' => 'required|phone',
    'age' => 'numeric|min:18',
    'website' => 'url',
    'password' => 'required|min:8|confirmed',
    'password_confirmation' => 'required',
    'role' => 'required|in:admin,user,moderator',
];

// Create validator and validate
$validator = new Validation($input);
if (!$validator->validate($rules)) {
    http_response_code(422);
    echo json_encode([
        'error' => 'Validation failed',
        'errors' => $validator->errors()
    ]);
    exit;
}

// Data is valid, continue processing
$sanitized = Validation::sanitize($input);
```

### Available Validation Rules

| Rule | Description | Example |
|------|-------------|---------|
| `required` | Field must have a value | `name\|required` |
| `email` | Must be valid email address | `email\|email` |
| `url` | Must be valid URL | `website\|url` |
| `min:n` | Minimum n characters | `password\|min:8` |
| `max:n` | Maximum n characters | `name\|max:50` |
| `numeric` | Must be a number | `price\|numeric` |
| `integer` | Must be an integer | `count\|integer` |
| `phone` | Valid phone number (10-15 digits) | `phone\|phone` |
| `regex:pattern` | Match regex pattern | `code\|regex:/^[A-Z0-9]{5}$/` |
| `unique:table,column` | Value must be unique in DB | `email\|unique:users,email` |
| `confirmed` | Must match field_confirmation | `password\|confirmed` |
| `in:val1,val2` | Value must be in list | `status\|in:active,inactive` |
| `array` | Must be an array | `tags\|array` |
| `string` | Must be a string | `description\|string` |

### Combining Rules

Rules are combined with pipe separators:

```php
$rules = [
    'email' => 'required|email|unique:users,email',
    'password' => 'required|min:8|confirmed',
    'age' => 'numeric|min:18|max:120',
];
```

## Frontend Validation (JavaScript)

### Method 1: Using data-validate Attribute (Recommended)

```html
<form method="POST" action="/api/users" data-validate='{"email":"required|email","name":"required|min:3"}'>
    <div class="mb-3">
        <label for="name" class="form-label">Name *</label>
        <input type="text" class="form-control" id="name" name="name" required>
    </div>
    
    <div class="mb-3">
        <label for="email" class="form-label">Email *</label>
        <input type="email" class="form-control" id="email" name="email" required>
    </div>
    
    <button type="submit" class="btn btn-primary">Submit</button>
</form>
```

### Method 2: JavaScript Initialization

```javascript
const validator = new FormValidator('myForm', {
    email: 'required|email',
    name: 'required|min:3|max:50',
    phone: 'phone'
});
```

### Method 3: Using data-validation Attributes

```html
<input 
    type="text" 
    name="email" 
    class="form-control"
    data-validation="required|email"
    placeholder="Your email"
>
```

## Bootstrap Styling

The validation system automatically applies Bootstrap classes:

- **Valid fields**: `.is-valid` class added
- **Invalid fields**: `.is-invalid` class added  
- **Error messages**: `.invalid-feedback` div displayed below field
- **Colors**: Red (#dc3545) for errors, Green (#198754) for valid

### Custom Error Messages

```php
$validator = new Validation($input);

// Get specific field error
$emailError = $validator->first('email'); // Returns first error message

// Get all field errors
$allErrors = $validator->errors(); // Returns array of all errors

// Check if field has error
if ($validator->hasError('email')) {
    // Do something
}
```

## Complete Example: Quick Reply Form

### HTML (forms/quick-reply.html)

```html
<div class="modal fade" id="quickReplyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Quick Reply</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="quickReplyForm" method="POST" data-validate='{"shortcut":"required|min:2","title":"required|min:3|max:50","message":"required|min:10|max:500"}'>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="shortcut" class="form-label">Shortcut Keyword *</label>
                        <input type="text" class="form-control" id="shortcut" name="shortcut" placeholder="e.g., hello">
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Welcome Message">
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="4" placeholder="Enter your quick reply..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Quick Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/validation.js"></script>
```

### PHP Backend (api.php or quick-replies.php)

```php
<?php
use App\Validation;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST; // or json_decode() for JSON
    
    // Validate
    $rules = [
        'shortcut' => 'required|min:2|max:20',
        'title' => 'required|min:3|max:50',
        'message' => 'required|min:10|max:500',
    ];
    
    $validator = new Validation($input);
    if (!$validator->validate($rules)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'errors' => $validator->errors()
        ]);
        exit;
    }
    
    // Sanitize
    $data = Validation::sanitize($input);
    
    // Save to database
    QuickReply::create([
        'user_id' => $_SESSION['user_id'],
        'shortcut' => $data['shortcut'],
        'title' => $data['title'],
        'message' => $data['message']
    ]);
    
    echo json_encode(['success' => true]);
}
```

## Real-Time Validation Features

✅ **Automatic validation on blur** - Fields validate when user leaves the field  
✅ **Live feedback** - Red/green styling as user types  
✅ **Smart error messages** - Context-aware, readable messages  
✅ **Form-level validation** - Submit button disabled until form is valid  
✅ **Bootstrap integration** - Works seamlessly with Bootstrap 5  

## JavaScript API

```javascript
// Create validator
const validator = new FormValidator('#myForm', rules);

// Validate entire form
const isValid = validator.validateForm(); // Returns true/false

// Validate single field
const input = document.querySelector('#email');
validator.validateField(input); // Validates and updates UI

// Disable form submission
validator.disableSubmit(true); // Disable submit button

// Get validation rules from form
const rules = createValidationRules();

// Check field validity
const hasError = document.querySelector('#email').classList.contains('is-invalid');
```

## Error Handling

### Display Errors in Modal/Alert

```javascript
// When form submission fails
fetch('/api/quick-replies', {
    method: 'POST',
    body: new FormData(form)
})
.then(res => res.json())
.then(data => {
    if (!data.success && data.errors) {
        // Display errors
        Object.entries(data.errors).forEach(([field, messages]) => {
            const input = document.querySelector(`[name="${field}"]`);
            if (input) {
                input.classList.add('is-invalid');
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback d-block';
                feedback.textContent = messages[0];
                input.parentNode.appendChild(feedback);
            }
        });
    }
});
```

## Best Practices

1. **Always validate on both frontend and backend** - Never trust client-side validation alone
2. **Use consistent rules** - Define rules in PHP, sync with frontend via data attributes
3. **Sanitize all input** - Use `Validation::sanitize()` before storing in database
4. **Show friendly messages** - Use field labels instead of technical names
5. **Disable submit during request** - Prevent double submissions
6. **Test with invalid data** - Verify all validation rules work correctly

## Troubleshooting

### Validation not working?

1. Check that `assets/js/validation.js` is loaded
2. Ensure form has `data-validate` attribute with valid JSON
3. Verify field names match form inputs exactly
4. Check browser console for JavaScript errors

### Error messages not showing?

1. Ensure form has proper Bootstrap classes
2. Check that invalid-feedback divs are being created
3. Verify CSS is loading correctly
4. Check that form submission is prevented

### Backend validation failing?

1. Check that `App\Validation` is properly imported
2. Verify validation rules are correct syntax
3. Ensure input data is passed correctly to validator
4. Check database connection for unique rule validation

## Future Enhancements

- [ ] Custom validation messages
- [ ] Async validation (check username availability)
- [ ] File upload validation (MIME type, size)
- [ ] Dynamic form fields
- [ ] Conditional validation based on other fields
