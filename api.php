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
