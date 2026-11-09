<?php

namespace src\Models;

use DateTimeImmutable;
use src\Interface\Arrayable;

class Wallet extends BaseModel implements Arrayable{
    private string $userId;
    private string $walletName;
    private string $walletName;
    private float $balance;
    private string $walletTag;

    public function __construct
        (
            string $id, 
            string $userId,
            string $walletName,
            string $walletName,
            float $balance,
            string $walletTag,
            DateTimeImmutable $createdAt,
            DateTimeImmutable $updatedAt
            DateTimeImmutable $createdAt,
            DateTimeImmutable $updatedAt
        )
    {
        parent::__construct($id, $createdAt, $updatedAt);

        parent::__construct($id, $createdAt, $updatedAt);

        $this->userId = $userId;
        $this->walletName = $walletName;
        $this->walletName = $walletName;
        $this->balance = $balance;
        $this->walletTag = $walletTag;
    }

    public function getUserId(){ return $this->userId; }
    public function getWalletName(){ return $this->walletName;}
    public function getWalletName(){ return $this->walletName;}
    public function getBalance(){ return $this->balance; }
    public function getWalletTag(){ return $this->walletTag; }

    public function setID(string $id): void{ $this->id;}
    public function setUserID(string $userId): void{ $this->userId;}
    public function setWalletName(string $walletName): void{ $this->walletName;}
    public function setBalance(float $balance): void{$this->balance = $balance; }
    public function setWalletTag(string $walletTag): void{$this->walletTag = $walletTag; }
    public function setCreatedAt(DateTime $createdAt): void{$this->createdAt = $createdAt; }
    public function setUpdatedAt(DateTime $updatedAt): void{$this->updatedAt = $updatedAt; }
    
}