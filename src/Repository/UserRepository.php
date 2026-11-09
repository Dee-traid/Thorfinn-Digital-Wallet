<?php 

namespace src\Repository;

use src\Models\User;
use src\Config\Database;
use PDO;
Use PDOException;
use DateTimeImmutable;

class UserRepository{
    private PDO $db;

    public function __construct(){
        $this->db = Database::getDatabaseConnection();
    }

    public function findUserByID(string $id): ?User
    {
        $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ? User::mapToRow($user): null;
    }

    public function findUserByEmail(string $email): ?User
    {
        $query = "SELECT * FROM users WHERE LOWER(email) = :email LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ? User::mapToRow($user): null;
    }

    public function createUser(User $user): ?User
    {
        $id = $user->getID();
        $fullName = $user->getFullName();
        $email = strtolower($user->getEmail());
        $passwordHash = $user->getPasswordHash();
        $walletTransactionPinHash = $user->getWalletTransactionPinHash();
        $isActive = $user->getIsActive();
        $emailVerified = $user->getEmailVerified();
        $createdAt = $user->getCreatedAt()->format('Y-m-d H:i:s');
        $updatedAt = $user->getUpdatedAt()->format('Y-m-d H:i:s');
        try{
            $query = "INSERT INTO users (id, full_name, email, password_hash, wallet_transaction_pin_hash, is_active, email_verified, created_at, updated_at) VALUES (:id, :fullName, :email, :passwordHash, :walletTransactionPinHash, :isActive, :emailVerified, :createdAt, :updatedAt)";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':fullName', $fullName);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':passwordHash', $passwordHash);
            $stmt->bindParam(':walletTransactionPinHash', $walletTransactionPinHash);
            $stmt->bindParam(':isActive', $isActive);
            $stmt->bindParam(':emailVerified', $emailVerified);
            $stmt->bindParam(':createdAt', $createdAt);
            $stmt->bindParam(':updatedAt', $updatedAt);
            $stmt->execute();
            
