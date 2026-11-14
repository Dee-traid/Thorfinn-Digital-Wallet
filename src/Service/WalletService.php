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
    private WalletTransferRepository $walletTransferRepository;
    private MailService $mailService;

    public function __construct(UserRepository $userRepository, WalletRepository $walletRepository, WalletTransferRepository $walletTransferRepository, MailService $mailService){
        $this->userRepository = $userRepository;
        $this->walletRepository = $walletRepository;
        $this->walletTransferRepository = $walletTransferRepository;
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

     public function updateWalletName(string $userId, string $walletId, string $walletName){
        try {

            if(!$walletName){
                UtilityHelper::sendJson('error', "Enter wallet name", 401);
                return;
            }
    
            $user = $this->userRepository->findUserByID($userId);
            if(!$user){
                UtilityHelper::sendJson('error', "User not found, Please Login", 404);
                return;
            }
    
            $wallet = $this->walletRepository->findWalletByUserID($walletId, $userId);
            if(!$wallet){
                UtilityHelper::sendJson('error', "Wallet not found", 404);
                return;
            }

            $updatedAt = new DateTimeImmutable();
    
            $wallet->setWalletName($walletName);
            $wallet->setUpdatedAt($updatedAt);
    
            $walletUpdate = $this->walletRepository->updateWalletDetails($wallet);

            try {
                $this->mailService->updateWallet($user->getEmail(), $user->getFullName(), $walletName);
            } catch (Throwable $e) {
                error_log("Failed to send wallet update email: " . $e->getMessage());
            }

            return $walletUpdate;
    
        } catch (Throwable $e) {
            error_log('Wallet update error: ' . $e->getMessage());
            UtilityHelper::sendJson('error', "An error occurred, Try again", 500);
        }
    }

    public function deleteWallet(string $userId, string $walletId, string $walletPin){
        
        try{

            $wallet = $this->walletRepository->findWalletByUserID($walletId, $userId);

            if(!$wallet){
                UtilityHelper::sendJson('error', "Wallet does not exist", 404);
                return;
            }
    
            $user = $this->userRepository->findUserByID($userId);
            if (!$user || !UtilityHelper::verifyValue($walletPin, $user->getWalletTransactionPinHash())) {
                UtilityHelper::sendJson('error', "Incorrect wallet pin, Try again", 404);
                return; 
            }

            $balance = $wallet->getBalance();
            if($balance > 0){
                UtilityHelper::sendJson('error', "Cannot delete wallet, Account is still funded", 400);
                return;
            }
            
            $deletedWallet = $this->walletRepository->deleteWallet($userId, $walletId);
            if(!$deletedWallet){
              return false;  
            }

            try {
                $this->mailService->deleteWallet($user->getEmail(), $user->getFullName(), $wallet->getWalletName());
            } catch (Throwable $e) {
                error_log("Failed to send wallet deletion email: " . $e->getMessage());
            }

            return $deletedWallet;
    
        }catch(Throwable $e){
            error_log('Wallet service deletion error: ' . $e->getMessage());
            UtilityHelper::sendJson('error', "An error occurred, Please try again", 500);
        }

    }

    public function fundWallet(string $userId, string $senderWalletId, string $beneficiaryWalletId, string $source, float $amount, string $pin){
        $user = $this->userRepository->findUserByID($userId);
        if(!$user){
            UtilityHelper::sendJson('error', "User not found, Please Login", 403);
            return;
        }

        $senderId = $this->getOwnedWalletById($senderWalletId, $userId);
        $senderBalance = $senderId->getBalance();

        $beneficiaryId = $this->getOwnedWalletById($beneficiaryWalletId, $userId); 
        $beneficiaryBalance = $beneficiaryId->getBalance();

        if($amount <= 0){
            UtilityHelper::sendJson('erro', "Funding amount should not be less than or equal to zero", 422);
            return;
        }

        if($amount > $senderBalance){
            UtilityHelper::sendJson('error', "Insufficient fund", 400);
            return;
        }

        if(!isset($pin) || !UtilityHelper::verifyValue($pin, $user->getWalletTransactionPinHash())){
            UtilityHelper::sendJson('error', "Incorrect wallet transfer pin", 403);
            return;
        }

        if(!in_array($source,['wallet', 'external'], true)){
            UtilityHelper::sendJson('error', "Select a funding source", 422);
            return;
        }

        if ($source === 'external') {
            $externalSource =  $this->fundFromExternal($userId, $beneficiaryWalletId, $amount);
            return $externalSource;
        }

        if($senderWalletId === $beneficiaryWalletId){
            UtilityHelper::sendJson('error', "Cannot fund wallet from same wallet", 422);
            return;
        }

        $transfer = $this->saveTransfer($senderWalletId, $beneficiaryWalletId, $amount);

        return $this->fundFromWallet($userId, $transfer);
    }

    private function saveTransfer(string $senderWalletId, string $beneficiaryWalletId, float $amount){
        $narration = 'Wallet funding';
        $transferId = uniqid('transfer-', true);
        $reference = 'FND-' . strtoupper(bin2hex(random_bytes(8)));
        $createdAt = new DateTimeImmutable();

        $walletTransfer = new WalletTransfer(
            $transferId, 
            $amount,
            $senderWalletId, 
            $beneficiaryWalletId, 
            $reference,
            $narration,
            $status = 'pending',
            $createdAt
        );

        $transfer = $this->walletTransferRepository->saveTransfer($walletTransfer);

        return $transfer;
    }

    private function recordTransactionHistory(string $walletId, float $before, float $amount, float $after, string $type, string $reference, string $narration, DateTimeImmutable $now): void{
            $id = uniqid('txn-', true);
            $ok = $this->walletTransferRepository->saveWalletTransactionHistory($id , $walletId, $before, $amount, $after, $type, $reference, $narration, $now
        );

        if(!$ok){
            UtilityHelper::sendJson('error', "Unable to save $type transaction history", 422);
        }
    }

    private function getOwnedWalletById(string $walletId, string $userId){
        $wallet = $this->walletRepository->findWalletByIDAndUserID($walletId, $userId);
        if(!$wallet){
            UtilityHelper::sendJson('error', "Wallet not found", 404);
            return;
        }

        return $wallet;
    }
    
    private function fundFromWallet(string $userId, WalletTransfer $transfer): array{
        $now = new DateTimeImmutable();
        $transferId = $transfer->getID();
        $senderWalletId = $transfer->getSenderWalletId();
        $beneficiaryWalletId = $transfer->getBeneficiaryWalletId();
        $amount = $transfer->getAmount();
        $reference = $transfer->getTransferReference();
        $narration = 'Wallet-Funding';

        $senderBalance = $this->walletRepository->getBalance($senderWalletId);
        $beneficiaryBalance = $this->walletRepository->getBalance($beneficiaryWalletId);

        $senderBalanceAfter = $senderBalance - $amount;
        $beneficiaryBalanceAfter = $beneficiaryBalance + $amount;

        try {   

            $this->walletRepository->beginTransaction();

            $debit = $this->walletRepository->debitWallet($senderWalletId, $userId, $amount, $now);
            if(!$debit){
                UtilityHelper::sendJson('error', "Insuffcient funds", 422);
            }

            $credit = $this->walletRepository->creditWallet($beneficiaryWalletId, $userId, $amount, $now);
            if(!$credit){
                UtilityHelper::sendJson('error', "Wallet not found", 422);
            }

            $senderTxnHistory = $this->recordTransactionHistory($senderWalletId, $senderBalance, $amount,  $senderBalanceAfter, 'debit', $reference . '-DR', $narration, $now);
            $beneficiaryTxnHistory = $this->recordTransactionHistory($beneficiaryWalletId, $beneficiaryBalance, $amount, $beneficiaryBalanceAfter, 'credit', $reference . '-CR', $narration, $now);

            $this->walletRepository->updateTransferStatus($transferId, 'successful');

            $this->walletRepository->commit();

            try {
                $user = $this->userRepository->findUserByID($userId);
                if ($user) {
                    $senderWallet = $this->walletRepository->findWalletByIDAndUserID($senderWalletId, $userId);
                    $beneficiaryWallet = $this->walletRepository->findWalletByIDAndUserID($beneficiaryWalletId, $userId);

                    $senderWalletName = $senderWallet ? $senderWallet->getWalletName() : 'Sender Wallet';
                    $beneficiaryWalletName = $beneficiaryWallet ? $beneficiaryWallet->getWalletName() : 'Beneficiary Wallet';

                    $this->mailService->sendTransactionNotification(
                        $user->getEmail(),
                        $user->getFullName(),
                        $amount,
                        'debit',
                        $senderWalletName,
                        $reference . '-DR'
                    );

                    $this->mailService->sendTransactionNotification(
                        $user->getEmail(),
                        $user->getFullName(),
                        $amount,
                        'credit',
                        $beneficiaryWalletName,
                        $reference . '-CR'
                    );
                }
            } catch (Throwable $e) {
                error_log("Failed to send transaction notification email: " . $e->getMessage());
            }

            return [
                'transfer_id' => $transferId,
                'reference'=> $reference,
                'status' => 'successful',
                'sender_balance_after'=> $senderBalanceAfter,
                'beneficiary_balance_after' => $beneficiaryBalanceAfter,
            ];

        } catch (Throwable $e) {
            $this->walletRepository->rollBack();
            $this->markTransactionFailed($transferId);
            error_log("Wallet funding failed: " . $e->getMessage());
            throw $e;
            UtilityHelper::sendJson('error', "Unable to complete", 500);
        }
    }
  
    private function markTransactionFailed(string $transferId){
        try{
            $status = 'failed';
            $this->walletRepository->updateTransferStatus($transferId, $status);
        }catch (Throwable $e) {
            error_log("Could not mark transfer $transferId as failed: " . $e->getMessage());
        }   
    }

 

}