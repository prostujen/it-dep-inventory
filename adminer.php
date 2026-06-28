<?php
// Custom Adminer configuration to allow empty password database access
function adminer_object() {
    require_once './adminer-helper.php';
    return new AdminerCustom;
}

// Include the core Adminer script
require './adminer-core.php';
