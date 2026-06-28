<?php
// This file is loaded dynamically by Adminer at runtime after the Adminer class is defined.
class AdminerCustom extends Adminer\Adminer {
    function login($login, $password) {
        // Allow empty password database connections for development/demo
        return true;
    }
}