            return $user;

        }catch(PDOException $e){
            error_log("Error creating User" . $e->getMessage());
            throw new PDOException("Registration error");
        }
        
    }

    public function updateUser(User $user): ?User
    {
        $id = $user->getId();
        $fullName = $user->getFullName();
        $email = $user->getEmail();
        $updatedAt = $user->getUpdatedAt();
        
        try {
            
            $query = "UPDATE users SET full_name = :fullName, updated_at = :updatedAt WHERE id = :id ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id) ?? null ;
            $stmt->bindParam(':fullName', $fullName) ?? null;
            $stmt->bindParam(':updatedAt', $updatedAt) ?? null;
            $stmt->execute();

            return $this->user->findByUserID($user->getID());

        } catch (PDOException $e){
            error_log("Error updating user" . $e->getMessage());
            throw new PDOException("Error updating user" . $e->getMessage());
        }
        
    }

    public function deleteUser(string $id): ?User
    {
        $id = $user->getId();

        try {

            $query = "DELETE FROM users WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            return $stmt->rowCount() > 0;

        } catch (PDOException $e){
            error_log("Error deleting user" . $e->getMessage());
            throw new PDOException("Error deleting user: " . $e->getMessage());
        }
    }

    public function saveVerificationToken(string $userId, string $email, string $tokenHash,DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): void{
        $deleteQuery = "DELETE FROM email_verification WHERE user_id = :userId";
        $saveQuery = "INSERT INTO email_verification(user_id, email, token_hash, created_at, expires_at) VALUES (:userId, :email, :tokenHash, :createdAt, :expiresAt)";

        try{

            $stmt = $this->db->prepare($deleteQuery);
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();

        }  catch (PDOException $e) {
            throw new PDOException("Error truancating past records: " . $e->getMessage());
        }

        $createdAt = $createdAt->format('Y-m-d H:i:s');
        $expiresAt = $expiresAt->format('Y-m-d H:i:s');

        try {

            $stmt = $this->db->prepare($saveQuery);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':tokenHash', $tokenHash);
            $stmt->bindParam(':createdAt', $createdAt);
            $stmt->bindParam(':expiresAt', $expiresAt);
            $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error saving token verification" . $e->getMessage());
            throw new PDOException("Unable to save record: " . $e->getMessage());
        }
    }

    public function findTokenVerification(string $tokenHash): ?array
    {
        $query = "SELECT user_id, expires_at FROM email_verification WHERE token_hash = :tokenHash";
        
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':tokenHash', $tokenHash);
            $stmt->execute();
    
            $token = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            return $token;

        } catch (PDOException $e){
            error_log("Error finding token verification" . $e->getMessage());
            throw new PDOException("Error finding record: " . $e->getMessage());
        }
       
    }

    public function markEmailVerified(string $userId){
        $query = "UPDATE users SET email_verified = 1, updated_at = :updatedAt WHERE id = :userId";
        $updatedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        try{

            $this->db->beginTransaction();
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':updatedAt', $updatedAt);
            $stmt->execute();

            $tokenRemoval = $this->db->prepare("DELETE FROM email_verification WHERE user_id = :userId");
            $tokenRemoval->bindParam(':userId', $userId);
            $tokenRemoval->execute();

            $this->db->commit();

        }catch(PDOException $e){
            $this->db->rollback();
            error_log("Error marking user email verified" . $e->getMessage());
            throw new PDOException("Error updating record status" . $e->getMessage());
        }
    }

    public function updateLastLogin(string $userId){
        $query = "UPDATE users SET last_login_at = :now WHERE id = :id";
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

        } catch (PDOException $e) {
            error_log("Updating user last Error" . $e->getMessage());
            throw new PDOException("Error updating record status: " . $e->getMessage());
        }
    }

    public function passwordUpdate(User $user): bool{
        $query = "UPDATE users SET password_hash = :passwordHash, updated_at = :updatedAt WHERE id =:userId";
        try {

            $passwordHash = $user->getPasswordHash();
            $userId = $user->getID();
            $updatedAt = $user->getUpdatedAt()->format('Y-m-d H:i:s');

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':passwordHash', $passwordHash);
            $stmt->bindParam(':updatedAt', $updatedAt);
            $stmt->execute();

            if($stmt->rowCount() > 0){
                return true;

            }else{              
                return false;
            }

        } catch (PDOException $e) {
            error_log("Password Update Repository Error" . $e->getMessage());
            throw new PDOException("Error updating record status: " . $e->getMessage());
        }
    }

    public function saveToken(string $userId, string $email, string $tokenHash, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt){
        try {

            $createdAt = $createdAt->format('Y-m-d H:i:s');
            $expiresAt = $expiresAt->format('Y-m-d H:i:s');

            $query = "INSERT INTO token (user_id, email, token_hash, created_at, expires_at) VALUES (:userId, :email, :tokenHash, :createdAt, :expiresAt)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':tokenHash', $tokenHash);
            $stmt->bindParam(':createdAt', $createdAt);
            $stmt->bindParam(':expiresAt', $expiresAt);
            $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error saving token for user email verification" . $e->getMessage());
            throw new PDOException("Error saving token: ");
        }
    }

    public function findVerificationToken(string $token){
        $query = "SELECT user_id, expires_at FROM token WHERE  token_hash= :tokenHash";
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':tokenHash', $token);
            $stmt->execute();

            $token = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            return $token;

        } catch (PDOException $e) {
            error_log("Error finding token " . $e->getMessage());
            throw new PDOException("Error fetching record: ");
        }
    }

    public function loginAttempts(string $email): int{
        $query = "SELECT COUNT(*) FROM login_attempts WHERE email = :email  AND attempted_at > datetime('now',  '-5 minutes')";
        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $loginAttempts = (int)$stmt->fetchColumn();
            return $loginAttempts;

        } catch (PDOException $e) {
            error_log("Error finding record " . $e->getMessage());
            throw new PDOException("Error fetching record: " . $e->getMessage());
        }
    }

    public function saveLoginAttempts(string $userId, string $email, string $ip){
        $attemptedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $query = "INSERT INTO login_attempts (user_id, email, ip_address, attempted_at) VALUES (:userId, :email, :ip_address, :attemptedAt)";

        try {

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':userId', $userId);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':ip_address', $ip);
            $stmt->bindParam(':attemptedAt', $attemptedAt);
            $stmt->execute();

        } catch (PDOException $e) {
            error_log("Error saving login attempts by user" . $e->getMessage());
            throw new PDOException("Error Processing Request" . $e->getMessage());
            
        }
    }

    public function deleteLoginAttempts(string $email): void{
        $query = "DELETE FROM login_attempts WHERE email = :email";

       try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
       } catch (PDOException $e) {
            error_log("Error deleting login attempt" . $e->getMessage());
            throw new PDOException("Unable to process request" . $e->getMessage());
       }

    }

}


