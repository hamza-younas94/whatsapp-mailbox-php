<?php
/**
 * Initialize CRM Data for All Contacts
 * Run this ONCE to set default CRM values
 */
require_once 'bootstrap.php';

use App\Models\Contact;

echo "🚀 Initializing CRM Data for All Contacts...\n\n";

$contacts = Contact::all();
$updated = 0;

foreach ($contacts as $contact) {
    // Set default stage if null
    if (!$contact->stage) {
        $contact->stage = 'new';
    }
    
    // Calculate lead score if null or zero
    if (!$contact->lead_score || $contact->lead_score == 0) {
        $contact->updateLeadScore();
    }
    
    $contact->save();
    $updated++;
    
    echo "✅ Updated: {$contact->name}\n";
    echo "   - Stage: {$contact->stage}\n";
    echo "   - Lead Score: {$contact->lead_score}\n\n";
}

echo "\n✅ Done! Updated {$updated} contacts.\n";
echo "\n🎯 Now refresh your browser and you'll see:\n";
echo "   - Stage badges (NEW, CONTACTED, etc.)\n";
echo "   - Lead scores (numbers in circles)\n";
echo "   - Proper CRM data in dashboard\n";
