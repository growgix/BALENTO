# BALENTO — Accessible-Premium Handbag E-Commerce REST API & Storefront

> **Production-grade PHP 8.2+ / MySQL 8.0+ Backend API and Editorial Frontend for BALENTO Handbags (India).**

---

## 1. Architectural Blueprint

BALENTO is engineered using clean, framework-independent, object-oriented design patterns adhering strictly to PSR-4 autoloading, PSR-12 coding styles, and strict types (`declare(strict_types=1);`).

```
[ HTTP Client (Browser / Mobile / Fetch API) ]
                     │
                     ▼
             [ public/index.php ] ──────── (Front Controller Bootstrap)
                     │
                     ▼
          [ App\Core\Router ]
                     │
         ┌───────────┴───────────┐
         ▼                       ▼
 [ Global Middlewares ]     [ Route Middlewares ]
  ├─ CorsMiddleware          ├─ RateLimitMiddleware
  └─ JsonBodyMiddleware      └─ AuthMiddleware (RBAC)
                                 │
                                 ▼
                     [ App\Controllers\* ]
                                 │
                                 ▼
                      [ App\Services\* ] ──── (Authoritative Business Logic & Pricing Engine)
                                 │
                                 ▼
                    [ App\Repositories\* ] ── (Pessimistic Concurrency Locking: SELECT FOR UPDATE)
                                 │
                                 ▼
                    [ App\Core\Database ] ─── (PDO Wrapper / ACID Transactions)
                                 │
                                 ▼
                     [ MySQL 8.0 Database ]
```

---

## 2. Directory Structure

```
├── .env.example                  # Environment configuration template
├── composer.json                 # PSR-4 Autoloading specification
├── config/                       # Application configuration registry
│   ├── app.php                   # Timezone, environment, debug settings
│   ├── auth.php                  # JWT TTL, session names, hashing settings
│   ├── cors.php                  # Allowed origins, headers, preflight cache
│   └── database.php              # PDO MySQL / SQLite connection settings
├── database/                     # Database schemas and seed data
│   ├── schema.sql                # 12 InnoDB relational tables with constraints
│   └── seed.sql                  # Seed data (5 bags, 15 variants, pincodes, admin)
├── public/                       # Web server document root
│   └── index.php                 # Front controller entrypoint
├── src/                          # Backend source code (PSR-4 App\ namespace)
│   ├── Controllers/              # Thin request/response handlers
│   ├── Core/                     # Autoloader, Router, Database, Request, Response, Env
│   ├── Helpers/                  # Logger with sensitive data masking
│   ├── Middleware/               # CORS, JSON validation, RateLimiter, AuthGuard
│   ├── Repositories/             # Data access layer with SQL row locking
│   ├── Services/                 # Business logic, Pricing engine, AuthService
│   └── Validation/               # Input validators (PIN, Phone, Email, Length)
├── src/js/                       # Frontend application modules
│   ├── data/                     # Product catalog and lookbook data
│   └── modules/                  # Cart, quick view, monogram, checkout, and API client
└── tests/                        # Automated unit, integration, and security test suites
```

---

## 3. Database Schema (12 Tables)

