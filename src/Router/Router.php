<?php

namespace src\Router;

use src\Config\Database;
use src\Repository\UserRepository;
use src\Service\UserService;
use src\Controllers\UserController;
use src\Service\MailService;
use src\Repository\WalletRepository;
use src\Service\WalletService;
use src\Controllers\WalletController;
use src\Utility\UtilityHelper;
use src\Repository\WalletTransferRepository;
use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\KEY;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();

$userRepository = new UserRepository();
$mailService    = new MailService();
$userService    = new UserService($userRepository, $mailService);
$userController = new UserController($userService);

$walletTransferRepository = new WalletTransferRepository();
$walletRepository = new WalletRepository();
$mailService    = new MailService();
$walletService = new WalletService($userRepository, $walletRepository, $mailService);
$walletController = new WalletController($walletService);


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
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', 'Request not allowed', 405);
        }
        $userController->Login();
        break;

    case 'csrf-token':
        $userController->csrfToken();
        break;

    case 'verify-login':
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', 'Request not allowed', 405);
        }
        $userController->verifyLogin();
        break;

    case 'forgot-password':
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', 'Request not allowed', 405);
        }
        $userController->forgotPassword();
        break;

    case 'verify-token':
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', 'Request not allowed', 405);
        }
        $userController->verifyToken();
        break;

    case 'reset-password':
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', 'Request not allowed', 405);
        }
        $userController->passwordReset();
        break;

    case 'dashboard':
        if($method !== 'GET'){
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $walletController->dashboard();
        break;

    case 'add-wallet':
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $walletController->createWallet();
        break;

    case 'edit-wallet':
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $walletController->updateWallet();
        break;
    case 'delete-wallet':
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $walletController->deleteWallet();
        break;

    case 'fund-wallet':
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $walletController->fundWallet();
        break;

    case 'transactions-details':
        if($method !== 'GET'){
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $walletController->recentTransactions();
        break;

    case 'change-password':
        if($method !== 'POST'){
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $userController->changePassword();
        break;

    case 'confirm-change-password':
        if ($method !== 'POST') {
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $userController->verifyPasswordChange();
        break;

    case 'transfer-internal':
        if ($method !== 'POST') {
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $walletController->walletInternalTransfer();
        break;

    case 'transfer-external':
        if ($method !== 'POST') {
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $walletController->walletExternalTransfer();
        break;
    
    case 'logout':
        if ($method !== 'POST') {
            UtilityHelper::sendJson('error', "Request not allowed", 405);
        }
        $userController->logoutUser();
        break;
        
    default:
        UtilityHelper::sendJson('error', 'Invalid request method', 400);
        break;
}