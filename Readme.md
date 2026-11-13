# Thorfinn Digital Wallet

>
>
> A secure, multi-wallet digital financial management web application built with PHP and SQLite. Thorfinn Digital Wallet enables individuals to organize personal finances into segregated wallets, track real-time balances, fund accounts, and execute instant internal and peer-to-peer transfers protected by 2FA and transaction PINs.

---

## Table of Contents

1. [Project Purpose](#1-project-purpose)
2. [Core Features](#2-core-features)
3. [System Requirements](#3-system-requirements)
4. [Installation & Database Setup](#4-installation--database-setup)
5. [Environment Configuration](#5-environment-configuration)
6. [Running the Application](#6-running-the-application)
7. [Running Tests](#7-running-tests)
8. [Project Structure](#8-project-structure)
9. [Troubleshooting Guide](#9-troubleshooting-guide)
10. [Updating & Contributing](#10-updating--contributing)
11. [License & Contact](#11-license--contact)

---

## 1. Project Purpose

**Thorfinn Wallet** is built for individuals and developers who need a clean, reliable system to separate personal funds into dedicated sub-wallets (e.g., *Savings*, *Travel*, *Daily Expenses*) and move money instantaneously.

Designed with a decoupled PHP service-repository backend, an ACID-compliant SQLite ledger, and a responsive vanilla HTML/CSS/JS frontend, Thorfinn Wallet enforces strict financial integrity—including double-entry transaction audit trails, PIN-authorized transfers, CSRF protection, and two-factor email authentication.

---

## 2. Core Features

### Login, Onboarding & Account Security
* **Account Creation:** Register with full name, email, strong password, and a custom 6-digit numeric Wallet Transaction PIN.
* **Email Verification:** New accounts receive a secure, time-limited verification link prior to full activation.
* **2FA Login Flow:** Every login requires a 6-character alphanumeric one-time token sent to the user's verified email, followed by an automated login IP alert.
* **Brute-Force Protection:** Rate-limits failed login attempts and token requests after 3 attempts within a 5-minute window.
* **Password Management:** Supports self-service password recovery as well as authenticated in-app password updates verified via email token.

### Multi-Wallet Management
* **Create Wallets:** Create up to **5 segregated wallets** per user, each assigned a unique Wallet Tag (`@tag`) for easy peer-to-peer transfers.
* **Dashboard Overview:** Displays total net balance across all wallets alongside individual wallet cards.
* **Wallet Inspection:** View detailed wallet statistics and the **last 10 transactions** filtered globally or per wallet.
* **Edit Wallet Details:** Rename existing wallets at any time with email confirmation.
* **Safe Wallet Deletion:** Protected by PIN authorization and strict database rules—a wallet can only be deleted if its balance is `0` **and** it has `0` recorded transactions.

### Funding & Transfers
* **Wallet Funding:** Add funds to any owned wallet from an internal wallet or an external source.
* **Internal Transfers:** Move money instantaneously between your own wallets.
* **External (P2P) Transfers:** Send money to another user's wallet using their unique Wallet Tag.
* **Atomic Ledger & Alerts:** Every transfer runs inside a database transaction (`BEGIN` / `COMMIT` / `ROLLBACK`), logs `balance_before` and `balance_after` entries, and sends automated debit/credit email receipts to both parties.

---

## 3. System Requirements

Ensure the following tools and PHP extensions are installed on your machine before setup:

| Component | Minimum Version | Notes |
| :--- | :--- | :--- |
| **PHP** | `8.1+` | CLI and web server SAPI |
| **PHP Extensions** | `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `json` | Verify via `php -m` |
| **SQLite** | `3.35+` | File-based relational database |
| **Composer** | `2.0+` | Dependency & autoloader management |
| **SMTP Server** | Any | Required for 2FA, verification, and transaction alerts (e.g., Mailtrap, SendGrid, Gmail SMTP) |

---

## 4. Installation & Database Setup

Follow these copy-pasteable steps to set up a local instance:

### Step 1: Clone the Repository
```bash
git clone https://github.com/Dee-Traid/Thorfinn-Digital-Wallet.git
cd Thorfinn-Digital-Wallet
```

### Step 2: Install PHP Dependencies
```bash
composer install
```

### Step 3: Configure Environment Variables
```bash
cp .env.example .env
```
Open `.env` and fill in your local URL, a strong `JWT_KEY`, and your SMTP credentials (see [Environment Configuration](#5-environment-configuration)).

### Step 4: Initialize the SQLite Database & Run Migrations
Thorfinn Wallet uses **SQLite** configured via Phinx (`phinx.php`) pointing to `database/wallet.db`.

1. **Create the `database` directory and empty SQLite database file:**
   ```bash
   mkdir -p database
   touch database/wallet.db
   ```

2. **Run Phinx migrations to create all required tables:**
   ```bash
   vendor/bin/phinx migrate -e development
   ```

3. **Verify the database tables were created:**
   ```bash
   sqlite3 database/wallet.db ".tables"
   ```
   You should see `users`, `wallets`, `wallet_transfers`, `wallet_transactions_history`, `email_verification`, `token`, `login_attempts`, and `token_requests`.

*(Alternative Manual Setup)*: If you are setting up the database manually without Phinx, you can apply the SQLite-compatible table definitions from `schema.sql` directly to `database/wallet.db`.

---

## 5. Environment Configuration

All sensitive credentials are loaded from `.env` at runtime using `vlucas/phpdotenv`. **Never commit your `.env` file or `database/*.db` files to version control.**

### `.env.example`
```env
# Application Settings
APP_URI=http://localhost:8000
JWT_KEY=replace_with_a_long_random_secret_string

# Outgoing SMTP Mail Configuration
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=465
MAIL_USER=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_FROM_NAME="Thorfinn Digital Wallet"
```

| Variable | Description |
| :--- | :--- |
| `APP_URI` | Base URL where the application is hosted (used to generate email verification links). |
| `JWT_KEY` | Secret cryptographic key used to sign session JWT tokens (`HS256`). |
| `MAIL_HOST` | Hostname of your SMTP mail server. |
| `MAIL_PORT` | SMTP port (defaults to `465` for SMTPS encryption). |
| `MAIL_USER` | SMTP authentication username / sender email address. |
| `MAIL_PASSWORD` | SMTP authentication password or app-specific password. |
| `MAIL_FROM_NAME` | Display name shown on outgoing system emails. |

### Generating a Secure `JWT_KEY`
Run this command in your terminal to generate a cryptographically secure secret key:
```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```
Paste the generated string as your `JWT_KEY` in `.env`.

---

## 6. Running the Application

1. **Start PHP's built-in development server** from the project root:
   ```bash
   php -S localhost:8000
   ```

2. **Open the application in your browser:**
   * **Landing Page:** `http://localhost:8000/index.php`
   * **Login / Sign Up:** `http://localhost:8000/auth.html`
   * **User Dashboard:** `http://localhost:8000/dashboard.html`

3. **Quick Walkthrough:**
   * Navigate to **Create account**, enter your details, and set a 6-digit Wallet Transaction PIN.
   * Check your configured SMTP inbox and click the email verification link.
   * Sign in, enter the 6-character 2FA login token sent to your email, and begin creating, funding, and transferring between wallets.

---

## 7. Running Tests

This project uses **PHPUnit (`^10.5`)** for automated unit and integration testing.

1. Ensure development dependencies are installed (`composer install`).
2. Create a separate test SQLite database so test runs do not interfere with development data:
   ```bash
   touch database/wallet_test.db
   ```
3. Execute the test suite:
   ```bash
   vendor/bin/phpunit --testdox
   ```

---

## 8. Project Structure

The codebase follows a clean layered architecture separating frontend views, business services, and SQLite repositories:

```text
thorfinn-wallet/
├── database/
│   ├── migrations/                  # Phinx versioned schema migrations
│   └── wallet.db                    # Local SQLite database file (git-ignored)
├── images/
│   └── logo.png                     # Application favicon and brand assets
├── public/
│   ├── css/
│   │   ├── styles.css               # Global theme variables & base layout
│   │   ├── auth.css                 # Authentication view styles
│   │   └── dashboard.css            # Dashboard grid, cards & modal styles
│   └── js/
│       ├── auth.js                  # Login, signup, 2FA & password reset handlers
│       └── dashboard.js             # Wallet CRUD, transfers & PIN modal logic
├── src/
│   ├── Config/
│   │   └── Database.php             # Singleton PDO SQLite connection setup
│   ├── Models/
│   │   ├── User.php                 # User entity & row mapper
│   │   ├── Wallet.php               # Wallet entity & row mapper
│   │   └── WalletTransfer.php       # Transfer entity & status model
│   ├── Repository/
│   │   ├── UserRepository.php       # User, token & rate-limit SQL queries
│   │   ├── WalletRepository.php     # Wallet CRUD, atomic debit/credit & history
│   │   └── WalletTransferRepository.php # Transfer & ledger audit persistence
│   ├── Service/
│   │   ├── MailService.php          # PHPMailer SMTP templates & alerts
│   │   ├── UserService.php          # Registration, 2FA auth, JWT & password flows
│   │   └── WalletService.php        # Business rules, PIN checks & transfers
│   └── Utility/
│       ├── CSRF.php                 # Session CSRF token generation & validation
│       └── UtilityHelper.php        # Password/PIN hashing & JSON response helper
├── tests/                           # PHPUnit automated test suite
├── auth.html                        # Login, registration, and password reset UI
├── composer.json                    # Dependencies & PSR-4 ("src\\": "src/") mapping
├── dashboard.html           # Main wallet management dashboard UI
├── index.php                        # Public landing page & CSRF bootstrap
├── phinx.php                        # Phinx migration configuration
└── schema.sql                       # Reference SQL database schema
```

---

## 9. Troubleshooting Guide

| Issue / Error Message | Likely Cause | Recommended Fix |
| :--- | :--- | :--- |
| **`PDOException: could not find driver`** | The PHP SQLite PDO driver is disabled or missing. | Install `php-sqlite3` (e.g., `sudo apt install php-sqlite3`) and enable `extension=pdo_sqlite` in `php.ini`. |
| **`attempt to write a readonly database`** | PHP lacks write permissions on `database/wallet.db` or the `database/` directory. | SQLite requires write access to **both** the file and its parent folder: `chmod -R 775 database/`. |
| **`Mail error` / Verification emails not arriving** | Invalid SMTP credentials or port `465` blocked by firewall/ISP. | Verify `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, and `MAIL_PASSWORD` in `.env`, and check PHP `error_log` for PHPMailer diagnostics. |
| **`Users' wallet creation limit of 5 wallets has been reached`** | Each user account is capped at 5 active wallets. | Rename an existing wallet or delete an unused wallet with `0` balance and `0` transactions. |
| **`Cannot delete wallet` error** | Wallets with a positive balance or past transaction history cannot be deleted. | Transfer remaining funds out first. Wallets with historical ledger records are locked from deletion to preserve audit integrity. |
| **`Invalid or expired token`** | Security tokens expire within 2–5 minutes. | Click **Resend token** or restart the login/reset flow and enter the new code promptly. |

---

## 10. Updating & Contributing

1. **Pull the latest changes:**
   ```bash
   git checkout main
   git pull origin main
   composer install
   vendor/bin/phinx migrate -e development
   ```

2. **Create a feature branch:**
   ```bash
   git checkout -b feat/your-feature-name
   ```

3. **Make changes & run checks:**
   * Follow PSR-4 namespace conventions under `src/`.
   * Never hardcode secrets, tokens, or personal data in code or tests.
   * Run `vendor/bin/phpunit` to ensure all tests pass.

4. **Submit a Pull Request:**
   ```bash
   git add .
   git commit -m "Add: brief description of changes"
   git push origin feat/your-feature-name
   ```
   Open a Pull Request against `main` with a clear summary of your changes.

---

## 11. License & Contact

* **License:** Distributed under the **MIT License**. See the `LICENSE` file for details.
* **Support & Issues:** Found a bug or have a question? Please open an issue on the repository's [GitHub Issues](https://github.com/your-username/thorfinn-wallet/issues) page.