# BondKonnect Backend: The Financial Engine

The `bondkonnect_api` directory houses the core backend for the BondKonnect platform. Built with **Laravel 11**, it acts as a robust, secure, and highly scalable financial engine for bond trading, analytics, and payment processing.

## 🏛 Architecture & Tech Stack

BondKonnect's backend follows a **Service-Oriented Architecture** (SOA), decoupling business logic from controllers to ensure maintainability and testability.

- **Framework:** [Laravel 11.x](https://laravel.com/) (latest stable)
- **Language:** PHP 8.2+
- **Database:** [Railway PostgreSQL](https://railway.app/) (Managed PostgreSQL for high availability and performance)
- **Real-time Engine:** [Pusher](https://pusher.com/) for WebSocket broadcasting (Yield curves, live quotes).
- **Caching & Queues:** Railway Redis 7+ for low-latency session management and background job processing.
- **AI Integration:** [AiService](./app/Services/AiService.php) for market analysis and predictive yield calculations.

---

## 💎 Specialized Financial Services

At the heart of BondKonnect are custom services designed for the Kenyan and international bond markets:

### 🇰🇪 M-Pesa Integration (`MpesaService`)
- **STK Push (Lipa na M-Pesa):** Instant mobile payment initiation for retail bond purchases.
- **C2B & B2C:** Automated reconciliation of customer payments and disbursement of bond proceeds.
- **Real-time Validation:** Instant callback processing for high-volume transactions.

### 🌐 PayPal Integration (`PaypalService`)
- Secure international payment gateway for diaspora and institutional investors.
- Multi-currency support for global bond access.

### 📈 Rating & Credibility (`RatingService`, `CredibilityScoreService`)
- **User Credibility Scoring:** A dynamic reputation system based on trade history and payment reliability.
- **Bond Ratings:** Integration of historical and market data to provide risk indicators for Kenyan corporate and government bonds.
- **Dispute Management:** Automated handling of rating disputes through a transparent review process.

### 🤖 AI Concierge & Knowledge Base (`AiService`)
- **RAG Architecture:** Retrieval-Augmented Generation for site-specific knowledge.
- **Vector Search:** `pgvector` integration for high-performance similarity searching of bond market data and platform documentation.
- **Ground Truth Knowledge:** A curated knowledge base (IFB/FXD rules, M-Pesa workflows) ensures the AI only answers based on verified platform information.
- **Strict Guardrails:** Professional "Terminal" persona focused exclusively on BondKonnect and the Kenyan bond market.

---

## 🔐 Advanced Security Features

BondKonnect goes beyond standard authentication to protect sensitive financial data:

- **Multi-Session Management:** Users can view active browser and device sessions in real-time and revoke specific logins to prevent unauthorized access.
- **Granular RBAC:** A deep permission system managing access for Individuals, Agents, Brokers, Dealers, and Corporates.
- **Sanctum & JWT:** Secure, token-based API authentication.
- **Audit Logs:** Every financial transaction and sensitive system change is logged with a detailed audit trail.

---

## 🚀 Getting Started

### 1. Prerequisites
- PHP 8.2+ & Composer
- MySQL 8+ or PostgreSQL (Railway recommended)
- Redis

### 2. Installation
```bash
composer install
```

### 3. Environment Configuration
Copy `.env.example` to `.env` and configure your credentials:
```env
DB_CONNECTION=pgsql # Use pgsql for Railway PostgreSQL
DB_URL="your-railway-connection-string"

MPESA_CONSUMER_KEY=your-key
MPESA_CONSUMER_SECRET=your-secret
MPESA_SHORTCODE=174379

PUSHER_APP_ID=your-id
PUSHER_APP_KEY=your-key
```

### 4. Database & Seeding
```bash
php artisan key:generate
php artisan migrate --seed # Seeds essential bond data and system roles
```

### 5. Start the Engine
```bash
php artisan serve
```

---

## 🧪 Testing & Validation

We maintain high reliability through extensive automated testing:
- **Unit & Feature:** [PHPUnit](https://phpunit.de/) for validating financial services and API endpoints.
- **Test Coverage:** Critical services like `MpesaService` and `RatingService` are heavily tested.

```bash
# Run the test suite
php artisan test
```

## 📄 License
Proprietary software. Part of the BondKonnect Ecosystem.
