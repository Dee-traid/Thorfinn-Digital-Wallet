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

}