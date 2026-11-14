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

    public function updateWallet(){
        try{

            $userId = $_SESSION['user_id'] ?? null;
            if(!$userId){
                UtilityHelper::sendJson('error', "Please Login", 401);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $walletName = trim(strtoUpper($data['wallet_name'] ?? ''));
            $walletId = $data['id'] ?? null;

            if ($walletName === '') {
                UtilityHelper::sendJson('error', 'Enter your new wallet name', 400);
                return;
            }

            if(!$walletId){
                UtilityHelper::sendJson('error', "Invalid wallet ID", 400);
                return;
            }

           $walletUpdate =  $this->walletService->updateWalletName($userId, $walletId, $walletName);
           if(!$walletUpdate){
            UtilityHelper::sendJson('error', "Error updating Wallet", 500);
           }

           UtilityHelper::sendJson('success', "Wallet name updated successfully", 200);

        }catch(Throwable $e){
            error_log("Wallet name update Validation error: " . $e->getMessage());
            UtilityHelper::sendJson('error', "Error updating wallet", 500);
        }
    }

    public function deleteWallet(){
        try {
            
            $userId = $_SESSION['user_id'] ?? '';
            if(!$userId){
                UtilityHelper::sendJson('error', "Please Login", 401);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $walletId = $data['id'] ?? '';
            $walletPin = trim($data['pin'] ?? '');

            if($walletId === ''){
                UtilityHelper::sendJson('error', "Wallet not found", 400);
                return;
            }

            if($walletPin === ''){
                UtilityHelper::sendJson('error', "Enter your wallet transaction pin", 401);
                return;
            }

            $deleted = $this->walletService->deleteWallet($userId, $walletId, $walletPin);
            if(!$deleted){
                UtilityHelper::sendJson('error', "Deletion failed. Wallet cannot be deleted (must have 0 balance and no transactions).", 403);
                return;
            }

            UtilityHelper::sendJson('success', "Wallet ". $walletId . " deleted successfully", 201);
        } catch (Throwable $e) {
            error_log("Wallet deletion Validation error: " . $e->getMessage());
            UtilityHelper::sendJson('error', "An error occurred while deleting  wallet", 500);
        }

    }



}