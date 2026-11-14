<?php 

namespace src\Repository;

use src\Models\Wallet;
use src\Config\Database;
use PDO;
use PDOException;
use Exception;
use DateTimeImmutable;

class WalletRepository{
    private PDO $db;

    public function __construct(){
        $this->db = Database::getDatabaseConnection();
    }


    public function getWalletsWithTxCount(string $userId): array{
        $sql = "SELECT w.id, w.wallet_name AS name, w.balance, w.wallet_tag AS tag,
                       COUNT(t.id) AS tx_count
                FROM wallets w
                LEFT JOIN wallet_transactions_history t ON t.wallet_id = w.id
                WHERE w.user_id = :userId
                GROUP BY w.id
                ORDER BY w.created_at ASC";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userId', $userId);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getWalletsWithTxCount: " . $e->getMessage());
            throw new PDOException("Error fetching wallets");
        }
    }

    public function getRecentTransactions(string $userId, ?string $walletId = null, int $limit = 10): array{
        $query = "SELECT t.id, t.amount, t.narration AS description, CASE t.transaction_type WHEN 'credit' THEN 'in' ELSE 'out' END AS direction, t.created_at, w.wallet_name FROM wallet_transactions_history t
                JOIN wallets w ON w.id = t.wallet_id
                WHERE w.user_id = :userId"
                . ($walletId ? " AND w.id = :walletId" : "") .
                " ORDER BY t.created_at DESC
                LIMIT :limit";

        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':userId', $userId);
            if ($walletId) {
                $stmt->bindParam(':walletId', $walletId);
            }
            $stmt->bindParam(':limit', $limit);
            
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $rows;

        } catch (PDOException $e) {
            error_log("Repository Error fetching recent transactions: " . $e->getMessage());
            throw new PDOException("Error fetching recent transactions: " . $e->getMessage());
        }
    }

    public function beginTransaction(){
        $this->db->beginTransaction();
    }

    public function commit(): void{
        $this->db->commit();
    }
 
    public function rollBack(): void{
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function findWalletByUserID(string $walletId, string $userId): ?Wallet{
        $query = "SELECT * FROM wallets WHERE user_id = :userId AND id = :walletId LIMIT 5";
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':walletId', $walletId);
            $stmt->execute();
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            return $wallet ?Wallet::mapToRow($wallet): null; 
        } catch (PDOException $e) {
            error_log("Error finding wallet by user's id:" . $e->getMessage());
            throw new Exception ("Mapper's error");
        }
    }

    public function findWalletByIDAndUserID(string $id, string $userId): ?Wallet{
        $query = "SELECT * FROM wallets WHERE id = :id AND user_id = :userId LIMIT 1";
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            return $wallet ?Wallet::mapToRow($wallet): null; 

        } catch (PDOException $e) {
            error_log("Error finding User and Wallet id:" . $e->getMessage());
            throw new Exception ("Error finding User and Wallet id");
        }
    }

    public function saveWallet(Wallet $wallet): ?Wallet{
        $id = $wallet->getID();
        $userId = $wallet->getUserId();
        $walletName = $wallet->getWalletName();
        $balance = $wallet->getBalance();
        $walletTag = $wallet->getWalletTag();
        $createdAt = $wallet->getCreatedAt()->format('Y-m-d H:i:s');
        $updatedAt = $wallet->getUpdatedAt()->format('Y-m-d H:i:s');
        $query = "INSERT INTO wallets (id, user_id, wallet_name, balance, wallet_tag, created_at, updated_at) VALUES (:id, :userId, :walletName, :balance, :walletTag, :createdAt, :updatedAt)";

        try{
        
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':walletName', $walletName);
            $stmt->bindParam(':balance', $balance);
            $stmt->bindParam(':walletTag', $walletTag);
            $stmt->bindParam(':createdAt', $createdAt);
            $stmt->bindParam(':updatedAt', $updatedAt);
            $stmt->execute();

            return $wallet;
            
        }catch(PDOException $e){
            error_log("Wallet creation database error" . $e->getMessage());
            throw new Exception("Database error saving wallet in the database" . $e->getMessage());
        }
    }

    public function findWalletTag(string $walletTag){

        $query = "SELECT * FROM wallets WHERE wallet_tag = :walletTag LIMIT 1";
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':walletTag', $walletTag);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            return Wallet::mapToRow($row);

        } catch (PDOException $e) {
            error_log("Wallet tag error" . $e->getMessage());
            throw new PDOException("Error fetching wallet tag" . $e->getMessage());
        }
    }

    public function countTotalWalletPerUser(string $userId){
        $query = "SELECT COUNT(*) FROM wallets WHERE user_id = :userId LIMIT 1";
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $wallets = (int) $stmt->fetchColumn();

            return $wallets;

        } catch (PDOException $e) {
            error_log("Error fetching wallets by user's id" . $e->getMessage());
            throw new PDOException("Error fetching walles" . $e->getMessage());
        }
    }


    public function updateWalletDetails(Wallet $wallet): bool{
        $walletName = $wallet->getWalletName() ?? '';
        $updatedAt = $wallet->getUpdatedAt()->format('Y-m-d H:i:s') ?? '';
        $id = $wallet->getID();
        $userId = $wallet->getUserID();

        $query = "UPDATE wallets SET wallet_name = :walletName, updated_at = :updatedAt WHERE id = :id AND user_id = :userId";
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':walletName', $walletName);
            $stmt->bindParam(':updatedAt', $updatedAt);
            $stmt->execute();
            $wallet = $stmt->rowCount() > 0;

            return $wallet; 

        } catch (PDOException $e) {
            error_log("Wallet update error" . $e->getMessage());
            throw new PDOException("Error updating wallet details" . $e->getMessage());
        }
    }

    public function deleteWallet(string $userId, string $walletId): bool{
        $query = "DELETE FROM wallets WHERE user_id = :userId AND id = :id AND balance = 0 AND NOT EXISTS (SELECT 1 FROM wallet_transactions_history WHERE wallet_transactions_history.wallet_id = wallets.id)";

        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $walletId);
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $deletedWallet = $stmt->rowCount() === 1;
            if(!$deletedWallet){
                return false;
            }

            return true;

        } catch (PDOException $e) {
            throw new PDOException("Error deleting wallet: " . $e->getMessage());
        }
    }




}