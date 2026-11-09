<?php

namespace src\Models;

use DateTimeImmutable;

abstract class BaseModel{
    protected string $id;
    protected DateTimeImmutable $createdAt;
    protected DateTimeImmutable $updatedAt;

    public function __construct
        (
            string $id,
            DateTimeImmutable $createdAt,
            DateTimeImmutable $updatedAt
        )
    {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getID(): string{return $this->id; }
    public function getCreatedAt(){return $this->createdAt; }
    public function getUpdatedAt(){return $this->updatedAt; }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void{ $this->updatedAt = $updatedAt;}
    public function setCreatedAt(DateTimeImmutable $createdAt): void{ $this->createdAt = $createdAt;}


    abstract public static function mapToRow(array $row): static;
}