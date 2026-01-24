# Form Validation Implementation Guide

## Overview
This document provides detailed information about all HTML forms in the WhatsApp Mailbox application and their corresponding POST handlers. Each form is listed with validation requirements and implementation recommendations.

---

## Forms Summary Table

| Form ID | File | Handler | Fields | Validation Status |
|---------|------|---------|--------|-------------------|
| quickReplyForm | quick-replies.php | AJAX | 20 fields | ✅ Implemented |
| workflowForm | workflows.php | AJAX | 6 fields | ✅ Implemented |
| campaignForm | drip-campaigns.php | AJAX | 5 fields | ✅ Implemented |
| broadcastForm | broadcasts.php | AJAX | 6 fields | ✅ Implemented |
| scheduleForm | scheduled-messages.php | AJAX | 3 fields | ✅ Implemented |
| templateForm | message-templates.php | AJAX | 7 fields | ✅ Implemented |
| ruleModal | auto_tag_rules.html.twig | AJAX | 6 fields | ❌ Needs Implementation |
| segmentForm | segments.php | AJAX | 4 fields | ✅ Implemented |
| tagForm | tags.php | AJAX | 3 fields | ✅ Implemented |
| userForm | users.php | AJAX | 8 fields | ✅ Implemented |
| webhookForm | webhook-manager.php | AJAX | 5 fields | ✅ Implemented |
| loginForm | login.html.twig | POST | 2 fields | ⚠️ Minimal |
| registerForm | register.php | POST | 5 fields | ✅ Implemented |
| settingsForm | user-settings.html.twig | POST | 5 fields | ⚠️ Minimal |

---

## Detailed Form Specifications

### 1. Quick Replies Form
**File:** `quick-replies.php`  
**Handler:** AJAX POST with action=create/update  
**Form ID:** quickReplyForm

#### Input Fields
```
shortcut (text) - keyword/shortcut text
title (text) - reply title/name
message (textarea) - actual message content
is_active (checkbox) - active status
priority (number) - match priority (1-100)
shortcuts (hidden JSON) - multiple shortcuts array
use_regex (checkbox) - enable regex matching
business_hours_start (time) - start time
business_hours_end (time) - end time
timezone (select) - timezone for business hours
outside_hours_message (textarea) - message outside hours
conditions (hidden JSON) - tag/stage conditions
delay_seconds (number) - response delay
media_url (hidden) - media URL
media_type (hidden) - image/video/document
media_filename (hidden) - filename
excluded_contact_ids (hidden JSON) - blacklist contacts
included_contact_ids (hidden JSON) - whitelist contacts
sequence_messages (hidden JSON) - sequential messages
sequence_delay_seconds (number) - delay between messages
allow_groups (checkbox) - group reply enabled
```

#### Validation Rules
```
shortcut: required, min 1 char, max 50 chars
title: required, min 2 chars, max 100 chars
message: required, min 1 char, max 4096 chars
```

#### Additional Validations
- priority must be numeric 0-100
- delay_seconds must be numeric >= 0
- timezone must be valid timezone string
- JSON fields (conditions, shortcuts, etc.) must be valid JSON
- contact IDs must be numeric or empty

---

### 2. Workflows Form
**File:** `workflows.php`  
**Handler:** AJAX POST with action=create/update  
**Form ID:** workflowForm

#### Input Fields
```
name (text) - workflow name
trigger_type (select) - trigger type (new_message, stage_change, tag_added, etc.)
trigger_conditions (hidden JSON) - conditions object
actions (hidden JSON) - array of actions
description (textarea) - workflow description
is_active (checkbox) - active status
```

#### Validation Rules
```
name: required, min 2 chars, max 150 chars
trigger_type: required
trigger_conditions: required, valid JSON
actions: required, non-empty array, at least 1 action
```

#### Additional Validations
- trigger_type must be from allowed list
- trigger_conditions must be valid JSON object
- actions must be valid JSON array with min length 1
- Verify action objects have required properties

---

