<?php

namespace src\Controllers;

use src\Service\WalletService;
use src\Utility\UtilityHelper;
use Throwable;

class WalletController{
    private WalletService $walletService;

    public function __construct(WalletService $walletService) {
        $this->walletService = $walletService;
    }

    public function dashboard(){
        try {

            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                UtilityHelper::sendJson('error', 'Please login', 401);
            }

            $data = $this->walletService->getDashboard($userId);
            UtilityHelper::sendJson('success', 'OK', 200, ['data' => $data]);

        } catch (Throwable $e) {
            error_log("Dashboard error: " . $e->getMessage());
            UtilityHelper::sendJson('error', 'Could not load dashboard', 500);
        }
    }

    public function createWallet(){
        try {
            
            $userId = $_SESSION['user_id'] ?? null;
            if(!$userId){
                UtilityHelper::sendJson('error', "Please Login", 401);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $walletName = strtolower(trim($data['wallet_name']));

            if (!isset($walletName) || empty(trim($walletName))) {
                UtilityHelper::sendJson('error', 'Wallet name is required', 400);
                return;
            }

            $attempt = 0;
            $maxAttempts = 5;
            do{

                $walletTag = UtilityHelper::generateWalletTag($walletName);
                $attempt++;

            }while(!$walletTag && $attempt < $maxAttempts);
            {

                if(!$walletTag){ 
                    UtilityHelper::sendJson('error', "An error occured while generating wallet tag.", 500);
                    return;
                } 
            }

            $wallet = $this->walletService->createWallet($userId, $walletName, $walletTag);

            if (!$wallet) {
                UtilityHelper::sendJson('error', 'Wallet could not be created', 500);
                return;
            }
    
            UtilityHelper::sendJson('success', 'Wallet created successfully', 201);

        } catch (Throwable $e) {
            error_log("Wallet creation validation error" . $e->getMessage());
            UtilityHelper::sendJson('error', "An error occurred, Please try again", 500);
        }
    }


}