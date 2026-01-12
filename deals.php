<?php
/**
 * Deal History Page - View all deals across all contacts
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Deal;
use App\Models\Contact;
use Illuminate\Database\Capsule\Manager as Capsule;

if (!isAuthenticated()) {
    redirect('/login.php');
}

// Get filter parameters
$contactId = $_GET['contact_id'] ?? null;
$status = $_GET['status'] ?? null;
$search = $_GET['search'] ?? null;

// Build query
$query = Deal::with(['contact', 'creator'])->orderBy('deal_date', 'desc');

if ($contactId) {
    $query->where('contact_id', $contactId);
}

if ($status && $status !== 'all') {
    $query->where('status', $status);
}

if ($search) {
    $query->where(function($q) use ($search) {
        $q->where('deal_name', 'LIKE', "%{$search}%")
          ->orWhere('notes', 'LIKE', "%{$search}%")
          ->orWhereHas('contact', function($cq) use ($search) {
              $cq->where('name', 'LIKE', "%{$search}%");
          });
    });
}

$deals = $query->get();
$contacts = Contact::orderBy('name')->get();

// Stats
$totalDeals = Deal::count();
$dealsByStatus = Deal::selectRaw('status, COUNT(*) as count')
    ->groupBy('status')
    ->pluck('count', 'status');

$wonDeals = Deal::where('status', 'won')->get();
$totalRevenue = $wonDeals->sum('amount');

$user = getAuthenticatedUser();

echo render('deals.html.twig', [
    'deals' => $deals,
    'contacts' => $contacts,
    'totalDeals' => $totalDeals,
    'dealsByStatus' => $dealsByStatus,
    'totalRevenue' => $totalRevenue,
    'wonDealsCount' => $wonDeals->count(),
    'currentContactId' => $contactId,
    'currentStatus' => $status ?? 'all',
    'searchQuery' => $search,
    'user' => $user
]);
