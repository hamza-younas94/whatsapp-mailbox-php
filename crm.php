<?php
/**
 * CRM API Endpoints
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\Contact;
use App\Models\Note;
use App\Models\Activity;
use App\Models\Deal;

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/crm.php', '', $path);

try {
    // Update contact CRM fields
    if ($method === 'PUT' && preg_match('/^\/contact\/(\d+)\/crm$/', $path, $matches)) {
        $contactId = $matches[1];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $contact = Contact::findOrFail($contactId);
        
        $updateFields = [];
        $allowedFields = ['stage', 'lead_score', 'assigned_to', 'source', 'company_name', 
                         'email', 'city', 'country', 'tags', 'deal_value', 'deal_currency', 'expected_close_date'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[$field] = $data[$field];
            }
        }
        
        if (!empty($updateFields)) {
            // Log stage change if stage updated
            if (isset($updateFields['stage']) && $updateFields['stage'] !== $contact->stage) {
                $oldStage = $contact->stage;
                $contact->update($updateFields);
                $contact->addActivity(
                    'stage_changed',
                    "Stage changed from {$oldStage} to {$updateFields['stage']}"
                );
            } else {
                $contact->update($updateFields);
            }
            
            $contact->updateLeadScore();
        }
        
        echo json_encode([
            'success' => true,
            'contact' => $contact->fresh()
        ]);
        exit;
    }
    
    // Add note to contact
    if ($method === 'POST' && preg_match('/^\/contact\/(\d+)\/note$/', $path, $matches)) {
        $contactId = $matches[1];
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Note content is required']);
            exit;
        }
        
        $note = Note::create([
            'contact_id' => $contactId,
            'content' => $data['content'],
            'type' => $data['type'] ?? 'general',
            'created_by' => $_SESSION['user_id']
        ]);
        
        // Add activity
        $contact = Contact::find($contactId);
        $contact->addActivity('note_added', 'Note added', substr($data['content'], 0, 100));
        $contact->touch('last_activity_at');
        
        echo json_encode([
            'success' => true,
            'note' => $note
        ]);
        exit;
    }
    
    // Get notes for contact
    if ($method === 'GET' && preg_match('/^\/contact\/(\d+)\/notes$/', $path, $matches)) {
        $contactId = $matches[1];
        
        $notes = Note::where('contact_id', $contactId)
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->get();
        
        echo json_encode(['success' => true, 'notes' => $notes]);
        exit;
    }
    
    // Get activities for contact
    if ($method === 'GET' && preg_match('/^\/contact\/(\d+)\/activities$/', $path, $matches)) {
        $contactId = $matches[1];
        
        $activities = Activity::where('contact_id', $contactId)
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
        
        echo json_encode(['activities' => $activities]);
        exit;
    }
    
    // Get deals for contact
    if ($method === 'GET' && preg_match('/^\/contact\/(\d+)\/deals$/', $path, $matches)) {
        $contactId = $matches[1];
        
        $deals = Deal::where('contact_id', $contactId)
            ->with('creator')
            ->orderBy('deal_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        echo json_encode(['success' => true, 'deals' => $deals]);
        exit;
    }
    
    // Add deal to contact
    if ($method === 'POST' && preg_match('/^\/contact\/(\d+)\/deal$/', $path, $matches)) {
        $contactId = $matches[1];
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['deal_name']) || empty($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Deal name and amount are required']);
            exit;
        }
        
        $deal = Deal::create([
            'contact_id' => $contactId,
            'deal_name' => $data['deal_name'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'PKR',
            'status' => $data['status'] ?? 'pending',
            'deal_date' => $data['deal_date'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'created_by' => $_SESSION['user_id']
        ]);
        
        // Add activity
        $contact = Contact::find($contactId);
        $contact->addActivity('deal_added', "Deal added: {$data['deal_name']} - PKR " . number_format($data['amount'], 0));
        $contact->touch('last_activity_at');
        
        // Update lead score if deal is won
        if ($data['status'] === 'won') {
            $contact->updateLeadScore();
        }
        
        echo json_encode([
            'success' => true,
            'deal' => $deal
        ]);
        exit;
    }
    
    // Get CRM statistics
    if ($method === 'GET' && $path === '/stats') {
        $stats = [
            'total_contacts' => Contact::count(),
            'by_stage' => Contact::selectRaw('stage, COUNT(*) as count')
                ->groupBy('stage')
                ->pluck('count', 'stage'),
            'avg_lead_score' => Contact::avg('lead_score'),
            'total_deal_value' => Contact::sum('deal_value'),
            'contacts_this_month' => Contact::whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'))
                ->count(),
            'hot_leads' => Contact::where('lead_score', '>=', 70)
                ->where('stage', '!=', 'customer')
                ->where('stage', '!=', 'lost')
                ->count()
        ];
        
        echo json_encode(['stats' => $stats]);
        exit;
    }
    
    // Get contacts by stage
    if ($method === 'GET' && preg_match('/^\/contacts\/stage\/([a-z_]+)$/', $path, $matches)) {
        $stage = $matches[1];
        
        $contacts = Contact::where('stage', $stage)
            ->with(['lastMessage', 'assignedUser'])
            ->withCount('unreadMessages')
            ->orderBy('lead_score', 'desc')
            ->orderBy('last_message_time', 'desc')
            ->get();
        
        echo json_encode(['contacts' => $contacts]);
        exit;
    }
    
    // Search contacts with CRM filters
    if ($method === 'GET' && $path === '/search') {
        $query = Contact::query()->with(['lastMessage', 'assignedUser'])->withCount('unreadMessages');
        
        // Filter by stage
        if (isset($_GET['stage']) && $_GET['stage'] !== 'all') {
            $query->where('stage', $_GET['stage']);
        }
        
        // Filter by lead score
        if (isset($_GET['min_score'])) {
            $query->where('lead_score', '>=', intval($_GET['min_score']));
        }
        
        // Filter by assigned user
        if (isset($_GET['assigned_to'])) {
            $query->where('assigned_to', intval($_GET['assigned_to']));
        }
        
        // Filter by tags
        if (isset($_GET['tag'])) {
            $query->where('tags', 'LIKE', '%' . $_GET['tag'] . '%');
        }
        
        // Search by name/phone/company
        if (isset($_GET['q']) && !empty($_GET['q'])) {
            $search = $_GET['q'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone_number', 'LIKE', "%{$search}%")
                  ->orWhere('company_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }
        
        $contacts = $query->orderBy('lead_score', 'desc')
            ->orderBy('last_message_time', 'desc')
            ->limit(100)
            ->get();
        
        echo json_encode(['contacts' => $contacts]);
        exit;
    }
    
    // 404
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    
} catch (\Exception $e) {
    logger('CRM API Error: ' . $e->getMessage(), 'error');
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