### 3. Drip Campaigns Form
**File:** `drip-campaigns.php`  
**Handler:** AJAX POST with action=create/update  
**Form ID:** campaignForm

#### Input Fields
```
name (text) - campaign name
description (textarea) - campaign description
trigger_conditions (hidden JSON) - trigger conditions
steps (hidden JSON) - array of campaign steps
is_active (checkbox) - active status
```

#### Validation Rules
```
name: required, min 2 chars, max 150 chars
trigger_conditions: required, valid JSON
steps: required, non-empty array, at least 1 step
```

#### Additional Validations
- Each step in steps array must have: name, delay_minutes, message_type, message_content
- delay_minutes must be numeric >= 0
- message_type must be valid (text, image, document, etc.)
- template_id (if provided) must exist and belong to user

---

### 4. Broadcast Form
**File:** `broadcasts.php`  
**Handler:** AJAX POST with action=create/update  
**Form ID:** broadcastForm

#### Input Fields
```
name (text) - broadcast name
recipient_filter (select) - filter: all, tag_*, segment_*, stage_*
message (textarea) - broadcast message
message_type (select) - text, template, image, etc.
template_name (text) - WhatsApp template name (if using templates)
scheduled_at (datetime) - schedule for later (optional)
```

#### Validation Rules
```
name: required, min 2 chars, max 100 chars
recipient_filter: required
message: required, min 1 char, max 4096 chars
```

#### Additional Validations
- recipient_filter must resolve to valid recipients
- recipient count must be > 0
- If template is used, must exist
- scheduled_at (if provided) must be future date/time
- Message type must be valid

---

### 5. Scheduled Messages Form
**File:** `scheduled-messages.php`  
**Handler:** AJAX POST with action=create  
**Form ID:** scheduleForm

#### Input Fields
```
contact_id (select) - target contact
message (textarea) - message content
scheduled_at (datetime-local) - schedule time
```

#### Validation Rules
```
contact_id: required, must belong to current user
message: required, min 1 char, max 4096 chars
scheduled_at: required, must be future date/time
```

#### Additional Validations
- contact_id must exist and belong to user
- scheduled_at must be greater than current time
- Message must not be empty after sanitization
- Timezone consideration (currently Pakistan time)

---

### 6. Message Templates Form
**File:** `message-templates.php`  
**Handler:** AJAX POST with action=create/update  
**Form ID:** templateForm

#### Input Fields
```
name (text) - template name
whatsapp_template_name (text) - exact WhatsApp template name
language_code (select) - language code (en, es, fr, etc.)
content (textarea) - template content with {{1}}, {{2}} variables
category (text) - template category
status (select) - pending, approved, rejected
```

#### Validation Rules
```
name: required, min 2 chars, max 150 chars
whatsapp_template_name: required, min 1 char, max 100 chars
language_code: required, max 10 chars
content: required, min 1 char
```

#### Additional Validations
- language_code must be from predefined list
- content must be parseable for {{N}} variables
- Variables must be sequential ({{1}}, {{2}}, not {{1}}, {{3}})
- status must be in (pending, approved, rejected)
- Category optional but max 100 chars if provided

---

### 7. Auto-Tag Rules Form
**File:** `templates/auto_tag_rules.html.twig`  
**Handler:** AJAX POST (auto_tag_rules.php) - needs implementation  
**Form ID:** ruleModal

#### Input Fields
```
ruleName (text) - rule name
ruleTagId (select) - tag to apply
ruleKeywords (textarea) - keywords one per line
ruleMatchType (select) - any, all, exact
rulePriority (number) - priority 1-100
ruleEnabled (checkbox) - rule active status
```

#### Validation Rules (TO IMPLEMENT)
```
ruleName: required, min 2 chars, max 100 chars
ruleTagId: required, must exist and belong to user
ruleKeywords: required, min 1 keyword
ruleMatchType: required, in (any, all, exact)
rulePriority: required, integer 1-100
```

#### Additional Validations Needed
- Validate each keyword is non-empty
- Ensure tag exists and user has access
- ruleMatchType must be from allowed values
- rulePriority must be numeric 1-100
- Check for duplicate rule names

