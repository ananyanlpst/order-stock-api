
# Order & Inventory Management REST API

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?logo=mysql&logoColor=white)
![Pest](https://img.shields.io/badge/Testing-Pest-555555)
![GitHub Actions](https://img.shields.io/badge/CI-GitHub_Actions-2088FF?logo=githubactions&logoColor=white)

A backend portfolio project built with Laravel to manage products, inventory, and orders through a secure REST API.

The project focuses on backend architecture, business logic, role-based access control, data integrity, and automated testing.

## Engineering Highlights

- RESTful API design and implementation
- Token-based authentication with Laravel Sanctum
- Role-based access control (Admin and Staff)
- Database transactions and pessimistic locking
- Inventory consistency and stock management
- Historical order data preservation
- Server-side validation and business logic
- Automated testing with Pest
- Continuous integration with GitHub Actions

## Tech Stack

- Laravel 13 — developed with version 13.39.0
- PHP 8.4 — developed with version 8.4.27
- MySQL for local development
- SQLite in memory for automated tests
- Laravel Sanctum for API token authentication
- Pest for automated testing
- GitHub Actions for continuous integration

## Features

- Login and logout with API tokens
- Tokens expire after one day
- Login rate limiting
- Admin and staff permissions
- Product CRUD with unique SKU validation
- Product search by name or SKU
- Paginated product and order lists
- Order filtering by status
- Order creation with stock validation and deduction
- Order cancellation with stock restoration
- Protection against repeated cancellation
- Order item snapshots that preserve purchase-time names, SKUs, and prices
- Prevention of deleting products referenced by order history

## Permissions

| Action | Admin | Staff |
|---|---|---|
| View and search products | Yes | Yes |
| Create, update, or delete products | Yes | No |
| Create orders | Yes | Yes |
| List and view orders | All orders | Own orders |
| Cancel confirmed orders | All orders | Own orders |

All product and order endpoints require authentication.

## Local Setup

### Requirements

- PHP 8.4 with the extensions required by Composer
- Composer 2
- MySQL
- SQLite PHP extension for testing

### Installation

```bash
git clone https://github.com/ananyanlpst/order-stock-api.git
cd order-stock-api
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database and an application database user.
Configure the connection in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=order_stock
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Run migrations and add demo products:

```bash
php artisan config:clear
php artisan migrate
php artisan db:seed --class=ProductSeeder
```

The product seeder creates missing demo SKUs without resetting existing products.

Start the local server:

```bash
php artisan serve
```

The API base URL is `http://127.0.0.1:8000/api`.
Laravel Herd can also serve the project.

### Create a Local User

There is currently no public registration endpoint.

Run:

```bash
php artisan tinker
```

Create a local account, replacing the example password before running:

```php
$user = \App\Models\User::create([
    'name' => 'Local Admin',
    'email' => 'admin@example.com',
    'password' => 'REPLACE_WITH_YOUR_LOCAL_PASSWORD',
]);

$user->role = 'admin';
$user->save();
```

Passwords are automatically hashed by the User model.
New users default to the staff role.

## API Endpoints

Paths below are relative to `/api`.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/login` | Authenticate and issue a token |
| POST | `/logout` | Revoke the current token |
| GET | `/user` | Retrieve the authenticated user |
| GET | `/products` | List or search products |
| GET | `/products/{product}` | View a product |
| POST | `/products` | Create a product |
| PATCH | `/products/{product}` | Update a product |
| DELETE | `/products/{product}` | Delete a product without order history |
| GET | `/orders` | List accessible orders |
| GET | `/orders/{order}` | View an accessible order with items |
| POST | `/orders` | Create an order |
| POST | `/orders/{order}/cancel` | Cancel an order and restore stock |

Send these headers when calling protected endpoints:

```http
Accept: application/json
Authorization: Bearer YOUR_TOKEN
```

For JSON request bodies, also send:

```http
Content-Type: application/json
```

### Create an Order

```json
{
  "items": [
    {
      "product_id": 3,
      "quantity": 2
    },
    {
      "product_id": 4,
      "quantity": 1
    }
  ]
}
```

Use product IDs that exist in your database.

Prices and totals are calculated by the server.
Duplicate product IDs in the same request are rejected.

### Search and Filter

```text
GET /api/products?search=Pen
GET /api/orders?status=confirmed
GET /api/orders?status=cancelled
```

## Data Integrity

Order creation and cancellation use database transactions.

Product rows are locked with `lockForUpdate()` during stock changes.
Cancellation also locks the order before checking its status.

If an operation fails, database changes within the transaction are rolled back.
Cancelling an already cancelled order is rejected before stock is restored again.

Money calculations use integer minor units before being stored as decimal amounts.

## Testing

```bash
php artisan config:clear
php artisan test
```

The test suite uses an in-memory SQLite database configured in `phpunit.xml`.

Tests cover authentication, permissions, product validation, order totals,
stock deduction, rollback, cancellation, search, and status filtering.

GitHub Actions runs the test suite on pushes to `main` and pull requests
targeting `main`.

### Testing Limitations

SQLite tests verify application behavior but do not verify MySQL row-locking
behavior under concurrent requests. Concurrent stock updates require separate
integration testing against MySQL.


## Project Status & Roadmap

### Implemented

- REST API for products, inventory, and orders
- Authentication and role-based authorization
- Product and order management
- Transaction-based stock management
- Automated testing with Pest
- Continuous integration with GitHub Actions

### Planned Improvements

- React and TypeScript frontend
- Interactive API documentation
- MySQL concurrency integration testing

This project is part of my professional software development portfolio.
