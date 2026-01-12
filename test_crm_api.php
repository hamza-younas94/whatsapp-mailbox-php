<?php
/**
 * Test CRM API - Verify CRM fields are returned
 */
require_once 'bootstrap.php';

// Simulate authenticated session
$_SESSION['user_id'] = 1;

use App\Models\Contact;

echo "<h1>CRM API Test</h1>";
echo "<p>Testing if API returns CRM fields...</p>";

// Get first contact
$contact = Contact::first();

if (!$contact) {
    echo "<p style='color: red;'>❌ No contacts found in database</p>";
    exit;
}

echo "<h2>✅ Contact Found: {$contact->name}</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";

$fields = [
    'id' => $contact->id,
    'name' => $contact->name,
    'phone_number' => $contact->phone_number,
    'stage' => $contact->stage,
    'lead_score' => $contact->lead_score,
    'company_name' => $contact->company_name,
    'email' => $contact->email,
    'city' => $contact->city,
    'deal_value' => $contact->deal_value,
    'last_activity_at' => $contact->last_activity_at,
];

foreach ($fields as $field => $value) {
    $status = $value !== null ? '✅' : '❌';
    $displayValue = $value ?? '<em>NULL</em>';
    echo "<tr><td><strong>{$field}</strong></td><td>{$displayValue}</td><td>{$status}</td></tr>";
}

echo "</table>";

// Test API endpoint
echo "<h2>API Endpoint Test</h2>";
echo "<p>Testing: <code>/api.php/contacts</code></p>";

// Make a fake API call
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PATH_INFO'] = '/contacts';

ob_start();
include 'api.php';
$apiResponse = ob_get_clean();

echo "<h3>API Response (first 2000 chars):</h3>";
echo "<pre style='background: #f5f5f5; padding: 15px; overflow-x: auto;'>";
echo htmlspecialchars(substr($apiResponse, 0, 2000));
echo "</pre>";

// Check if response contains CRM fields
$hasCrmFields = strpos($apiResponse, '"stage"') !== false && 
                strpos($apiResponse, '"lead_score"') !== false && 
                strpos($apiResponse, '"company_name"') !== false;

if ($hasCrmFields) {
    echo "<p style='color: green; font-weight: bold;'>✅ API RESPONSE INCLUDES CRM FIELDS!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ API RESPONSE MISSING CRM FIELDS!</p>";
}

echo "<hr>";
echo "<h2>🔧 If API is missing CRM fields:</h2>";
echo "<ol>";
echo "<li>Check if <code>api.php</code> was updated</li>";
echo "<li>Run: <code>git pull origin main</code></li>";
echo "<li>Check file modification date: <code>stat api.php</code></li>";
echo "</ol>";

echo "<h2>🔧 If front-end not showing badges:</h2>";
echo "<ol>";
echo "<li>Hard refresh browser: <code>Ctrl+Shift+R</code> or <code>Cmd+Shift+R</code></li>";
echo "<li>Clear browser cache completely</li>";
echo "<li>Open in incognito/private window</li>";
echo "<li>Check JavaScript console for errors (F12)</li>";
echo "</ol>";
