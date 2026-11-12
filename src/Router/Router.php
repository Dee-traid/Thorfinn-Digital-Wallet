<?php

namespace src\Router;

use src\Config\Database;
use src\Repository\UserRepository;
use src\Service\UserService;
use src\Controllers\UserController;
use src\Service\MailService;
use src\Utility\UtilityHelper;
use Dotenv\Dotenv;
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

/* Registration API Handler */
$userRepository = new UserRepository();
$mailService    = new MailService();
$userService    = new UserService($userRepository, $mailService);
$userController = new UserController($userService);

// $walletTransferRepository = new WalletTransferRepository();
// $walletRepository = new WalletRepository();
// $mailService    = new MailService();
// $walletService = new WalletService($userRepository, $walletRepository, $walletTransferRepository, $mailService);
// $walletController = new WalletController($walletService);




if(session_status() === PHP_SESSION_NONE){
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '', 
        'secure' => false, 
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'register':
        if ($method !== 'POST') {
            UtilityHelper::sendJson('error', 'Request not allowed', 405);
        }
        $userController->register();
        break;
    
    case 'verify-email':
        if($method !== 'GET'){
            UtilityHelper::sendJson('error', 'Request not allowed', 405);
        }
        $userController->verifyEmail();
        break;
    
    case 'resend-verification':
        if ($method !== 'POST') {
            UtilityHelper::sendJson('error', 'Request not allowed', 405);
        }
        $userController->resendVerification();
        break;
    case 'login':
        UtilityHelper::sendJson('error', 'Request not allowed', 501);
        break;

    default:
        UtilityHelper::sendJson('error', 'Invalid request method', 400);
        break;
}