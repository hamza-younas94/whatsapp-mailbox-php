<?php
/**
 * WhatsApp Mailbox API Endpoints with Eloquent ORM
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Contact;
use App\Models\Message;
use App\Models\Task;
use App\Models\MessageAction;
use App\Models\ContactMerge;
use App\Models\Activity;
use App\Models\Note;
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
            
        case 'send-media':
            if ($method === 'POST') {
                sendMediaMessage();
            }
            break;
            
        case 'send-template':
            if ($method === 'POST') {
                sendTemplateMessage();
            }
            break;
            
        case 'templates':
            if ($method === 'GET') {
                getTemplates();
            }
            break;
            
        case 'users':
            if ($method === 'GET' && isset($request[1])) {
                getUser($request[1]);
            } elseif ($method === 'GET') {
                getUsers();
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
            
        case 'contact-timeline':
            if ($method === 'GET' && isset($request[1])) {
                getContactTimeline($request[1]);
            }
            break;
            
        case 'tasks':
            if ($method === 'GET') {
                getTasks();
            } elseif ($method === 'POST') {
                createTask();
            } elseif ($method === 'PUT' && isset($request[1])) {
                updateTask($request[1]);
            } elseif ($method === 'DELETE' && isset($request[1])) {
                deleteTask($request[1]);
            }
            break;
            
        case 'message-action':
            if ($method === 'POST') {
                handleMessageAction();
            } elseif ($method === 'DELETE' && isset($request[1])) {
                removeMessageAction($request[1]);
            }
            break;
            
        case 'contact-merge':
            if ($method === 'POST') {
                mergeContacts();
            }
            break;
            
        case 'duplicate-contacts':
            if ($method === 'GET') {
                findDuplicateContacts();
            }
            break;
            
        case 'auto-tag-rules':
            if ($method === 'GET' && !isset($request[1])) {
                getAutoTagRules();
            } elseif ($method === 'POST') {
                createAutoTagRule();
            } elseif ($method === 'PUT' && isset($request[1])) {
                updateAutoTagRule($request[1]);
            } elseif ($method === 'DELETE' && isset($request[1])) {
                deleteAutoTagRule($request[1]);
            }
            break;
            
        case 'tags':
            if ($method === 'GET') {
                getTags();
            }
            break;
            
        case 'bulk-tag':
            if ($method === 'POST') {
                bulkAddTag();
            }
            break;
            
        case 'bulk-stage':
            if ($method === 'POST') {
                bulkUpdateStage();
            }
            break;
            
        case 'bulk-delete':
            if ($method === 'POST') {
                bulkDeleteContacts();
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
    
    $query = Contact::with(['lastMessage', 'contactTags'])
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
            'last_activity_type' => $contact->last_activity_type,
            // Tags via pivot
            'tags' => $contact->contactTags->map(function($tag){
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color
                ];
            })->values()->all(),
            'tag_ids' => $contact->contactTags->pluck('id')->values()->all()
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
    $afterId = intval($_GET['after_id'] ?? 0);
    
    $query = Message::where('contact_id', $contactId);
    
    if ($afterId > 0) {
        $messages = $query
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get();
        response_json($messages);
        return;
    }
    
    $messages = $query
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
 * Send media message (image, video, document, audio)
 */