---

### 8. Segments Form
**File:** `segments.php`  
**Handler:** AJAX POST with action=create/update  
**Form ID:** segmentForm

#### Input Fields
```
name (text) - segment name
description (textarea) - segment description
conditions (hidden JSON) - filter conditions
is_dynamic (checkbox) - auto-update segment
```

#### Validation Rules
```
name: required, min 2 chars, max 100 chars
description: max 500 chars
conditions: required, valid JSON, not empty
```

#### Additional Validations
- Conditions must be valid JSON object
- Conditions must have at least 1 rule
- Field values in conditions must be valid
- Valid condition fields: stage, lead_score, last_message_days, tags
- Operators must be valid: =, >, <, in

---

### 9. Tags Form
**File:** `tags.php`  
**Handler:** AJAX POST with action=create/update  
**Form ID:** tagForm

#### Input Fields
```
name (text) - tag name
color (color) - hex color code
description (textarea) - tag description
```

#### Validation Rules
```
name: required, min 2 chars, max 50 chars
description: max 255 chars
```

#### Additional Validations
- color must be valid hex color or default #25D366
- Name must be unique per user
- Description optional

---

### 10. User Management Form
**File:** `users.php`  
**Handler:** AJAX POST with action=create/update  
**Form ID:** userForm

#### Input Fields
```
username (text) - login username
email (text) - email address
full_name (text) - full name
password (password) - password (required on create, optional on update)
role (select) - admin, agent, viewer
is_active (checkbox) - user active
phone (text) - phone number
subscription_plan (select) - free, starter, pro, enterprise
```

#### Validation Rules
```
username: required, min 3 chars, max 50 chars, unique
email: required, valid email format, unique
full_name: required, min 2 chars, max 100 chars
password: required on create, min 6 chars on update if provided
role: required, in (admin, agent, viewer)
```

#### Additional Validations
- Check username uniqueness (excluding self on update)
- Check email uniqueness (excluding self on update)
- Password must be hashed before storage
- Role must be from allowed list
- Prevent deleting own account
- Prevent deactivating own account

---

### 11. Webhook Manager Form
**File:** `webhook-manager.php`  
**Handler:** AJAX POST with action=create/update  
**Form ID:** webhookForm

#### Input Fields
```
name (text) - webhook name
url (text) - webhook URL
events (hidden JSON) - array of events to subscribe to
secret (text) - webhook secret (auto-generated if empty)
is_active (checkbox) - webhook active
```

#### Validation Rules
```
name: required, min 2 chars, max 150 chars
url: required, valid URL format
events: required, non-empty array, at least 1 event
```

#### Additional Validations
- URL must be valid HTTPS or HTTP
- URL must be reachable/valid format
- events must be from allowed list
- events array must have min 1 item
- secret auto-generated if not provided
- secret must be encrypted before storage

---

### 12. Login Form
**File:** `templates/login.html.twig` → `login.php`  
**Handler:** POST (traditional form submission)  
**Form ID:** loginForm

#### Input Fields
```
username (text) - login username
password (password) - password
```

#### Current Validation
```
username: required
password: required
```

#### Validation Needed
```
username: required, not empty, trim whitespace
password: required, not empty
(Both validated only at backend via login() function)
```

#### Recommendations
- Add minimum length validation
- Add rate limiting for failed attempts
- Add CSRF token validation
- Add "remember me" functionality validation
- Sanitize username input

---

### 13. Registration Form
**File:** `register.php`  
**Handler:** POST (traditional form submission)  
**Form ID:** registerForm

#### Input Fields
```
username (text) - login username
email (text) - email address
business_name (text) - company name
password (password) - password
confirm_password (password) - confirm password
```

#### Current Validation
```
username: required
email: required, valid email format
password: required, min 8 chars
confirm_password: required, must match password
business_name: required
```

#### Additional Validations
- Check username uniqueness
- Check email uniqueness
- Password strength validation (uppercase, number, special char)
- CSRF token validation
- Verify email format properly

---

