<?php

namespace src\Service;

use src\Repository\WalletRepository;
use src\Repository\UserRepository;
use src\Models\User;
use src\Models\WalletTransfer;
use src\Repository\WalletTransferRepository;
use src\Models\Wallet;
use src\Utility\UtilityHelper;
use DateTimeImmutable;
use Throwable;

class WalletService{
    private UserRepository $userRepository;
    private WalletRepository $walletRepository;
    //private WalletTransferRepository $walletTransferRepository;
    private MailService $mailService;

    public function __construct(UserRepository $userRepository, WalletRepository $walletRepository, MailService $mailService){
        $this->userRepository = $userRepository;
        $this->walletRepository = $walletRepository;
        //$this->walletTransferRepository = $walletTransferRepository;
        $this->mailService = $mailService;
    }

    public function getDashboard(string $userId): array{
        $user = $this->userRepository->findUserByID($userId);
        if (!$user) 
        {
            UtilityHelper::sendJson('error', "Please log in again", 401);
        }
    
        $parts = preg_split('/\s+/', trim($user->getFullName()));
        $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) .
                    (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    
        $wallets = array_map(fn($w) => [
            'id'       => $w['id'],
            'name'     => $w['name'],
            'balance'  => (float) $w['balance'],
            'tag'      => $w['tag'],
            'tx_count' => (int) $w['tx_count'],
        ], $this->walletRepository->getWalletsWithTxCount($userId));
    
        $transactions = array_map(function ($t) {
            $t['amount'] = (float) $t['amount'];
            return $t;
        }, $this->walletRepository->getRecentTransactions($userId));
    
        return [
            'user'          => ['first_name' => $parts[0], 'initials' => $initials],
            'wallets'       => $wallets,
            'total_balance' => array_sum(array_column($wallets, 'balance')),
            'transactions'  => $transactions,
        ];
    }

    public function createWallet(string $userId, string $walletName, string $walletTag): ?Wallet{
        try {
         
             $user = $this->userRepository->findUserByID($userId);
             if(!$user){
                 UtilityHelper::sendJson('error', "User not found", 404);
             }
 
             $email = $user->getEmail();
             $fullName = $user->getFullName();
 
             $id = uniqid('wallet-',true);
             $balance = 0.00;
             $timeStamp = new DateTimeImmutable();
             
             $existingWalletTag = $this->walletRepository->findWalletTag($walletTag);
             if($existingWalletTag){
                 UtilityHelper::sendJson('error', "Wallet tag already exist", 409);
             }
 
             $wallets = $this->walletRepository->countTotalWalletPerUser($userId);
             if($wallets >= 5){
                 UtilityHelper::sendJson('error', "Users' wallet creation limit of 5 wallets has been reached.", 409);
             }
 
             $wallet = new Wallet(
                 $id,
                 $userId,
                 $walletName,
                 $balance,
                 $walletTag,
                 $timeStamp,
                 $timeStamp
             );
 
             $savedWallet = $this->walletRepository->saveWallet($wallet);
             $this->mailService->addWallet($email, $fullName, $walletName);
 
             if($savedWallet !== null){
                 UtilityHelper::sendJson('success', "Wallet created successfully", 201);
             }
 
             return $savedWallet;
        } catch (Throwable $e) {
             error_log('Error saving new wallet to data' . $e->getMessage());
             UtilityHelper::sendJson('error', "An error occurred, Please try again", 500);
             return null;
        }
     }
 

}