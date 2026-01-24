<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Migration: add 'react' action_type to message_actions enum
 */
class AddReactActionTypeMigration
{
    public function up()
    {
        // Extend enum to include 'react'
        Capsule::statement("ALTER TABLE message_actions MODIFY action_type ENUM('star','forward','delete','archive','react') DEFAULT 'star'");
    }

    public function down()
    {
        // Revert to original enum without react
        Capsule::statement("ALTER TABLE message_actions MODIFY action_type ENUM('star','forward','delete','archive') DEFAULT 'star'");
    }
}
