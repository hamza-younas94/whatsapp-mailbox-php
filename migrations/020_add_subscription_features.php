<?php

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function () {
        $schema = Capsule::schema();
        
        if (!$schema->hasColumn('user_subscriptions', 'features')) {
            $schema->table('user_subscriptions', function ($table) {
                $table->json('features')->nullable()->after('contact_limit');
            });
            echo "✅ Added features column to user_subscriptions\n";
            
            // Set default features for existing subscriptions based on plan
            $defaultFeatures = [
                'free' => [
                    'mailbox' => true,
                    'quick_replies' => false,
                    'broadcasts' => false,
                    'segments' => false,
                    'drip_campaigns' => false,
                    'scheduled_messages' => false,
                    'auto_reply' => true,
                    'tags' => true,
                    'notes' => true,
                    'message_templates' => false,
                    'crm' => false,
                    'analytics' => false,
                    'workflows' => false,
                    'dcmb_ip_commands' => true
                ],
                'starter' => [
                    'mailbox' => true,
                    'quick_replies' => true,
                    'broadcasts' => true,
                    'segments' => false,
                    'drip_campaigns' => false,
                    'scheduled_messages' => true,
                    'auto_reply' => true,
                    'tags' => true,
                    'notes' => true,
                    'message_templates' => true,
                    'crm' => true,
                    'analytics' => false,
                    'workflows' => false,
                    'dcmb_ip_commands' => true
                ],
                'professional' => [
                    'mailbox' => true,
                    'quick_replies' => true,
                    'broadcasts' => true,
                    'segments' => true,
                    'drip_campaigns' => true,
                    'scheduled_messages' => true,
                    'auto_reply' => true,
                    'tags' => true,
                    'notes' => true,
                    'message_templates' => true,
                    'crm' => true,
                    'analytics' => true,
                    'workflows' => true,
                    'dcmb_ip_commands' => true
                ],
                'enterprise' => [
                    'mailbox' => true,
                    'quick_replies' => true,
                    'broadcasts' => true,
                    'segments' => true,
                    'drip_campaigns' => true,
                    'scheduled_messages' => true,
                    'auto_reply' => true,
                    'tags' => true,
                    'notes' => true,
                    'message_templates' => true,
                    'crm' => true,
                    'analytics' => true,
                    'workflows' => true,
                    'dcmb_ip_commands' => true
                ]
            ];
            
            // Update existing subscriptions with default features
            foreach ($defaultFeatures as $plan => $features) {
                Capsule::table('user_subscriptions')
                    ->where('plan', $plan)
                    ->whereNull('features')
                    ->update(['features' => json_encode($features)]);
            }
            
            echo "✅ Set default features for existing subscriptions\n";
        }
        
        echo "✅ Subscription features migration completed\n";
    },
    'down' => function () {
        $schema = Capsule::schema();
        
        if ($schema->hasColumn('user_subscriptions', 'features')) {
            $schema->table('user_subscriptions', function ($table) {
                $table->dropColumn('features');
            });
            echo "✅ Dropped features column from user_subscriptions\n";
        }
    }
];
