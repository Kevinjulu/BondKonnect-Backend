# BondKonnect Database Credentials (Mock Data)

This document contains the credentials for the mock users seeded into the BondKonnect database for testing and development purposes.

## Primary Test Admin User
- **Email:** `admin@bondkonnect.test`
- **Password:** `password123`
- **Mock OTP:** `123456`
- **Roles:** `Admin`, `Broker`
- **Phone Number:** (Randomly generated, e.g., `07XXXXXXXX`)

## Other Mock Users
All other mock users use the password `password`.

| Name | Email | Company | Role |
|------|-------|---------|------|
| John Mwangi | `john.mwangi@example.com` | Nairobi Capital | Broker |
| Mary Wanjiru | `mary.wanjiru@example.com` | Kenya Power | Issuer |
| James Omondi | `james.omondi@example.com` | N/A | Individual |
| David Kimani | `david.kimani@example.com` | Safaricom PLC | Issuer |
| Grace Akinyi | `grace.akinyi@example.com` | Equity Bank | Broker |
| Peter Njoroge | `peter.njoroge@example.com` | N/A | Individual |

## OTP Verification Logic
For testing, an OTP record has been pre-seeded for the `admin@bondkonnect.test` user.
- **OTP:** `123456`
- **Expiry:** Set to 1 year from the seeding date.
- **Usage:** This OTP can be used directly in the verification screen after a successful login.

## How to Seed on Railway
To seed these users into your Railway database, you can use one of the following methods:

### Method 1: Railway CLI
Run the following command from your terminal:
```bash
railway run php artisan db:seed
```

### Method 2: Deployment Variable
Set the following environment variable in your Railway project:
- `RUN_SEEDER`: `true`

The next time your application deploys, the `docker/entrypoint.sh` script will automatically run the migrations and seeders.
