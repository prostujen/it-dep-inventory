<?php
// Custom Adminer configuration to allow empty password database access
function adminer_object() {
    class AdminerCustom extends Adminer {
        function login($login, $password) {
            // Allow empty password login
            return true;
        }
    }
    return new AdminerCustom;
}

// Include the core Adminer script
require './adminer-core.php';
