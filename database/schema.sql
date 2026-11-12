-- // Wallet System SQL Database Schema

-- // Database Creation
CREATE DATABASE wallet_system_db;

-- User's Table -- 
CREATE TABLE users(
    id VARCHAR(512) PRIMARY KEY NOT NULL,
    full_name VARCHAR(225) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(512) NOT NULL,
    wallet_transaction_pin_hash VARCHAR(512) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE email_verification(
    id INTEGER PRIMARY KEY,
    user_id VARCHAR(512)  NOT NULL,
    email VARCHAR(50) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    created_at DATETIME CURRENT_TIMESTAMP,
    expires_at DATETIME TIMESTAMP,
    CONSTRAINT uq_token_hash UNIQUE(token_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login Attempts table -- 
CREATE TABLE login_attempts (
    id SERIAL PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(225) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Token table --
CREATE TABLE token(
id  INTEGER PRIMARY KEY,
user_id  VARCHAR(225) NOT NULL,
email VARCHAR(225) NOT NULL,
token_hash  VARCHAR(64) NOT NULL UNIQUE,
created_at  DATETIME NOT NULL,
expires_at  DATETIME NOT NULL
);

-- Wallets Table --
CREATE TABLE wallets(
    id VARCHAR(512) PRIMARY KEY NOT NULL,
    user_id VARCHAR(512) NOT NULL,
    wallet_name VARCHAR(225) NOT NULL,
    balance DECIMAL(20, 0)  DEFAULT 0 NOT NULL,
    wallet_tag VARCHAR(512) UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wallet Transactions History Table --
CREATE TABLE wallet_transactions_history(
    id VARCHAR(512) PRIMARY KEY NOT NULL,
    wallet_id VARCHAR(512) NOT NULL,
    balance_before DECIMAL(20, 0) NOT NULL,
    amount DECIMAL(20, 0) NOT NULL,
    balance_after DECIMAL(20, 0) NOT NULL,
    transaction_type VARCHAR(50) CHECK(transaction_type IN('debit', 'credit')) NOT NULL,
    transaction_reference VARCHAR(512) UNIQUE NOT NULL,
    narration VARCHAR(225) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
);

-- Wallet Transfer's Table --
CREATE TABLE wallet_transfers(
    id VARCHAR(512) PRIMARY KEY NOT NULL,
    amount DECIMAL(20, 0) NOT NULL,
    sender_wallet_id VARCHAR(512) NOT NULL,
    beneficiary_wallet_id VARCHAR(512) NOT NULL,
    transfer_reference VARCHAR(225) UNIQUE NOT NULL,
    narration VARCHAR(200) NOT NULL,
    status VARCHAR(50)  CHECK(status IN('pending', 'successful', 'failed')) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (beneficiary_wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
);


