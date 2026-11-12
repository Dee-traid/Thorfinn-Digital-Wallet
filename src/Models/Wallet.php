<?php

namespace src\Models;

use DateTimeImmutable;
use src\Interface\Arrayable;

class Wallet extends BaseModel implements Arrayable{
    private string $userId;
    private string $walletName;
    private float $balance;
    private string $walletTag;

    public function __construct
        (
            string $id, 
            string $userId,
            string $walletName,
            float $balance,
            string $walletTag,
            DateTimeImmutable $createdAt,
            DateTimeImmutable $updatedAt
        )
    {
        parent::__construct($id, $createdAt, $updatedAt);

        $this->userId = $userId;
        $this->walletName = $walletName;
        $this->balance = $balance;
        $this->walletTag = $walletTag;
    }

    public function getUserId(){ return $this->userId; }
    public function getWalletName(){ return $this->walletName;}
    public function getBalance(){ return $this->balance; }
    public function getWalletTag(){ return $this->walletTag; }

    public function setID(string $id): void{ $this->id = $id;}
    public function setUserID(string $userId): void{ $this->userId = $userId;}
    public function setWalletName(string $walletName): void{ $this->walletName = $walletName;}
    public function setBalance(float $balance): void{$this->balance = $balance; }
    public function setWalletTag(string $walletTag): void{$this->walletTag = $walletTag;}
    
   
    public static function mapToRow(array $row): static{
        $id = $row['id'];
        $userId = $row['user_id'];
        $walletName = $row['wallet_name'];
        $balance = (float)($row['balance']);
        $walletTag = $row['wallet_tag'];
        $createdAt =  new DateTimeImmutable($row['created_at']);
        $updatedAt =  new DateTimeImmutable($row['updated_at']);

        $wallet = new Wallet(
            $id,
            $userId,
            $walletName,
            $balance,
            $walletTag,
            $createdAt,
            $updatedAt
        );

        return $wallet;
    }

    public function toArray(): array{
        $walletName = $this->getWalletName();
        $balance = $this->getBalance();
        $walletTag = $this->getWalletTag();
        $wallet = [$walletName, $balance, $walletTag];

        return $wallet;
    }
    
}