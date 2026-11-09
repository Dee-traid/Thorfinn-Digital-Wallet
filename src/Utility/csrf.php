<?php

namespace src\Utility;

class CSRF{
    public static function validateSession(){
        if(session_status() === PHP_SESSION_NONE){
            session_start();
        }
    }
    public static function generateCsrfToken(){
        self::validateSession();
        if(empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])){
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(string $token): bool{ 
        self::validateSession();
        if(empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])  || $token === '')
        { 
            return false;  
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function regenerateCsrfToken(): string {
        self::validateSession();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}



