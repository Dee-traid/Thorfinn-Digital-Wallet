<?php

namespace src\Models;

use DateTimeImmutable;
use src\Interface\Arrayable;

class WalletTransfer extends BaseModel{
    private float $amount;
    private string $senderWalletId;
    private string $beneficiaryWalletId;
    private string $transferReference;
    private string $narration;
    private string $status;

    public function __construct
    (
        string $id,
        float $amount,
        string $senderWalletId,
        string $beneficiaryWalletId,
        string $transferReference,
        string $narration,
        string $status,
        DateTimeImmutable $createdAt
    ){
        parent::__construct($id, $createdAt, $createdAt);

        $this->amount = $amount;
        $this->senderWalletId = $senderWalletId;
        $this->beneficiaryWalletId = $beneficiaryWalletId;
        $this->transferReference = $transferReference;
        $this->narration = $narration;
        $this->status = $status;
    }

    public function getAmount(){ return $this->amount; }
    public function getSenderWalletId(){ return $this->senderWalletId; }
    public function getBeneficiaryWalletId(){ return $this->beneficiaryWalletId; }
    public function getTransferReference(){ return $this->transferReference; }
    public function getNarration(){ return $this->narration; }
    public function getStatus(){ return $this->status; }

    public function setAmount(float $amount): void { $this->amount = $amount; }
    public function setSenderWalletId(int $senderWalletId): void { $this->senderWalletId = $senderWalletId; }
    public function setBeneficiaryWalletId(int $beneficiaryWalletId): void { $this->beneficiaryWalletId = $beneficiaryWalletId; }
    public function setTransferReference(string $transferReference): void { $this->transferReference = $transferReference; }
    public function setNarration(string $narration): void { $this->narration = $narration; }
    public function setStatus(string $status): void { $this->status = $status; }

    public static function mapToRow(array $row): static
    {
        $id = $row['id'];
        $amount = (float) $row['amount'];
        $senderWalletId = $row['sender_wallet_id'];
        $beneficiaryWalletId = $row['beneficiary_wallet_id'];
        $transferReference = $row['transfer_reference'];
        $narration = $row['narration'];
        $status = $row['status'];
        $createdAt = DateTimeImmutable($row['createdAt']);

        $transaction = new Transaction(
            $id,
            $amount,
            $senderWalletId,
            $beneficiaryWalletId,
            $transferReference,
            $narration,
            $status,
            $createdAt
        );

        return $transaction;
    }



}