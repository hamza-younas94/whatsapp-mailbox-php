<?php
// Minimal webhook for WhatsApp Web bridge
// Accepts JSON: { user_id, phone_number, message: { message_id, message_type, direction, message_body, timestamp } }

require 'bootstrap.php';

use App\Models\Message;
use App\Models\Contact;
use App\Models\User;

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid json']);
    exit;
}

try {
    $userId = intval($payload['user_id'] ?? 0);
    $user = User::findOrFail($userId);
    $phone = $payload['phone_number'] ?? '';
    $msg = $payload['message'] ?? [];

    // Ensure contact exists for this user
    $contact = Contact::firstOrCreate(
        ['user_id' => $user->id, 'phone_number' => $phone],
        ['name' => $phone]
    );

    // Save message (incoming)
    $message = Message::updateOrCreate(
        ['message_id' => $msg['message_id'] ?? null],
        [
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'phone_number' => $phone,
            'message_type' => $msg['message_type'] ?? 'text',
            'direction' => $msg['direction'] ?? 'incoming',
            'message_body' => $msg['message_body'] ?? '',
            'timestamp' => $msg['timestamp'] ?? date('Y-m-d H:i:s'),
            'is_read' => false
        ]
    );

    echo json_encode(['success' => true, 'message_id' => $message->id]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
