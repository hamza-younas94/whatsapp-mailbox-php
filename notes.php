<?php
/**
 * Admin Notes Page - View all notes across all contacts
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Note;
use App\Models\Contact;

if (!isAuthenticated()) {
    redirect('/login.php');
}

// Get filter parameters
$contactId = $_GET['contact_id'] ?? null;
$type = $_GET['type'] ?? null;
$search = $_GET['search'] ?? null;

// Build query
$query = Note::with(['contact', 'creator'])->orderBy('created_at', 'desc');

if ($contactId) {
    $query->where('contact_id', $contactId);
}

if ($type && $type !== 'all') {
    $query->where('type', $type);
}

if ($search) {
    $query->where(function($q) use ($search) {
        $q->where('content', 'LIKE', "%{$search}%")
          ->orWhereHas('contact', function($cq) use ($search) {
              $cq->where('name', 'LIKE', "%{$search}%");
          });
    });
}

$notes = $query->get();
$contacts = Contact::orderBy('name')->get();

// Stats
$totalNotes = Note::count();
$notesByType = Note::selectRaw('type, COUNT(*) as count')
    ->groupBy('type')
    ->pluck('count', 'type');

$user = getCurrentUser();

echo render('notes.html.twig', [
    'notes' => $notes,
    'contacts' => $contacts,
    'totalNotes' => $totalNotes,
    'notesByType' => $notesByType,
    'currentContactId' => $contactId,
    'currentType' => $type ?? 'all',
    'searchQuery' => $search,
    'user' => $user
]);
