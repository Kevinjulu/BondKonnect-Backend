# BondKonnect - Backend API

This repository contains the backend service for **BondKonnect**, a comprehensive platform designed for bond trading, portfolio management, and real-time market data analysis. This service is extracted from the main BondKonnect project to serve as a dedicated API layer.

## Project Overview

BondKonnect is a robust financial technology solution that facilitates secure and efficient bond trading. The backend is built with Laravel 11, leveraging its powerful features to provide a RESTful API that handles complex financial logic, user authentication, and real-time data broadcasting.

### Deployment Notice
🚀 **Testing & QA:** This backend is configured for deployment on **Railway** for testing and staging purposes. The configuration includes a `Procfile` for seamless deployment on the Railway platform.

## Key Features

### 🏦 Trading & Financials
- **Order Management:** Secure execution of bond trades and order tracking.
- **Portfolio Analytics:** Real-time P&L calculations and position management.
- **Financial Integration:** Support for M-Pesa and PayPal payment gateways (Phase 1 complete).
- **Yield Calculations:** Automated yield-to-maturity and yield-to-call calculations.

### 🔐 Security & Access
- **Authentication:** Robust user authentication using Laravel Sanctum and JWT.
- **RBAC:** Role-Based Access Control to manage different user permissions (Brokers, Investors, Admins).
- **Security Headers:** Implemented middleware for secure API communication.

### 📊 Market Data
- **Real-time Updates:** WebSocket integration via Laravel Echo and Pusher for live market feeds.
- **Data Archiving:** Efficient storage and retrieval of historical market data.

### 🛠 System Architecture
- **Service Layer:** Decoupled business logic for better maintainability.
- **Activity Logging:** Comprehensive logging of user and system activities.
- **Caching:** Multi-level caching (Redis/File) to optimize performance.

## Technical Stack

- **Framework:** Laravel 11.x
- **Language:** PHP 8.2+
- **Database:** MySQL 8+ (Configured for scalability)
- **Cache & Queue:** Redis 7+
- **Real-time:** Pusher / Laravel Echo
- **Deployment:** Railway / Heroku (via Procfile)

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- MySQL 8.x
- Redis (Optional for local development, recommended for production)

### Local Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Kevinjulu/BondKonnect-Backend.git
   cd BondKonnect-Backend
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Environment Setup:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Configuration:**
   Update your `.env` file with your MySQL credentials:
   ```env
   DB_CONNECTION=bk_api_db
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=bondkonnect_db
   ```

5. **Run Migrations & Seeders:**
   ```bash
   php artisan migrate --seed
   ```

6. **Start the Server:**
   ```bash
   php artisan serve
   ```

## Testing

Run the test suite using PHPUnit:
```bash
php artisan test
```

## Contributing

This is a private project. For access or contribution inquiries, please contact the repository owner.

## License

The BondKonnect Backend is proprietary software. All rights reserved.