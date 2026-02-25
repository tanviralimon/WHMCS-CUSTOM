# Orcus Technology — orcus.one

> **One Portal, All Services** · Way to Automation

A branded client portal built on **Laravel 12** + **Inertia.js** + **Vue 3** that integrates with WHMCS for hosting, VPS, domain, and billing management. Also acts as an **OAuth 2.0 / OpenID Connect provider** for external services like Aurizor.

**Live URL:** [https://orcus.one](https://orcus.one)  
**Company:** Orcus Technology  
**Website:** [https://orcustech.com](https://orcustech.com)

---

## Table of Contents

- [Tech Stack](#tech-stack)
- [Architecture Overview](#architecture-overview)
- [Features](#features)
- [Environment Variables](#environment-variables)
- [Local Development](#local-development)
- [Project Structure](#project-structure)
- [Authentication](#authentication)
- [OAuth 2.0 / OpenID Connect Provider](#oauth-20--openid-connect-provider)
- [Payment Gateways](#payment-gateways)
- [PDF Invoice Generation](#pdf-invoice-generation)
- [Feature Flags](#feature-flags)
- [Deployment](#deployment)
- [CI/CD (GitHub Actions)](#cicd-github-actions)
- [Server Configuration](#server-configuration)
- [Database Migrations](#database-migrations)
- [Troubleshooting](#troubleshooting)

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 12, PHP 8.2+ |
| **Frontend** | Vue 3.4, Inertia.js 2.0, Tailwind CSS 3 |
| **Build** | Vite 7 |
| **Auth** | Laravel Breeze (session) + Laravel Passport 13.5 (OAuth/OIDC) + WHMCS SSO |
| **Payments** | Stripe, SSLCommerz, Bank Transfer |
| **PDF** | barryvdh/laravel-dompdf 3.1 (DejaVu Sans font) |
| **API** | WHMCS API (hosting, billing, domains, tickets) |
| **Database** | MySQL (production), SQLite (local dev) |
| **Hosting** | CloudPanel with Nginx + PHP-FPM |
| **CI/CD** | GitHub Actions → SSH auto-deploy |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                      orcus.one (Laravel 12)                 │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────────┐  │
│  │  Inertia.js  │  │   Passport   │  │  WHMCS API Client │  │
│  │  Vue 3 SPA   │  │  OAuth/OIDC  │  │  (Server Mgmt)    │  │
│  └──────────────┘  └──────────────┘  └───────────────────┘  │
│         │                  │                    │            │
│         ▼                  ▼                    ▼            │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────────┐  │
│  │  Session Auth │  │  Token Auth  │  │  dash.orcustech   │  │
│  │  (Breeze)     │  │  (Passport)  │  │  .com (WHMCS)     │  │
│  └──────────────┘  └──────────────┘  └───────────────────┘  │
└─────────────────────────────────────────────────────────────┘
         │                   │
         ▼                   ▼
    Browser Users      External Services
    (orcus.one)        (Aurizor, etc.)
```

**How it works:**
1. Users log in at orcus.one (credentials validated against WHMCS API)
2. A local user record is created/synced with `whmcs_client_id`
3. All client data (services, invoices, tickets, domains) is fetched from WHMCS API in real-time
4. External services authenticate via OAuth 2.0 through orcus.one's Passport endpoints

---

## Features

### Client Portal
- **Dashboard** — overview of services, invoices, tickets
- **VPS Management** — start, stop, restart, rebuild, SSH keys, VNC console, bandwidth monitoring, rescue mode, OS reinstall, hostname change, upgrade/downgrade
- **Domain Management** — search, register, transfer, DNS records, WHOIS, nameservers, EPP code, auto-renew, domain lock, private nameservers
- **Invoice Management** — view, pay, download PDF (Hetzner-style minimal design)
- **Payment Processing** — Stripe Checkout, SSLCommerz, bank transfer with proof upload
- **Support Tickets** — create, reply, close, file attachments
- **Account Management** — profile, password, contacts, security settings
- **Knowledge Base** — articles and categories from WHMCS
- **Billing** — transactions, credit balance, add funds, quotes
- **Orders** — product catalog, cart, checkout
- **Affiliates** — affiliate program dashboard
- **Announcements** — company news and updates
- **Downloads** — file downloads from WHMCS
- **Currency Switcher** — dynamic multi-currency support (GBP, BDT, USD)

### OAuth / OIDC Provider
- Full OAuth 2.0 Authorization Code flow
- OpenID Connect discovery endpoint
- JWKS endpoint for token verification
- UserInfo endpoint with scope-based claims
- Branded authorization consent page

### SSO Integration
- **Inbound SSO** — WHMCS → orcus.one (HMAC-signed auto-login)
- **Outbound SSO** — orcus.one → WHMCS (SSO token redirect)

---

## Environment Variables

Create a `.env` file from `.env.example` and configure:

### Required
```env
APP_NAME="Orcus Technology"
APP_ENV=production
APP_KEY=                          # php artisan key:generate
APP_URL=https://orcus.one
APP_DEBUG=false

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=orcus_one
DB_USERNAME=
DB_PASSWORD=

# WHMCS API
WHMCS_BASE_URL=https://dash.orcustech.com
WHMCS_API_IDENTIFIER=
WHMCS_API_SECRET=
WHMCS_API_TIMEOUT=10
WHMCS_VERIFY_SSL=true

# Session
SESSION_DRIVER=database
SESSION_DOMAIN=orcus.one
```

### Payment Gateways
```env
STRIPE_SECRET_KEY=sk_live_...
```

### Invoice PDF
```env
INVOICE_COMPANY_ADDRESS1=
INVOICE_COMPANY_ADDRESS2=
INVOICE_COMPANY_CITY=
INVOICE_COMPANY_STATE=
INVOICE_COMPANY_POSTCODE=
INVOICE_COMPANY_COUNTRY=
INVOICE_COMPANY_PHONE=
INVOICE_COMPANY_EMAIL=support@orcustech.com
INVOICE_COMPANY_TAX_ID=
```

### Optional (Passport OAuth Keys)
```env
# If not set, keys are loaded from storage/oauth-*.key files
PASSPORT_PRIVATE_KEY=
PASSPORT_PUBLIC_KEY=
```

---

## Local Development

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 20+
- npm
- MySQL or SQLite

### Setup
```bash
git clone git@github.com:tanviralimon/WHMCS-CUSTOM.git
cd WHMCS-CUSTOM

# Install dependencies
composer install
npm install --legacy-peer-deps

# Environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate

# OAuth keys (optional, for OAuth provider features)
php artisan passport:keys

# Build frontend
npm run build

# Start dev server
composer run dev
```

The `composer run dev` command starts concurrently:
- Laravel dev server (`php artisan serve`)
- Queue listener
- Pail log viewer
- Vite HMR dev server

---

## Project Structure

```
├── app/
│   ├── Exceptions/
│   │   └── WhmcsApiException.php       # WHMCS API error handler
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                   # Breeze auth + WHMCS SSO
│   │   │   ├── Client/                 # All client portal controllers
│   │   │   │   ├── ServiceController   # VPS management (18+ actions)
│   │   │   │   ├── InvoiceController   # Invoice + PDF download
│   │   │   │   ├── PaymentController   # Stripe/SSLCommerz/Bank
│   │   │   │   ├── DomainController    # Domain management
│   │   │   │   ├── TicketController    # Support tickets
│   │   │   │   └── ...                 # Billing, Account, Orders, etc.
│   │   │   └── OidcController.php      # OAuth/OIDC provider endpoints
│   │   ├── Middleware/
│   │   │   ├── HandleInertiaRequests   # Shares auth, flash, currencies
│   │   │   ├── EnsureWhmcsOwnership    # Resource ownership verification
│   │   │   └── EnsureFeatureEnabled    # Feature flag gating
│   │   └── Responses/
│   │       └── AuthorizationViewResponse  # Passport consent page binding
│   ├── Models/
│   │   └── User.php                    # HasApiTokens, whmcs_client_id
│   ├── Providers/
│   │   └── AppServiceProvider.php      # Passport scopes, token expiry
│   └── Services/
│       └── Whmcs/                      # WHMCS API client & service layer
├── config/
│   ├── features.php                    # Feature flags (domains, sso, etc.)
│   ├── invoice.php                     # PDF invoice company details
│   ├── passport.php                    # OAuth provider config
│   ├── payment.php                     # Payment gateway settings
│   └── whmcs.php                       # WHMCS API connection config
├── database/migrations/                # Users, cache, jobs, Passport tables
├── resources/
│   ├── js/
│   │   ├── Components/                 # 22 shared Vue components
│   │   ├── Composables/useCurrency.js  # Currency formatting helper
│   │   ├── Layouts/                    # App, Auth, Guest layouts
│   │   └── Pages/
│   │       ├── Auth/                   # Login, Register, Forgot Password
│   │       └── Client/                 # All client portal pages (Vue SPA)
│   └── views/
│       ├── pdf/invoice.blade.php       # Hetzner-style PDF invoice template
│       └── vendor/passport/authorize   # OAuth consent page (branded)
├── routes/
│   ├── web.php                         # Public routes, SSO, OIDC discovery
│   ├── client.php                      # All client portal routes (auth req)
│   ├── api.php                         # OAuth/OIDC API endpoints
│   └── auth.php                        # Breeze auth routes
├── .github/workflows/deploy.yml        # CI/CD auto-deploy on push to main
└── deploy.sh                           # Manual deployment script
```

---

## Authentication

### Session Auth (Breeze)
- Users log in with email + password
- Credentials validated against WHMCS `ValidateLogin` API
- Local `users` table stores: `name`, `email`, `password` (hashed), `whmcs_client_id`
- Standard Laravel session cookies

### WHMCS SSO
- **Inbound:** WHMCS admin can send users to orcus.one via HMAC-signed URL
- **Outbound:** Users can jump from orcus.one to WHMCS panel via SSO token

### Resource Ownership
The `EnsureWhmcsOwnership` middleware verifies that the authenticated user's `whmcs_client_id` matches the owner of any WHMCS resource (service, invoice, ticket, domain) being accessed.

---

## OAuth 2.0 / OpenID Connect Provider

orcus.one acts as a full OAuth 2.0 / OpenID Connect provider using **Laravel Passport 13.5**. External services authenticate through orcus.one's branded login instead of raw WHMCS.

### Endpoints

| Endpoint | URL |
|----------|-----|
| **OIDC Discovery** | `https://orcus.one/.well-known/openid-configuration` |
| **Authorization** | `https://orcus.one/oauth/authorize` |
| **Token** | `https://orcus.one/oauth/token` |
| **UserInfo** | `https://orcus.one/api/userinfo` |
| **JWKS** | `https://orcus.one/oauth/jwks` |

### Scopes

| Scope | Description |
|-------|-------------|
| `openid` | Authenticate identity (returns `sub`, `name`, `email`) |
| `profile` | Access name and profile information |
| `email` | Access email address and verification status |

### Token Expiry

| Token Type | Lifetime |
|-----------|----------|
| Access Token | 15 days |
| Refresh Token | 30 days |
| Personal Access Token | 6 months |

### Creating an OAuth Client

```bash
php artisan passport:client \
  --name='Service Name' \
  --redirect_uri='https://example.com/auth/callback'
```

> ⚠️ Save the Client ID and Client Secret immediately — the secret is shown only once.

### Authorization Code Flow

1. External service redirects user to:
   ```
   https://orcus.one/oauth/authorize?
     client_id=CLIENT_ID&
     redirect_uri=CALLBACK_URL&
     response_type=code&
     scope=openid+profile+email&
     state=RANDOM_STATE
   ```
2. User sees branded orcus.one login page
3. After login, user sees branded consent screen (Authorize / Cancel)
4. On approval, redirects back with `?code=AUTH_CODE&state=STATE`
5. Service exchanges code for tokens:
   ```
   POST https://orcus.one/oauth/token
   Content-Type: application/x-www-form-urlencoded

   grant_type=authorization_code&
   client_id=CLIENT_ID&
   client_secret=CLIENT_SECRET&
   redirect_uri=CALLBACK_URL&
   code=AUTH_CODE
   ```
6. Service calls UserInfo with the access token:
   ```
   GET https://orcus.one/api/userinfo
   Authorization: Bearer ACCESS_TOKEN
   ```
   Response:
   ```json
   {
     "sub": "1",
     "name": "John Doe",
     "email": "john@example.com",
     "email_verified": true
   }
   ```

### Registered OAuth Clients

| Service | Callback URL |
|---------|-------------|
| **Aurizor** | `https://api.aurizor.com/auth/orcus/callback` |

---

## Payment Gateways

| Gateway | Type | Notes |
|---------|------|-------|
| **Stripe** | Credit/Debit Card | Stripe Checkout Sessions |
| **SSLCommerz** | Bangladesh Payments | Cross-site POST callback (session excluded from CSRF) |
| **Bank Transfer** | Manual | Upload payment proof (receipt image) |
| **Account Credit** | Internal | Apply/remove credit to invoices |

Payment proof uploads support: `jpg`, `jpeg`, `png`, `gif`, `pdf` (max 5MB by default, configurable via `TICKET_MAX_FILE_SIZE_MB`).

---

## PDF Invoice Generation

- **Library:** barryvdh/laravel-dompdf
- **Font:** DejaVu Sans (Unicode support)
- **Design:** Hetzner-inspired minimal layout — 9px base font, thin gray borders, clean typography
- **Currency:** Code-based suffix format (e.g., `1,000.00 BDT`) to avoid font rendering issues with currency symbols like ৳
- **Template:** `resources/views/pdf/invoice.blade.php`

Currency is resolved per-client:
1. Fetch client's `currency_id` via WHMCS `GetClientsDetails`
2. Match against `GetCurrencies` API response
3. Use ISO currency code as suffix (e.g., `GBP`, `BDT`, `USD`)

---

## Feature Flags

Defined in `config/features.php`. Routes gated by the `feature` middleware are hidden when disabled.

| Feature | Default | Description |
|---------|---------|-------------|
| `domains` | ✅ | Domain management |
| `knowledgebase` | ✅ | Knowledge base articles |
| `announcements` | ✅ | Company announcements |
| `downloads` | ✅ | File downloads |
| `affiliates` | ❌ | Affiliate program |
| `sso` | ✅ | SSO to WHMCS |
| `quotes` | ✅ | Quote management |
| `addons` | ✅ | Addon services |
| `orders` | ✅ | Product ordering |

---

## Deployment

### Production Server

| Detail | Value |
|--------|-------|
| **IP** | `95.217.142.144` |
| **Panel** | CloudPanel |
| **Deploy Path** | `/home/jltusu/htdocs/orcus.one` (symlink → `/home/tusu/htdocs/orcus.one`) |
| **PHP-FPM User** | `tusu:tusu` (port 19001) |
| **SSH User** | `jltusu` |
| **PHP Version** | 8.5 |
| **GitHub Deploy Key** | `/home/jltusu/.ssh/github_deploy` |

### Manual Deploy

```bash
ssh root@95.217.142.144

cd /home/jltusu/htdocs/orcus.one

# Pull latest
GIT_SSH_COMMAND='ssh -i /home/jltusu/.ssh/github_deploy -o StrictHostKeyChecking=no' \
  git pull origin main

# Dependencies
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

# Migrate
php artisan migrate --force

# Caches
php artisan config:clear && php artisan view:clear && php artisan route:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Permissions
chown -R jltusu:tusu /home/jltusu/htdocs/orcus.one

# Restart PHP
systemctl restart php8.5-fpm
```

### First-Time Server Setup

```bash
# 1. Clone
GIT_SSH_COMMAND='ssh -i /home/jltusu/.ssh/github_deploy -o StrictHostKeyChecking=no' \
  git clone git@github.com:tanviralimon/WHMCS-CUSTOM.git /home/jltusu/htdocs/orcus.one

cd /home/jltusu/htdocs/orcus.one

# 2. Dependencies
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

# 3. Environment
cp .env.example .env
# Edit .env with: DB creds, WHMCS API keys, APP_KEY, APP_URL, etc.
php artisan key:generate

# 4. Database
php artisan migrate --force

# 5. OAuth keys
php artisan passport:keys
chmod 640 storage/oauth-private.key
chmod 644 storage/oauth-public.key

# 6. Create OAuth clients
php artisan passport:client --name='Aurizor' \
  --redirect_uri='https://api.aurizor.com/auth/orcus/callback'

# 7. Build caches
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 8. Permissions
chown -R jltusu:tusu /home/jltusu/htdocs/orcus.one
```

---

## CI/CD (GitHub Actions)

**File:** `.github/workflows/deploy.yml`  
**Trigger:** Push to `main` branch

### Pipeline

1. **Build** (ubuntu-latest)
   - PHP 8.4 + Composer
   - `composer install --no-dev`
   - Node.js 20 + npm
   - `npm install --legacy-peer-deps && npm run build`
   - Upload `public/build` artifact

2. **Deploy** (ubuntu-latest)
   - SSH into production via deploy key
   - `git pull origin main`
   - `composer install --no-dev --optimize-autoloader`
   - `php artisan migrate --force`
   - Rebuild caches (config, route, view, event)
   - SCP built frontend assets
   - Fix permissions + restart PHP-FPM

### Required GitHub Secrets

| Secret | Description |
|--------|-------------|
| `SSH_PRIVATE_KEY` | ED25519 private key for SSH |
| `SERVER_HOST` | `95.217.142.144` |

---

## Server Configuration

### Nginx

CloudPanel reverse proxy setup:
- Port **443** (HTTPS) → proxy to **8080** (internal)
- Port **8080** → PHP-FPM on **19001**

**⚠️ Important:** Add this Nginx block for OIDC discovery (server-specific, not in codebase):

```nginx
# Default .well-known (ACME, etc.)
location ~ /.well-known {
    auth_basic off;
    allow all;
}

# OIDC discovery — MUST proxy to Laravel
location = /.well-known/openid-configuration {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-Host $http_host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
```

After editing: `nginx -t && systemctl reload nginx`

### PHP-FPM Pool

```ini
user = tusu
group = tusu
listen = 127.0.0.1:19001
```

---

## Database Migrations

| Migration | Tables |
|-----------|--------|
| `create_users_table` | `users`, `password_reset_tokens`, `sessions` |
| `create_cache_table` | `cache`, `cache_locks` |
| `create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |
| `add_whmcs_client_id` | Adds `whmcs_client_id` to `users` |
| `create_oauth_auth_codes_table` | `oauth_auth_codes` (Passport) |
| `create_oauth_access_tokens_table` | `oauth_access_tokens` (Passport) |
| `create_oauth_refresh_tokens_table` | `oauth_refresh_tokens` (Passport) |
| `create_oauth_clients_table` | `oauth_clients` (Passport) |
| `create_oauth_device_codes_table` | `oauth_device_codes` (Passport) |

---

## Troubleshooting

### "Key path does not exist or is not readable"
OAuth keys need correct permissions for the PHP-FPM user:
```bash
chmod 640 storage/oauth-private.key
chmod 644 storage/oauth-public.key
chown jltusu:tusu storage/oauth-*.key
```

### `.well-known/openid-configuration` returns 404
Nginx needs the OIDC proxy location block. See [Server Configuration](#server-configuration).

### PDF currency shows □ boxes
DejaVu Sans doesn't support all currency symbols (e.g., ৳). The app uses ISO currency code suffixes instead (`1,000.00 BDT`).

### CSRF Token Mismatch on payment callbacks
Payment callbacks are excluded from CSRF in `bootstrap/app.php`:
```php
$middleware->validateCsrfTokens(except: [
    'client/payment/*/callback/*',
]);
```

### Git pull fails on server
Use the deploy key:
```bash
GIT_SSH_COMMAND='ssh -i /home/jltusu/.ssh/github_deploy -o StrictHostKeyChecking=no' \
  git pull origin main
```

### Route cache errors
Clear and rebuild:
```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### "AuthorizationViewResponse is not instantiable"
The custom `AuthorizationViewResponse` must be bound in `AppServiceProvider::register()`. This is already done — if the error appears, clear the config cache.

---

## License

Private — Orcus Technology. All rights reserved.