function sendMediaMessage() {
    // Check message limit
    $messagesSent = (int)Capsule::table('config')
        ->where('config_key', 'messages_sent_count')
        ->value('config_value') ?? 0;
    
    $messageLimit = (int)Capsule::table('config')
        ->where('config_key', 'message_limit')
        ->value('config_value') ?? 500;
    
    if ($messagesSent >= $messageLimit) {
        response_error('Message limit reached', 429);
    }
    
    // Get form data
    $to = sanitize($_POST['to'] ?? '');
    $contactId = $_POST['contact_id'] ?? null;
    $caption = $_POST['caption'] ?? '';
    
    // Validate
    if (!$to || !isset($_FILES['media'])) {
        response_error('Phone number and media file are required', 422);
    }
    
    $file = $_FILES['media'];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        response_error('File upload failed', 400);
    }
    
    // Check file size (16MB max for WhatsApp)
    if ($file['size'] > 16 * 1024 * 1024) {
        response_error('File size must be less than 16MB', 400);
    }
    
    // Determine media type based on MIME
    $mimeType = mime_content_type($file['tmp_name']);
    $mediaType = 'document'; // default
    
    if (strpos($mimeType, 'image/') === 0) {
        $mediaType = 'image';
    } elseif (strpos($mimeType, 'video/') === 0) {
        $mediaType = 'video';
    } elseif (strpos($mimeType, 'audio/') === 0) {
        $mediaType = 'audio';
    }
    
    // Upload file to server
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Sanitize filename to avoid spaces and unsafe characters for public URL access
    $originalName = basename($file['name']);
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
    $filename = uniqid() . '_' . $safeName;
    $uploadPath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        response_error('Failed to save file', 500);
    }
    
    // Get public URL for the file
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $mediaUrl = $protocol . '://' . $host . '/uploads/' . $filename;
    
    // Send via WhatsApp API
    $whatsappService = new WhatsAppService();
    $result = $whatsappService->sendMediaMessage($to, $mediaUrl, $mediaType, $caption, $filename);
    
    if (!$result['success']) {
        // Clean up uploaded file
        @unlink($uploadPath);
        
        // Check if it's a 24-hour window error
        $errorMsg = $result['error'] ?? '';
        if (strpos($errorMsg, '400') !== false) {
            response_error('Cannot send: Contact must message you first (24-hour window)', 403, ['details' => $result]);
        }
        
        response_error('Failed to send media', 500, ['details' => $result]);
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
        'message_type' => $mediaType,
        'direction' => 'outgoing',
        'message_body' => $caption ?: '[' . strtoupper($mediaType) . ']',
        'media_url' => $mediaUrl,
        'media_id' => $result['media_id'] ?? null,
        'media_filename' => $filename,
        'media_mime_type' => $mimeType,
        'media_size' => $file['size'],
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
        'media_url' => $mediaUrl,
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
    $parameters = $input['parameters'] ?? [];
    $contactId = $input['contact_id'] ?? null;
    
    // Validate input
    $validation = validate([
        'to' => $to,
        'template_name' => $templateName,
        'language_code' => $languageCode
    ], [
        'to' => 'required',
        'template_name' => 'required|min:1|max:100',
        'language_code' => 'max:10'
    ]);
    
    if ($validation !== true) {
        response_error('Validation failed', 422, ['errors' => $validation]);
    }
    
    // Normalize template name (remove spaces, convert to lowercase for matching)
    $templateName = trim($templateName);
    
    // Normalize language code
    $languageCode = trim($languageCode);
    if (empty($languageCode)) {
        $languageCode = 'en';
    }
    
    // Sanitize parameters
    $sanitizedParams = [];
    if (is_array($parameters)) {
        foreach ($parameters as $param) {
            if (!empty($param)) {
                $sanitizedParams[] = sanitize($param);
            }
        }
    }
    
    // Send via WhatsApp API
    $whatsappService = new WhatsAppService();
    $result = $whatsappService->sendTemplateMessage($to, $templateName, $languageCode, $sanitizedParams);
    
    if (!$result['success']) {
        // Provide more helpful error message
        $errorMsg = $result['error'] ?? 'Failed to send template message';
        $errorDetails = $result['response'] ?? '';
        
        // Try to extract more details from response
        if ($errorDetails) {
            $errorData = json_decode($errorDetails, true);
            if (isset($errorData['error']['message'])) {
                $errorMsg = $errorData['error']['message'];
            }
        }
        
        response_error('Failed to send template message: ' . $errorMsg, 500, [
            'details' => $result,
            'template_name' => $templateName,
            'language_code' => $languageCode,
            'phone' => $to
        ]);
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
 * Get available message templates
 */
function getTemplates() {
    $whatsappService = new WhatsAppService();
    $result = $whatsappService->getTemplates();
    
    if ($result['success']) {
        response_json([
            'success' => true,
            'templates' => $result['templates'] ?? [],
            'data' => $result['data'] ?? []
        ]);
    } else {
        response_error('Failed to fetch templates: ' . ($result['error'] ?? 'Unknown error'), 500, $result);
    }
}

/**
 * Get all users
 */
function getUsers() {
    $users = \App\Models\User::orderBy('created_at', 'desc')->get();
    response_json(['success' => true, 'users' => $users]);
}

/**
 * Get single user
 */
function getUser($userId) {
    $user = \App\Models\User::find($userId);
    
    if (!$user) {
        response_error('User not found', 404);
    }
    
    response_json(['success' => true, 'user' => $user]);
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
 * Search messages with advanced filters
 */
function searchMessages() {
    $query = sanitize($_GET['q'] ?? '');
    $stage = sanitize($_GET['stage'] ?? '');
    $tags = explode(',', sanitize($_GET['tags'] ?? ''));
    $tags = array_filter($tags);
    $messageType = sanitize($_GET['message_type'] ?? '');
    $fromDate = sanitize($_GET['from_date'] ?? '');
    $toDate = sanitize($_GET['to_date'] ?? '');
    $direction = sanitize($_GET['direction'] ?? '');
    $minScore = (int)($_GET['min_score'] ?? 0);
    
    if (!$query) {
        response_json([]);
        return;
    }
    
    // Build query
    $qb = Message::with(['contact', 'contact.contactTags']);
    
    // Text search
    $qb->where(function($q) use ($query) {
        $q->where('message_body', 'like', "%{$query}%")
          ->orWhere('phone_number', 'like', "%{$query}%")
          ->orWhereHas('contact', function($c) use ($query) {
              $c->where('name', 'like', "%{$query}%")
                ->orWhere('phone_number', 'like', "%{$query}%");
          });
    });
    
    // Filters
    if ($stage) {
        $qb->whereHas('contact', function($c) use ($stage) {
            $c->where('stage', $stage);
        });
    }
    
    if (!empty($tags)) {
        $qb->whereHas('contact.contactTags', function($q) use ($tags) {
            $q->whereIn('tag_id', $tags);
        });
    }
    
    if ($messageType) {
        $qb->where('message_type', $messageType);
    }
    
    if ($fromDate) {
        $qb->where('timestamp', '>=', $fromDate . ' 00:00:00');
    }
    
    if ($toDate) {
        $qb->where('timestamp', '<=', $toDate . ' 23:59:59');
    }
    
    if ($direction && in_array($direction, ['incoming', 'outgoing'])) {
        $qb->where('direction', $direction === 'incoming' ? 'incoming' : 'outgoing');
    }
    
    if ($minScore > 0) {
        $qb->whereHas('contact', function($c) use ($minScore) {
            $c->where('lead_score', '>=', $minScore);
        });
    }
    
    $results = $qb->orderBy('timestamp', 'desc')
        ->limit(100)
        ->get()
        ->map(function($message) {
            return [
                'id' => $message->id,
                'message_body' => $message->message_body,
                'message_type' => $message->message_type,
                'timestamp' => $message->timestamp->format('Y-m-d H:i:s'),
                'contact_id' => $message->contact_id,
                'contact_name' => $message->contact->name,
                'phone_number' => $message->contact->phone_number,
                'stage' => $message->contact->stage,
                'lead_score' => $message->contact->lead_score,
                'direction' => $message->direction,
                'tags' => $message->contact->contactTags->map(function($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color
                    ];
                })->values()->all()
            ];
        });
    
    response_json($results);
}

/**
 * Get message limit and current count
 */
function getMessageLimit() {
    try {
        // Check if config records exist, create if not
        $messagesSent = Capsule::table('config')
            ->where('config_key', 'messages_sent_count')
            ->value('config_value');
        
        if ($messagesSent === null) {
            // Create the record if it doesn't exist
            Capsule::table('config')->insert([
                'config_key' => 'messages_sent_count',
                'config_value' => '0',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $messagesSent = 0;
        } else {
            $messagesSent = (int)$messagesSent;
        }
        
        $messageLimit = Capsule::table('config')
            ->where('config_key', 'message_limit')
            ->value('config_value');
        
        if ($messageLimit === null) {
            // Create the record if it doesn't exist
            Capsule::table('config')->insert([
                'config_key' => 'message_limit',
                'config_value' => '500',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $messageLimit = 500;
        } else {
            $messageLimit = (int)$messageLimit;
        }
        
        response_json([
            'sent' => $messagesSent,
            'limit' => $messageLimit,
            'remaining' => max(0, $messageLimit - $messagesSent),
            'percentage' => $messageLimit > 0 ? round(($messagesSent / $messageLimit) * 100, 1) : 0
        ]);
    } catch (\Exception $e) {
        logger('Message limit error: ' . $e->getMessage(), 'error');
        response_error('Failed to get message limit: ' . $e->getMessage(), 500);
    }
}
/**
 * Get all auto-tag rules
 */
function getAutoTagRules() {
    $rules = Capsule::table('auto_tag_rules as r')
        ->leftJoin('tags as t', 'r.tag_id', '=', 't.id')
        ->select('r.*', 't.name as tag_name', 't.color as tag_color')
        ->orderBy('r.priority', 'desc')
        ->get();
    
    response_json($rules);
}

/**
 * Create new auto-tag rule
 */
function createAutoTagRule() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $ruleName = sanitize($input['rule_name'] ?? '');
    $tagId = $input['tag_id'] ?? null;
    $keywords = $input['keywords'] ?? '[]';
    $matchType = $input['match_type'] ?? 'any';
    $priority = $input['priority'] ?? 1;
    $enabled = $input['enabled'] ?? true;
    
    if (!$ruleName || !$tagId) {
        response_error('Rule name and tag ID are required', 422);
    }
    
    $ruleId = Capsule::table('auto_tag_rules')->insertGetId([
        'rule_name' => $ruleName,
        'tag_id' => $tagId,
        'keywords' => $keywords,
        'match_type' => $matchType,
        'priority' => $priority,
        'enabled' => $enabled ? 1 : 0,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    response_json(['success' => true, 'rule_id' => $ruleId]);
}

/**
 * Update auto-tag rule
 */
function updateAutoTagRule($ruleId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $updateData = ['updated_at' => now()];
    
    if (isset($input['rule_name'])) {
        $updateData['rule_name'] = sanitize($input['rule_name']);
    }
    if (isset($input['tag_id'])) {
        $updateData['tag_id'] = $input['tag_id'];
    }
    if (isset($input['keywords'])) {
        $updateData['keywords'] = $input['keywords'];
    }
    if (isset($input['match_type'])) {
        $updateData['match_type'] = $input['match_type'];
    }
    if (isset($input['priority'])) {
        $updateData['priority'] = $input['priority'];
    }
    if (isset($input['enabled'])) {
        $updateData['enabled'] = $input['enabled'] ? 1 : 0;
    }
    
    Capsule::table('auto_tag_rules')
        ->where('id', $ruleId)
        ->update($updateData);
    
    response_json(['success' => true]);
}

/**
 * Delete auto-tag rule
 */
function deleteAutoTagRule($ruleId) {
    Capsule::table('auto_tag_rules')->where('id', $ruleId)->delete();
    response_json(['success' => true]);
}

/**
 * Get all tags
 */
function getTags() {
    $tags = Capsule::table('tags')
        ->orderBy('name')
        ->get();
    
    response_json($tags);
}

/**
 * Bulk add tag to multiple contacts
 */
function bulkAddTag() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $contactIds = $input['contact_ids'] ?? [];
    $tagId = $input['tag_id'] ?? null;
    
    if (empty($contactIds) || !$tagId) {
        response_error('contact_ids and tag_id are required', 422);
    }
    
    // Validate contacts exist
    $contacts = Contact::whereIn('id', $contactIds)->get();
    if ($contacts->isEmpty()) {
        response_error('No contacts found', 404);
    }
    
    // Sync tag for each contact (avoid duplicates)
    foreach ($contacts as $contact) {
        $contact->contactTags()->syncWithoutDetaching([$tagId]);
    }
    
    response_json([
        'success' => true,
        'updated_count' => $contacts->count()
    ]);
}

/**
 * Bulk update stage for multiple contacts
 */
function bulkUpdateStage() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $contactIds = $input['contact_ids'] ?? [];
    $stage = sanitize($input['stage'] ?? '');
    
    if (empty($contactIds) || !$stage) {
        response_error('contact_ids and stage are required', 422);
    }
    
    // Valid stages
    $validStages = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'customer'];
    if (!in_array($stage, $validStages)) {
        response_error('Invalid stage value', 422);
    }
    
    $updated = Contact::whereIn('id', $contactIds)->update([
        'stage' => $stage,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    response_json([
        'success' => true,
        'updated_count' => $updated
    ]);
}

/**
 * Bulk delete contacts
 */
function bulkDeleteContacts() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $contactIds = $input['contact_ids'] ?? [];
    
    if (empty($contactIds)) {
        response_error('contact_ids are required', 422);
    }
    
    // Delete contacts (cascade will handle related records)
    $deleted = Contact::whereIn('id', $contactIds)->delete();
    
    response_json([
        'success' => true,
        'deleted_count' => $deleted
    ]);
}

/**
 * Get unified contact timeline (messages, notes, activities, tasks)
 */
function getContactTimeline($contactId) {
    $contact = Contact::findOrFail($contactId);
    $userId = $_SESSION['user_id'];
    
    // Get all timeline items
    $timeline = [];
    
    // Messages
    $messages = Message::where('contact_id', $contactId)
        ->orderBy('timestamp', 'desc')
        ->limit(100)
        ->get()
        ->map(function($msg) use ($userId) {
            return [
                'type' => 'message',
                'id' => $msg->id,
                'timestamp' => $msg->timestamp,
                'title' => $msg->direction === 'incoming' ? 'Received message' : 'Sent message',
                'description' => substr($msg->message_body, 0, 200),
                'direction' => $msg->direction,
                'message_type' => $msg->message_type,
                'is_starred' => MessageAction::isStarred($msg->id, $userId),
                'data' => $msg
            ];
        });
    
    // Notes
    $notes = Note::where('contact_id', $contactId)
        ->with('creator')
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get()
        ->map(function($note) {
            return [
                'type' => 'note',
                'id' => $note->id,
                'timestamp' => $note->created_at,
                'title' => ucfirst($note->type) . ' Note',
                'description' => substr($note->content, 0, 200),
                'note_type' => $note->type,
                'creator' => $note->creator->name ?? 'Unknown',
                'data' => $note
            ];
        });
    
    // Activities
    $activities = Activity::where('contact_id', $contactId)
        ->with('creator')
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get()
        ->map(function($activity) {
            return [
                'type' => 'activity',
                'id' => $activity->id,
                'timestamp' => $activity->created_at,
                'title' => $activity->title,
                'description' => $activity->description,
                'activity_type' => $activity->type,
                'creator' => $activity->creator->name ?? 'Unknown',
                'data' => $activity
            ];
        });
    
    // Tasks
    $tasks = Task::where('contact_id', $contactId)
        ->with(['assignedUser', 'creator'])
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get()
        ->map(function($task) {
            return [
                'type' => 'task',
                'id' => $task->id,
                'timestamp' => $task->created_at,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date,
                'is_overdue' => $task->isOverdue(),
                'data' => $task
            ];
        });
    
    // Merge all and sort by timestamp
    $timeline = collect()
        ->merge($messages)
        ->merge($notes)
        ->merge($activities)
        ->merge($tasks)
        ->sortByDesc('timestamp')
        ->values();
    
    response_json([
        'success' => true,
        'timeline' => $timeline,
        'contact' => $contact
    ]);
}

/**
 * Get tasks (with filters)
 */
function getTasks() {
    $userId = $_SESSION['user_id'];
    $query = Task::with(['contact', 'assignedUser', 'creator']);
    
    // Filters
    $contactId = $_GET['contact_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $priority = $_GET['priority'] ?? null;
    $assignedTo = $_GET['assigned_to'] ?? null;
    $overdue = isset($_GET['overdue']) ? (bool)$_GET['overdue'] : null;
    
    if ($contactId) {
        $query->where('contact_id', $contactId);
    }
    if ($status) {
        $query->where('status', $status);
    }
    if ($priority) {
        $query->where('priority', $priority);
    }
    if ($assignedTo) {
        $query->where('assigned_to', $assignedTo);
    }
    if ($overdue !== null) {
        if ($overdue) {
            $query->where('due_date', '<', now())
                  ->whereNotIn('status', ['completed', 'cancelled']);
        }
    }
    
    $tasks = $query->orderBy('due_date', 'asc')->get();
    
    response_json([
        'success' => true,
        'tasks' => $tasks
    ]);
}

/**
 * Create task
 */
function createTask() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validation = validate([
        'title' => sanitize($input['title'] ?? ''),
        'contact_id' => isset($input['contact_id']) ? (int)$input['contact_id'] : null,
        'due_date' => $input['due_date'] ?? null
    ], [
        'title' => 'required|min:2|max:255',
        'contact_id' => 'integer',
        'due_date' => 'date'
    ]);
    
    if ($validation !== true) {
        response_error('Validation failed', 422, ['errors' => $validation]);
    }
    
    // Ensure user_id exists
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        $userExists = \App\Models\User::find($userId);
        if (!$userExists) {
            // Try to get first admin user
            $adminUser = \App\Models\User::where('role', 'admin')->first();
            if ($adminUser) {
                $userId = $adminUser->id;
            } else {
                // Get any user
                $anyUser = \App\Models\User::first();
                if ($anyUser) {
                    $userId = $anyUser->id;
                } else {
                    response_error('No valid user found. Please create a user first.', 422);
                }
            }
        }
    } else {
        // If no user_id in session, try to get current user from session
        if (isset($_SESSION['user_id'])) {
            $sessionUser = \App\Models\User::find($_SESSION['user_id']);
            if ($sessionUser) {
                $userId = $sessionUser->id;
            }
        }
        
        // If still no user, try to get first admin user
        if (!$userId) {
            $adminUser = \App\Models\User::where('role', 'admin')->first();
            if ($adminUser) {
                $userId = $adminUser->id;
            } else {
                // Get any user as last resort
                $anyUser = \App\Models\User::first();
                if ($anyUser) {
                    $userId = $anyUser->id;
                } else {
                    response_error('No valid user found. Please create a user first.', 422);
                }
            }
        }
    }
    
    $task = Task::create([
        'contact_id' => $input['contact_id'] ?? null,
        'title' => sanitize($input['title']),
        'description' => sanitize($input['description'] ?? ''),
        'type' => $input['type'] ?? 'follow_up',
        'priority' => $input['priority'] ?? 'medium',
        'status' => $input['status'] ?? 'pending',
        'due_date' => $input['due_date'] ? date('Y-m-d H:i:s', strtotime($input['due_date'])) : null,
        'assigned_to' => $input['assigned_to'] ?? null,
        'created_by' => $userId,
        'notes' => sanitize($input['notes'] ?? '')
    ]);
    
    response_json([
        'success' => true,
        'task' => $task->load(['contact', 'assignedUser', 'creator'])
    ]);
}

/**
 * Update task
 */
function updateTask($taskId) {
    $task = Task::findOrFail($taskId);
    $input = json_decode(file_get_contents('php://input'), true);
    
    $updateData = [];
    
    if (isset($input['title'])) {
        $updateData['title'] = sanitize($input['title']);
    }
    if (isset($input['description'])) {
        $updateData['description'] = sanitize($input['description']);
    }
    if (isset($input['status'])) {
        $updateData['status'] = $input['status'];
        if ($input['status'] === 'completed') {
            $updateData['completed_at'] = now();
        }
    }
    if (isset($input['priority'])) {
        $updateData['priority'] = $input['priority'];
    }
    if (isset($input['due_date'])) {
        $updateData['due_date'] = $input['due_date'] ? date('Y-m-d H:i:s', strtotime($input['due_date'])) : null;
    }
    if (isset($input['assigned_to'])) {
        $updateData['assigned_to'] = $input['assigned_to'];
    }
    
    $task->update($updateData);
    
    response_json([
        'success' => true,
        'task' => $task->fresh(['contact', 'assignedUser', 'creator'])
    ]);
}

/**
 * Delete task
 */
function deleteTask($taskId) {
    $task = Task::findOrFail($taskId);
    $task->delete();
    
    response_json(['success' => true]);
}

/**
 * Handle message action (star, forward, delete)
 */
function handleMessageAction() {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    
    $messageId = $input['message_id'] ?? null;
    $actionType = $input['action_type'] ?? 'star';
    $forwardToContactId = $input['forward_to_contact_id'] ?? null;
    
    if (!$messageId) {
        response_error('message_id is required', 422);
    }
    
    $message = Message::findOrFail($messageId);
    
    switch ($actionType) {
        case 'star':
            MessageAction::star($messageId, $userId);
            break;
            
        case 'forward':
            if (!$forwardToContactId) {
                response_error('forward_to_contact_id is required for forward action', 422);
            }
            MessageAction::create([
                'message_id' => $messageId,
                'user_id' => $userId,
                'action_type' => 'forward',
                'forwarded_to_contact_id' => $forwardToContactId,
                'notes' => sanitize($input['notes'] ?? '')
            ]);
            
            // Actually forward the message
            $whatsappService = new WhatsAppService();
            $forwardToContact = Contact::findOrFail($forwardToContactId);
            $forwardMessage = $message->message_body;
            if ($message->media_url) {
                $whatsappService->sendMediaMessage(
                    $forwardToContact->phone_number,
                    $message->media_url,
                    $message->message_type,
                    $forwardMessage
                );
            } else {
                $whatsappService->sendTextMessage($forwardToContact->phone_number, $forwardMessage);
            }
            break;
            
        case 'delete':
            // Soft delete or mark as deleted
            $message->update(['message_body' => '[Deleted]', 'is_deleted' => true]);
            break;
    }
    
    response_json(['success' => true]);
}

/**
 * Remove message action
 */
function removeMessageAction($actionId) {
    $action = MessageAction::findOrFail($actionId);
    $action->delete();
    
    response_json(['success' => true]);
}

/**
 * Merge duplicate contacts
 */
function mergeContacts() {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    
    $sourceContactId = $input['source_contact_id'] ?? null;
    $targetContactId = $input['target_contact_id'] ?? null;
    $mergeReason = sanitize($input['merge_reason'] ?? '');
    
    if (!$sourceContactId || !$targetContactId) {
        response_error('source_contact_id and target_contact_id are required', 422);
    }
    
    if ($sourceContactId == $targetContactId) {
        response_error('Cannot merge contact with itself', 422);
    }
    
    $sourceContact = Contact::findOrFail($sourceContactId);
    $targetContact = Contact::findOrFail($targetContactId);
    
    // Merge data
    $mergedData = [];
    
    // Merge messages
    $messagesMoved = Message::where('contact_id', $sourceContactId)
        ->update(['contact_id' => $targetContactId]);
    $mergedData['messages'] = $messagesMoved;
    
    // Merge notes
    $notesMoved = Note::where('contact_id', $sourceContactId)
        ->update(['contact_id' => $targetContactId]);
    $mergedData['notes'] = $notesMoved;
    
    // Merge activities
    $activitiesMoved = Activity::where('contact_id', $sourceContactId)
        ->update(['contact_id' => $targetContactId]);
    $mergedData['activities'] = $activitiesMoved;
    
    // Merge tags
    $sourceTags = $sourceContact->contactTags()->pluck('tags.id')->toArray();
    $targetContact->contactTags()->syncWithoutDetaching($sourceTags);
    $mergedData['tags'] = count($sourceTags);
    
    // Update target contact with best data from source (if missing)
    $updates = [];
    if (!$targetContact->email && $sourceContact->email) {
        $updates['email'] = $sourceContact->email;
    }
    if (!$targetContact->company_name && $sourceContact->company_name) {
        $updates['company_name'] = $sourceContact->company_name;
    }
    if (!$targetContact->city && $sourceContact->city) {
        $updates['city'] = $sourceContact->city;
    }
    if ($sourceContact->last_message_time > $targetContact->last_message_time) {
        $updates['last_message_time'] = $sourceContact->last_message_time;
    }
    $targetContact->update($updates);
    
    // Log merge
    ContactMerge::create([
        'source_contact_id' => $sourceContactId,
        'target_contact_id' => $targetContactId,
        'merged_by' => $userId,
        'merge_reason' => $mergeReason,
        'merged_data' => $mergedData
    ]);
    
    // Delete source contact
    $sourceContact->delete();
    
    response_json([
        'success' => true,
        'merged_data' => $mergedData,
        'target_contact' => $targetContact->fresh()
    ]);
}

/**
 * Clear log files
 */
function clearLogs() {
    // Only admins can clear logs
    $user = \App\Models\User::find($_SESSION['user_id'] ?? null);
    if (!$user || $user->role !== 'admin') {
        response_error('Unauthorized', 403);
    }
    
    $logDir = __DIR__ . '/storage/logs';
    $cleared = 0;
    
    if (is_dir($logDir)) {
        $files = scandir($logDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                $filePath = $logDir . '/' . $file;
                if (file_exists($filePath) && is_writable($filePath)) {
                    file_put_contents($filePath, '');
                    $cleared++;
                }
            }
        }
    }
    
    response_json([
        'success' => true,
        'message' => "Cleared {$cleared} log file(s)"
    ]);
}

/**
 * Find duplicate contacts
 */
function findDuplicateContacts() {
    // Find contacts with similar phone numbers or names
    $phonePattern = $_GET['phone_pattern'] ?? null;
    $nameSimilarity = isset($_GET['name_similarity']) ? (float)$_GET['name_similarity'] : 0.8;
    
    $duplicates = [];
    
    if ($phonePattern) {
        // Find by phone number pattern (same country code, similar numbers)
        $contacts = Contact::all();
        $grouped = [];
        
        foreach ($contacts as $contact) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $contact->phone_number);
            if (strlen($cleanPhone) >= 7) {
                $key = substr($cleanPhone, -7); // Last 7 digits
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = $contact;
            }
        }
        
        foreach ($grouped as $key => $group) {
            if (count($group) > 1) {
                $duplicates[] = [
                    'type' => 'phone_pattern',
                    'pattern' => $key,
                    'contacts' => $group
                ];
            }
        }
    } else {
        // Find by name similarity
        $contacts = Contact::whereNotNull('name')->get();
        
        foreach ($contacts as $i => $contact1) {
            for ($j = $i + 1; $j < count($contacts); $j++) {
                $contact2 = $contacts[$j];
                $similarity = similar_text(
                    strtolower($contact1->name),
                    strtolower($contact2->name),
                    $percent
                ) / 100;
                
                if ($similarity >= $nameSimilarity && $contact1->phone_number !== $contact2->phone_number) {
                    $duplicates[] = [
                        'type' => 'name_similarity',
                        'similarity' => $similarity,
                        'contacts' => [$contact1, $contact2]
                    ];
                }
            }
        }
    }
    
    response_json([
        'success' => true,
        'duplicates' => $duplicates,
        'count' => count($duplicates)
    ]);
}
