<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Create config table
 */
return new class {
    public function up()
    {
        Capsule::schema()->create('config', function (Blueprint $table) {
            $table->id();
            $table->string('config_key', 100)->unique();
            $table->text('config_value')->nullable();
            $table->timestamps();
        });
        
        // Insert default configuration
        $configs = [
            ['config_key' => 'business_name', 'config_value' => 'Your Business Name'],
            ['config_key' => 'business_description', 'config_value' => 'WhatsApp Business API Mailbox'],
            ['config_key' => 'timezone', 'config_value' => 'UTC'],
        ];
        
        foreach ($configs as $config) {
            $config['created_at'] = date('Y-m-d H:i:s');
            $config['updated_at'] = date('Y-m-d H:i:s');
            Capsule::table('config')->insert($config);
        }
    }

    public function down()
    {
        Capsule::schema()->dropIfExists('config');
    }
};
