<?php
/**
 * WhatsApp Mailbox API Endpoints with Eloquent ORM
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Contact;
use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Database\Capsule\Manager as Capsule;

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
            
        case 'send-template':
            if ($method === 'POST') {
                sendTemplateMessage();
            }
            break;
            
        case 'message-limit':
            if ($method === 'GET') {
                getMessageLimit();
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
            // CRM fields with defaults
            'stage' => $contact->stage ?: 'new',
            'lead_score' => $contact->lead_score ?: 0,
            'company_name' => $contact->company_name,
            'email' => $contact->email,
            'city' => $contact->city,
            'country' => $contact->country,
            'deal_value' => $contact->deal_value,
            'deal_currency' => $contact->deal_currency ?: 'USD',
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
    // Check message limit
    $messagesSent = (int)Capsule::table('config')
        ->where('config_key', 'messages_sent_count')
        ->value('config_value') ?? 0;
    
    $messageLimit = (int)Capsule::table('config')
        ->where('config_key', 'message_limit')
        ->value('config_value') ?? 500;
    
    if ($messagesSent >= $messageLimit) {
        response_error('Message limit reached. You have sent ' . $messagesSent . ' out of ' . $messageLimit . ' free messages. Please upgrade your plan to continue.', 429);
    }
    
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
        // Check if it's a 24-hour window error
        $errorMsg = $result['error'] ?? '';
        $response = $result['response'] ?? '';
        
        if (strpos($errorMsg, '400') !== false || strpos($response, '"code":100') !== false || strpos($response, 'Invalid parameter') !== false) {
            response_error('Cannot send message: This contact has not messaged you recently. WhatsApp requires the contact to message you first, or you need to use a template message.', 403, ['details' => $result]);
        }
        
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
    
    // Increment message counter
    Capsule::table('config')
        ->where('config_key', 'messages_sent_count')
        ->increment('config_value');
    
    // Get updated count
    $newCount = (int)Capsule::table('config')
        ->where('config_key', 'messages_sent_count')
        ->value('config_value') ?? 0;
    
    $limit = (int)Capsule::table('config')
        ->where('config_key', 'message_limit')
        ->value('config_value') ?? 500;
    
    response_json([
        'success' => true,
        'message_id' => $result['message_id'],
        'message' => $savedMessage,
        'messages_remaining' => max(0, $limit - $newCount)
    ]);
}

/**
 * Send template message (for starting new conversations)
 */
function sendTemplateMessage() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $to = sanitize($input['to'] ?? '');
    $templateName = sanitize($input['template_name'] ?? '');
    $languageCode = sanitize($input['language_code'] ?? 'en');
    $contactId = $input['contact_id'] ?? null;
    
    // Validate
    if (!$to || !$templateName) {
        response_error('Phone number and template name are required', 422);
    }
    
    // Send via WhatsApp API
    $whatsappService = new WhatsAppService();
    $result = $whatsappService->sendTemplateMessage($to, $templateName, $languageCode);
    
    if (!$result['success']) {
        response_error('Failed to send template message', 500, ['details' => $result]);
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
        'message_type' => 'template',
        'direction' => 'outgoing',
        'message_body' => "Template: {$templateName}",
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

/**
 * Get message limit and current count
 */
function getMessageLimit() {
    $messagesSent = (int)Capsule::table('config')
        ->where('config_key', 'messages_sent_count')
        ->value('config_value') ?? 0;
    
    $messageLimit = (int)Capsule::table('config')
        ->where('config_key', 'message_limit')
        ->value('config_value') ?? 500;
    
    response_json([
        'sent' => $messagesSent,
        'limit' => $messageLimit,
        'remaining' => max(0, $messageLimit - $messagesSent),
        'percentage' => $messageLimit > 0 ? round(($messagesSent / $messageLimit) * 100, 1) : 0
    ]);
}
