<?php 

namespace src\Repository;

use src\Models\Wallet;
use src\Models\WalletTransfer;
use src\Config\Database;
use PDO;
use PDOException;
use Exception;
use DateTimeImmutable;

class WalletTransferRepository{
    private PDO $db;

    public function __construct(){
        $this->db = Database::getDatabaseConnection();
    }

    public function saveTransfer(WalletTransfer $transfer): ?WalletTransfer{
        $id = $transfer->getID();
        $amount = $transfer->getAmount();
        $senderWalletId = $transfer->getSenderWalletId();
        $beneficiaryWalletId = $transfer->getBeneficiaryWalletId();
        $reference = $transfer->getTransferReference();
        $narration = $transfer->getNarration();
        $status = $transfer->getStatus();
        $createdAt = $transfer->getCreatedAt()->format('Y-m-d H:i:s');

        $query = "INSERT INTO wallet_transfers (id, amount, sender_wallet_id, beneficiary_wallet_id, transfer_reference, narration, status, created_at) VALUES (:id, :amount, :senderWalletId, :beneficiaryWalletId, :transferReference, :narration, :status, :createdAt)";
        
        try {

            $this->db->beginTransaction();

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':senderWalletId', $senderWalletId);
            $stmt->bindParam(':beneficiaryWalletId', $beneficiaryWalletId);
            $stmt->bindParam(':transferReference', $reference);
            $stmt->bindParam(':narration', $narration);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':createdAt', $createdAt);
            $stmt->execute();

            $this->db->commit();

           return $transfer;

        } catch (PDOException $e) {
            $this->db->rollback();
            throw new PDOException("Error saving wallet transfer: " . $e->getMessage());
        }
    }

    public function saveWalletTransactionHistory(string $id, string $walletId, float $balanceBefore, float $amount, float $balanceAfter, string $type, string $reference, string $narration, DateTimeImmutable $createdAt): bool{
        $query = "INSERT INTO wallet_transactions_history (id, wallet_id, balance_before, amount, balance_after, transaction_type, transaction_reference, narration, created_at) VALUES (:id, :walletId, :balanceBefore, :amount, :balanceAfter, :transactionType, :transactionReference, :narration, :createdAt)";

        $transactionType = $type;
        $transactionReference = $reference;
        $createdAt = $createdAt->format('Y-m-d H:i:s');
    
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':walletId', $walletId);
            $stmt->bindParam(':balanceBefore', $balanceBefore);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':balanceAfter', $balanceAfter);
            $stmt->bindParam(':transactionType', $transactionType);
            $stmt->bindParam(':transactionReference', $transactionReference);
            $stmt->bindParam(':narration', $narration);
            $stmt->bindParam(':createdAt', $createdAt);
            $stmt->execute();

            return $stmt->rowCount() === 1;

        } catch (PDOException $e) {
            throw new PDOException("Error saving wallet transaction history: " . $e->getMessage());
        }
    }



}