| Table | Description | Primary Key / Indexes |
| :--- | :--- | :--- |
| `categories` | Handbag categories (Totes, Shoulder, Crossbody, Hobo, Structured) | `id`, `slug` (UNIQUE) |
| `products` | Core product silhouettes, tags, prices, descriptions, specs | `id`, `slug` (UNIQUE), `category_id` (FK) |
| `product_features` | Specification highlights (e.g. 14" Laptop sleeve, Key leash) | `id`, `product_id` (FK) |
| `product_variants` | Color variations with real-time inventory counts and SKU | `id`, `sku` (UNIQUE), `(product_id, color)` |
| `product_images` | Multi-image sets (`primary`, `hover`, `gallery`) | `id`, `product_id` (FK), `variant_id` (FK) |
| `coupons` | Promo codes, discount types (`percentage`, `fixed`), caps | `id`, `code` (UNIQUE) |
| `pincodes` | 6-digit Indian PIN delivery timelines & COD serviceability | `id`, `pincode` (UNIQUE) |
| `orders` | Atomic customer orders with financial breakdown and status | `id`, `order_number` (UNIQUE), `idempotency_key` |
| `order_items` | Immutable historical snapshots of purchased bags & monograms | `id`, `order_id` (FK), `product_id`, `variant_id` |
| `newsletter_subscribers` | Email subscriptions with source tracking and deduplication | `id`, `email` (UNIQUE) |
| `lookbook_items` | Curated street style profiles with tagged products | `id`, `city_key` (UNIQUE), `product_id` (FK) |
| `admins` | Backoffice user authentication (Argon2id/Bcrypt) & RBAC roles | `id`, `username` (UNIQUE), `email` (UNIQUE) |

---

## 4. REST API Endpoint Reference

### Storefront APIs (Public)

| Method | Endpoint | Description | Rate Limit |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/health` | Diagnostic healthcheck | 120 / min |
| `GET` | `/api/products` | Paginated catalog (`?category=`, `?search=`, `?sort=`, `?page=`, `?limit=`) | 120 / min |
| `GET` | `/api/products/{id_or_slug}` | Full product specs, variants, and image gallery | 120 / min |
| `POST` | `/api/pincode/check` | Delivery estimation & COD feasibility check | 60 / min |
| `POST` | `/api/coupons/validate` | Promotional discount and pricing validation | 30 / min |
| `POST` | `/api/orders/checkout` | Atomic checkout with concurrency stock locking | 10 / min |
| `GET` | `/api/orders/track/{order_number}` | Public order tracking with masked customer data | 120 / min |
| `POST` | `/api/newsletter/subscribe` | Email subscription with normalization | 120 / min |
| `GET` | `/api/lookbook` | Curated street style editorial cards | 120 / min |

### Admin APIs (Protected: `Authorization: Bearer <token>`)

| Method | Endpoint | Description | Role Required |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/admin/login` | Authenticate and obtain JWT bearer token | Public |
| `GET` | `/api/admin/me` | Current authenticated admin profile | `admin`, `manager` |
| `GET` | `/api/admin/dashboard/stats` | Aggregate revenue, order status counts, low-stock alerts | `admin`, `manager` |
| `GET` | `/api/admin/orders` | Paginated order management with search & filters | `admin`, `manager` |
| `PUT` | `/api/admin/orders/{id}/status` | Update fulfillment and payment status | `admin`, `manager` |
| `POST` | `/api/admin/products` | Create new bag silhouette and specs | `admin` |
| `PUT` | `/api/admin/products/{id}` | Update product attributes, price, or description | `admin` |
| `DELETE` | `/api/admin/products/{id}` | Soft deactivation of product | `admin` |
| `PUT` | `/api/admin/inventory/adjust` | Increment or decrement variant stock quantity | `admin`, `manager` |
| `POST` | `/api/admin/coupons` | Create promotional coupons | `admin` |

---

## 5. Quick Start & Setup

### Prerequisites
- PHP 8.2 or higher (with `pdo_mysql`, `mbstring`, `json`, `openssl` extensions enabled)
- MySQL 8.0+ or MariaDB 10.6+

### Installation

1. **Clone repository and configure environment**:
   ```bash
   cp .env.example .env
   ```
   *Edit `.env` with your database credentials.*

2. **Import Database Schema & Seed Data**:
   ```bash
   mysql -u root -p balento_db < database/schema.sql
   mysql -u root -p balento_db < database/seed.sql
   ```

3. **Start Built-in PHP Server**:
   ```bash
   php -S 0.0.0.0:8000 -t public
   ```

4. **Default Development Admin Credentials**:
   - **Username**: `admin`
   - **Password**: `Password@123`
   - **Email**: `admin@balento.com`

---

## 6. Running Automated Tests

Run the complete test suite across all 11 test modules:

```powershell
Get-ChildItem "tests\test_phase*.php" | ForEach-Object {
    Write-Host "Running $($_.Name)..."
    php $_.FullName
}
```

---

## 7. Security Features

1. **SQL Injection Immunity**: 100% prepared PDO statements with explicit typed parameter bindings.
2. **Pessimistic Concurrency Locking**: Uses `SELECT ... FOR UPDATE` during checkout to eliminate race conditions and overselling.
3. **Idempotency Safeguard**: `X-Idempotency-Key` header prevents duplicate orders on network retries.
4. **Argon2id / Bcrypt Passwords**: High-cost password hashing resistant to GPU cracking.
5. **Role-Based Access Control**: `AuthMiddleware` verifies HMAC-SHA256 JWT tokens with role hierarchies.
6. **Data Privacy Masking**: Public tracking masks customer emails (`p***a@example.com`) and phone numbers (`+91 ******3210`).
7. **Sensitive Log Redaction**: Loggers automatically mask passwords, tokens, and payment details.
