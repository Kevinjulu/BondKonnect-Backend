# BondKonnect API

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About BondKonnect API

The BondKonnect API is a robust backend service built with Laravel that powers the BondKonnect platform. It provides secure and efficient endpoints for bond trading, portfolio management, and market data analysis. The API follows RESTful principles and implements industry-standard security practices.

## Key Features

### Trading Operations
- Real-time bond trading execution
- Order management system
- Trade settlement processing
- Position tracking and management
- Trade history and audit logs

### Market Data
- Real-time bond pricing
- Historical price data
- Market depth information
- Yield curve calculations
- Market indicators and analytics

### Portfolio Management
- Portfolio tracking and analytics
- P&L calculations (Realized and Unrealized)
- Performance attribution
- Risk metrics calculation
- Portfolio rebalancing tools

### User Management
- Role-based access control
- Multi-factor authentication
- Session management
- User activity logging
- API key management

### System Features
- WebSocket support for real-time updates
- Rate limiting and request throttling
- Comprehensive API documentation
- Data validation and sanitization
- Error handling and logging
- Caching and performance optimization

## Technical Stack

### Core Technologies
- Laravel Framework 11.x
- PHP 8.2+
- MySQL 8.0+
- Redis 7+

### Authentication & Security
- Laravel Sanctum for API tokens
- CSRF protection
- Input validation and sanitization

### Performance & Monitoring
- Laravel Horizon for queue management
- Redis for caching and queues

### Development Tools
- PHPUnit for testing
- Laravel Pint for code styling
- Composer for dependency management
- NPM for frontend assets

## Project Structure

```
bondkonnect_api/
├── app/
│   ├── Console/           # Artisan commands
│   ├── Exceptions/        # Custom exceptions
│   ├── Http/
│   │   ├── Controllers/   # API controllers
│   │   │   ├── Auth/     # Authentication controllers
│   │   │   ├── Trade/    # Trading controllers
│   │   │   ├── Market/   # Market data controllers
│   │   │   └── Portfolio/# Portfolio controllers
│   │   ├── Middleware/   # Custom middleware
│   │   └── Requests/     # Form requests
│   ├── Models/           # Eloquent models
│   ├── Services/         # Business logic services
│   ├── Events/           # Event classes
│   ├── Listeners/        # Event listeners
│   ├── Jobs/             # Queue jobs
│   └── Providers/        # Service providers
├── config/               # Configuration files
├── database/
│   ├── factories/        # Model factories
│   ├── migrations/       # Database migrations
│   └── seeders/         # Database seeders
├── routes/
│   ├── api.php          # API routes
│   ├── channels.php     # Broadcasting channels
│   ├── console.php      # Console commands
│   └── web.php          # Web routes
├── storage/
│   ├── app/             # Application files
│   ├── framework/       # Framework files
│   └── logs/            # Application logs
├── tests/               # Test files
├── resources/           # Frontend resources
└── public/              # Public files
```

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- MySQL 8.0+
- Redis 7+
- Node.js 18+ and NPM
- Git

### Installation

1. Clone the repository:
```bash
git clone https://github.com/your-username/bondkonnect.git
cd bondkonnect/bondkonnect_api
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install NPM dependencies:
```bash
npm install
```

4. Create environment file:
```bash
cp .env.example .env
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Configure your database in `.env`:
```env
DB_CONNECTION=bk_api_db
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bk_api_db
DB_USERNAME=your_username
DB_PASSWORD=your_password

# BK Legacy Database
BK_HOST=127.0.0.1
BK_PORT=3306
BK_DATABASE=bk_db
BK_USERNAME=your_username
BK_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

7. Run migrations:
```bash
php artisan migrate
```

8. Seed the database:
```bash
php artisan db:seed
```

### Development

1. Start the development server:
```bash
php artisan serve
```

2. Start the queue worker:
```bash
php artisan queue:work
```

3. Start the WebSocket server:
```bash
php artisan websockets:serve
```

4. Compile assets:
```bash
npm run dev
```

## API Documentation

The API documentation is available at `/api/documentation` when running the application. It includes:

### Authentication
- JWT token generation
- Token refresh
- Password reset
- MFA setup

### Endpoints
- Trading endpoints
- Market data endpoints
- Portfolio endpoints
- User management endpoints

### Request/Response Examples
- Sample requests
- Response formats
- Error codes
- Rate limiting

## Security

### Authentication
- JWT-based authentication
- OAuth2 implementation
- MFA support
- Session management

### Data Protection
- CSRF protection
- Input validation
- SQL injection prevention
- XSS protection
- Rate limiting
- Secure password hashing

### Monitoring
- Security logging
- Audit trails
- Suspicious activity detection
- Regular security updates

## Testing

### Unit Tests
```bash
php artisan test --filter=Unit
```

### Feature Tests
```bash
php artisan test --filter=Feature
```

### Integration Tests
```bash
php artisan test --filter=Integration
```

### Browser Tests
```bash
php artisan dusk
```

### Test Coverage
```bash
php artisan test --coverage
```

## Deployment

### Production Setup
1. Set up production environment variables
2. Run composer install with --no-dev flag
3. Optimize the application:
```bash
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

4. Set up queue workers:
```bash
php artisan queue:work --daemon
```

5. Set up scheduled tasks:
```bash
php artisan schedule:run
```

### Deployment Platforms
- AWS Elastic Beanstalk
- DigitalOcean App Platform
- Laravel Forge
- Docker

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, please open an issue in the GitHub repository or contact the development team.