### 14. User Settings Form
**File:** `templates/user-settings.html.twig` → `user-settings.php`  
**Handler:** POST (traditional form submission)  
**Form ID:** settingsForm

#### Input Fields
```
business_name (text) - business name
phone_number (tel) - phone number
whatsapp_access_token (password) - WhatsApp API token
whatsapp_phone_number_id (text) - phone number ID
whatsapp_api_version (select) - API version
```

#### Current Validation
```
Minimal - basic assignment only
```

#### Validation Needed
```
business_name: required, min 2 chars, max 100 chars
phone_number: required, valid phone format
whatsapp_access_token: required, min 50 chars (token format)
whatsapp_phone_number_id: required, numeric, 15+ digits
whatsapp_api_version: required, in (v18.0, v17.0, v16.0)
```

#### Additional Validations
- Token must match WhatsApp API token format
- Phone ID must be numeric only
- Validate API version against supported versions
- Test API credentials before saving
- Encrypt sensitive fields
- Add webhook URL validation

---

## Implementation Priority

### CRITICAL (Missing Validation)
1. **Auto-Tag Rules** (ruleModal) - Complete implementation needed
2. **User Settings** (settingsForm) - Enhance validation for API credentials

### HIGH (Minimal Validation)
1. **Login Form** - Add rate limiting and better error handling
2. **Settings Form** - Add credential validation and testing

### MEDIUM (Consistency)
1. Add client-side validation to all AJAX forms
2. Standardize error message formats
3. Add CSRF token to all forms
4. Implement proper error logging

---

## Validation Function Usage

All AJAX forms use the centralized `validate()` function:

```php
$validation = validate([
    'field_name' => sanitize($_POST['field_name'] ?? ''),
    // ... more fields
], [
    'field_name' => 'required|min:2|max:100',
    // ... more rules
]);

if ($validation !== true) {
    echo json_encode([
        'success' => false,
        'error' => 'Validation failed',
        'errors' => $validation
    ]);
    exit;
}
```

---

## Common Validation Rules

| Rule | Format | Example |
|------|--------|---------|
| Required | `required` | `'name' => 'required'` |
| Min Length | `min:N` | `'password' => 'min:6'` |
| Max Length | `max:N` | `'email' => 'max:255'` |
| Email | `email` | `'email' => 'email'` |
| URL | `url` | `'webhook_url' => 'url'` |
| Numeric | `numeric` | `'priority' => 'numeric'` |
| In List | `in:val1,val2` | `'role' => 'in:admin,agent'` |
| Match Field | `match:fieldname` | `'confirm' => 'match:password'` |

---

## Error Response Format

All AJAX handlers return JSON with consistent format:

```json
{
  "success": true/false,
  "message": "optional success message",
  "error": "optional error message",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"],
    "another_field": ["Error message"]
  },
  "data": {}
}
```

---

## Security Recommendations

1. **CSRF Protection**: Add CSRF tokens to all forms
2. **Input Sanitization**: Use `sanitize()` function on all inputs
3. **SQL Injection**: Use Eloquent ORM (already implemented)
4. **XSS Prevention**: Use `htmlspecialchars()` on output
5. **Rate Limiting**: Implement for login and registration
6. **Password Hashing**: Use password_hash() and password_verify()
7. **Sensitive Data**: Encrypt API tokens and credentials
8. **Validation**: Server-side validation is critical, client-side is convenience

---

## Testing Checklist

- [ ] Test all forms with empty values
- [ ] Test with SQL injection attempts
- [ ] Test with XSS payloads
- [ ] Test with very long inputs (exceed max length)
- [ ] Test with special characters
- [ ] Test with valid edge cases (0, empty string, null)
- [ ] Test concurrent form submissions
- [ ] Test form with missing required fields
- [ ] Test form with invalid field types
- [ ] Test form with JSON parsing errors

---

## Next Steps

1. Create centralized FormValidator class
2. Implement auto-tag rules validation
3. Enhance user settings validation
4. Add client-side validation library
5. Implement CSRF token support
6. Add comprehensive error logging
7. Create form testing suite
