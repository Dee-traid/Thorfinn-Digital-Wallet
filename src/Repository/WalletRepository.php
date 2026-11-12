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

}