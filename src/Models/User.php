<?php

namespace src\Models;

use DateTimeImmutable;
use src\Interface\Arrayable;

class User extends BaseModel implements Arrayable{
    private string $fullName;
    private string $email;
    private string $passwordHash;
    private string $walletTransactionPinHash;
    private bool $isActive;
    private bool $emailVerified;
    
    public function __construct
        (   
            string $id,
            string $fullName, 
            string $email, 
            string $passwordHash, 
            string $walletTransactionPinHash, 
            bool $isActive, 
            bool $emailVerified,
            DateTimeImmutable $createdAt,
            DateTimeImmutable $updatedAt
        )
    {
        parent::__construct($id, $createdAt, $updatedAt);

        $this->fullName = $fullName;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->walletTransactionPinHash = $walletTransactionPinHash;
        $this->isActive = $isActive;
        $this->emailVerified = $emailVerified;
    }

    public function getFullName(): string{return $this->fullName; }
    public function getEmail(): string{return $this->email; }
    public function getPasswordHash(): string{return $this->passwordHash; }
    public function getWalletTransactionPinHash(): string{return $this->walletTransactionPinHash; }
    public function getIsActive(): bool{return $this->isActive; }
    public function getEmailVerified(): bool{return $this->emailVerified; }
    
    public function setFullName(string $fullName): void{$this->fullName = $fullName; }
    public function setEmail(string $email): void{$this->email = $email; }
    public function setPasswordHash(string $passwordHash): void{$this->passwordHash = $passwordHash; }
    public function setWalletTransactionPinHash(string $walletTransactionPinHash): void{$this->walletTransactionPinHash = $walletTransactionPinHash; }
    public function setIsActive(bool $isActive): void{$this->isActive = $isActive; }
    public function setEmailVerified(bool $emailVerified): void{$this->emailVerified = $emailVerified; }

    public static function mapToRow(array $row): static{
        $id = $row['id'];
        $fullName = $row['full_name'];
        $email = $row['email'];
        $passwordHash = $row['password_hash'];
        $walletTransactionPinHash = $row['wallet_transaction_pin_hash'];
        $isActive = (bool) $row['is_active'];
        $emailVerified = (bool) $row['email_verified'];
        $createdAt = new DateTimeImmutable($row['created_at']);
        $updatedAt = new DateTimeImmutable($row['updated_at']);

        $user = new static(
            $id, 
            $fullName, 
            $email, 
            $passwordHash, 
            $walletTransactionPinHash, 
            $isActive, 
            $emailVerified, 
            $createdAt,
            $updatedAt
        );
        return $user;
    }

    public function toArray(): array{
        $fullName = $this->getFullName();
        $email = $this->getEmail();
        $user = [$fullName, $email];

        return $user;
    }

}