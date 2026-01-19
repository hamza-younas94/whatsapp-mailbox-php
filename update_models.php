<?php
/**
 * Script to update all models with user_id for multi-tenant support
 * Run this after migration 100
 */

require_once __DIR__ . '/bootstrap.php';

echo "🔧 Updating all models for multi-tenant support...\n\n";

$modelsToUpdate = [
    'Message' => ['user_id', 'contact_id', 'phone_number', 'message_type', 'direction', 'message_body', 'media_url', 'media_mime_type', 'media_caption', 'media_filename', 'media_size', 'status', 'is_read', 'timestamp'],
    'QuickReply' => ['user_id', 'title', 'shortcut', 'message', 'is_active', 'usage_count'],
    'Broadcast' => ['user_id', 'name', 'message', 'scheduled_at', 'status', 'total_recipients', 'sent_count', 'failed_count'],
    'ScheduledMessage' => ['user_id', 'contact_id', 'message', 'scheduled_at', 'status', 'sent_at'],
    'Segment' => ['user_id', 'name', 'description', 'conditions', 'contact_count'],
    'Tag' => ['user_id', 'name', 'color', 'description'],
    'Deal' => ['user_id', 'contact_id', 'title', 'amount', 'currency', 'status', 'stage', 'probability', 'deal_date', 'expected_close_date', 'closed_date', 'notes'],
    'Workflow' => ['user_id', 'name', 'description', 'trigger_type', 'trigger_conditions', 'actions', 'is_active', 'execution_count'],
    'AutoTagRule' => ['user_id', 'tag_id', 'rule_name', 'match_type', 'pattern', 'is_active', 'priority', 'usage_count'],
    'Webhook' => ['user_id', 'name', 'url', 'events', 'is_active', 'secret'],
    'Note' => ['user_id', 'contact_id', 'content', 'created_by'],
    'InternalNote' => ['user_id', 'contact_id', 'note', 'created_by'],
    'Activity' => ['user_id', 'contact_id', 'type', 'title', 'description', 'metadata', 'created_by'],
    'Task' => ['user_id', 'contact_id', 'title', 'description', 'due_date', 'status', 'priority', 'assigned_to'],
    'MessageTemplate' => ['user_id', 'name', 'content', 'category', 'language', 'status'],
    'DripCampaign' => ['user_id', 'name', 'description', 'is_active', 'total_steps'],
    'IpCommand' => ['user_id', 'ip_address', 'contact_name', 'phone_number', 'api_response', 'http_code', 'status'],
];

$modelsDir = __DIR__ . '/app/Models';
$filesUpdated = 0;

foreach ($modelsToUpdate as $modelName => $fillableFields) {
    $filePath = "{$modelsDir}/{$modelName}.php";
    
    if (!file_exists($filePath)) {
        echo "⚠️  Model {$modelName}.php not found\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Check if already has user_id in fillable
    if (strpos($content, "'user_id'") !== false) {
        echo "✓ {$modelName} already has user_id\n";
        continue;
    }
    
    // Add user_id to fillable array
    $pattern = '/protected\s+\$fillable\s*=\s*\[/';
    $replacement = "protected \$fillable = [\n        'user_id', // MULTI-TENANT";
    
    $newContent = preg_replace($pattern, $replacement, $content);
    
    if ($newContent !== $content) {
        file_put_contents($filePath, $newContent);
        echo "✅ Updated {$modelName}\n";
        $filesUpdated++;
    } else {
        echo "⚠️  Could not update {$modelName} (pattern not found)\n";
    }
}

echo "\n✅ Updated {$filesUpdated} model files\n";
echo "\n📝 Manual steps still needed:\n";
echo "1. Add user() relationship method to each model\n";
echo "2. Update WhatsAppService to use TenantContext\n";
echo "3. Add tenant middleware to all entry points\n";
echo "4. Create registration/signup page\n";
echo "5. Update webhook.php to identify tenant\n";
