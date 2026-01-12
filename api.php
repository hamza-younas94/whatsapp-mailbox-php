<?php
/**
 * WhatsApp Mailbox API Endpoints with Eloquent ORM
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Contact;
use App\Models\Message;
use App\Services\WhatsAppService;

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check authentication
if (!isAuthenticated()) {
    response_error('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$action = $request[0] ?? '';

try {
    switch ($action) {
        case 'contacts':
            if ($method === 'GET') {
                getContacts();
            }
            break;
            
        case 'messages':
            if ($method === 'GET') {
                getMessages();
            }
            break;
            
        case 'send':
            if ($method === 'POST') {
                sendMessage();
            }
            break;
            
        case 'mark-read':
            if ($method === 'POST') {
                markAsRead();
            }
            break;
            
        case 'search':
            if ($method === 'GET') {
                searchMessages();
            }
            break;
            
        default:
            response_error('Endpoint not found', 404);
    }
} catch (\Exception $e) {
    logger("API Error: " . $e->getMessage(), 'error');
    response_error('Internal server error', 500);
}

/**
 * Get all contacts with unread counts
 */
function getContacts() {
    $search = sanitize($_GET['search'] ?? '');
    
    $query = Contact::with('lastMessage')
        ->withCount(['unreadMessages as unread_count']);
    
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('phone_number', 'like', "%{$search}%");
        });
    }
    
    $contacts = $query->orderBy('last_message_time', 'desc')->get();
    
    // Format for response
    $formatted = $contacts->map(function($contact) {
        return [
            'id' => $contact->id,
            'phone_number' => $contact->phone_number,
            'name' => $contact->name,
            'profile_picture_url' => $contact->profile_picture_url,
            'last_message_time' => $contact->last_message_time?->format('Y-m-d H:i:s'),
            'unread_count' => $contact->unread_count,
            'last_message' => $contact->lastMessage?->message_body,
            'initials' => $contact->initials,
            // CRM fields
            'stage' => $contact->stage,
            'lead_score' => $contact->lead_score,
            'company_name' => $contact->company_name,
            'email' => $contact->email,
            'city' => $contact->city,
            'country' => $contact->country,
            'deal_value' => $contact->deal_value,
            'deal_currency' => $contact->deal_currency,
            'expected_close_date' => $contact->expected_close_date,
            'last_activity_at' => $contact->last_activity_at?->format('Y-m-d H:i:s'),
            'last_activity_type' => $contact->last_activity_type
        ];
    });
    
    response_json($formatted);
}

/**
 * Get messages for a specific contact
 */
function getMessages() {
    $contactId = $_GET['contact_id'] ?? null;
    
    if (!$contactId) {
        response_error('contact_id is required');
    }
    
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $messages = Message::where('contact_id', $contactId)
        ->orderBy('timestamp', 'desc')
        ->limit($limit)
        ->offset($offset)
        ->get()
        ->reverse()
        ->values();
    
    response_json($messages);
}

/**
 * Send a WhatsApp message
 */
function sendMessage() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $to = sanitize($input['to'] ?? '');
    $message = $input['message'] ?? '';
    $contactId = $input['contact_id'] ?? null;
    
    // Validate
    $validation = validate([
        'to' => $to,
        'message' => $message
    ], [
        'to' => 'required',
        'message' => 'required|max:4096'
    ]);
    
    if ($validation !== true) {
        response_error('Validation failed', 422, $validation);
    }
    
    // Send via WhatsApp API
    $whatsappService = new WhatsAppService();
    $result = $whatsappService->sendTextMessage($to, $message);
    
    if (!$result['success']) {
        response_error('Failed to send message', 500, ['details' => $result]);
    }
    
    // Get or create contact
    if (!$contactId) {
        $contact = Contact::firstOrCreate(
            ['phone_number' => $to],
            ['name' => $to]
        );
        $contactId = $contact->id;
    }
    
    // Save message to database
    $savedMessage = Message::create([
        'message_id' => $result['message_id'],
        'contact_id' => $contactId,
        'phone_number' => $to,
        'message_type' => 'text',
        'direction' => 'outgoing',
        'message_body' => $message,
        'timestamp' => now(),
        'is_read' => true,
        'status' => 'sent'
    ]);
    
    // Update contact last message time
    Contact::find($contactId)->update(['last_message_time' => now()]);
    
    response_json([
        'success' => true,
        'message_id' => $result['message_id'],
        'message' => $savedMessage
    ]);
}

/**
 * Mark messages as read
 */
function markAsRead() {
    $input = json_decode(file_get_contents('php://input'), true);
    $contactId = $input['contact_id'] ?? null;
    
    if (!$contactId) {
        response_error('contact_id is required');
    }
    
    $contact = Contact::find($contactId);
    
    if (!$contact) {
        response_error('Contact not found', 404);
    }
    
    $contact->markAllAsRead();
    
    response_json(['success' => true]);
}

/**
 * Search messages
 */
function searchMessages() {
    $query = sanitize($_GET['q'] ?? '');
    
    if (!$query) {
        response_json([]);
    }
    
    $results = Message::with('contact')
        ->search($query)
        ->orderBy('timestamp', 'desc')
        ->limit(50)
        ->get()
        ->map(function($message) {
            return [
                'id' => $message->id,
                'message_body' => $message->message_body,
                'timestamp' => $message->timestamp->format('Y-m-d H:i:s'),
                'contact_name' => $message->contact->name,
                'contact_phone' => $message->contact->phone_number,
                'direction' => $message->direction
            ];
        });
    
    response_json($results);
}
