# DeliveryHub

### Open-source B2B Delivery Management Platform

**DeliveryHub** is an open-source delivery management platform designed to connect merchants with independent delivery partners through a modern, API-first infrastructure.

The project aims to provide a flexible foundation for managing deliveries, drivers, merchants, pricing, assignments, tracking, and proof of delivery.

> 🚧 **Status: Early Development**
>
> DeliveryHub is currently under active development. APIs, database structures, authentication, and business workflows may change as the project evolves.

---

## ✨ Vision

DeliveryHub aims to become a modular delivery infrastructure that businesses can deploy, customize, and extend for their own delivery operations.

Instead of building a delivery system from scratch, businesses and developers should be able to use DeliveryHub as a foundation and integrate it into their existing platforms.

### The long-term vision

```text
                    DeliveryHub
                         |
        +----------------+----------------+
        |                |                |
        v                v                v
    Merchants         Drivers           Admins
        |                |                |
        +----------------+----------------+
                         |
                         v
                 Delivery Platform
                         |
        +----------------+----------------+
        |                |                |
        v                v                v
     Pricing         Tracking          Analytics
        |                |                |
        +----------------+----------------+
                         |
                         v
                  External APIs
```

---

# 🚀 Features

DeliveryHub is being developed around the following capabilities.

### Merchant Management

* Merchant accounts
* Merchant profiles
* Delivery creation
* Delivery history
* Delivery status
* Delivery pricing
* Delivery addresses
* Proof of delivery

### Driver Management

* Driver registration
* Driver profiles
* Availability status
* Delivery assignments
* Delivery acceptance
* Pickup workflow
* Delivery completion

### Delivery Management

* Create deliveries
* Delivery lifecycle
* Driver matching
* Delivery assignment
* Pickup management
* In-transit status
* Delivered status
* Proof of delivery
* Delivery status history

### Administration

* Admin dashboard
* Merchant management
* Driver management
* Delivery management
* Platform configuration
* Monitoring and reporting

### Developer Platform

* REST API
* API versioning
* Authentication
* Docker development environment
* Automated testing
* GitHub Actions
* Open-source contribution workflow

---

# 🧱 Architecture

DeliveryHub follows an API-first architecture.

```text
                    Client Applications
                           |
              +------------+------------+
              |                         |
              v                         v
        Merchant Web/PWA           Driver Web/PWA
              |                         |
              +------------+------------+
                           |
                           v
                         Nginx
                           |
                           v
                    Laravel Application
                           |
              +------------+------------+
              |                         |
              v                         v
            MySQL                    Redis
              |                         |
              |                         |
              +------------+------------+
                           |
                           v
                    External Services
```

The architecture is designed to support future web applications, mobile applications, integrations, and third-party services without tightly coupling the user interface to the backend.

---

# 🛠 Technology Stack

| Technology      | Purpose                          |
| --------------- | -------------------------------- |
| Laravel 12      | Backend framework                |
| PHP 8.4         | Application runtime              |
| MySQL 8.4       | Primary database                 |
| Redis 7         | Cache, queues and temporary data |
| Nginx           | Web server / reverse proxy       |
| Docker          | Development environment          |
| Docker Compose  | Local infrastructure             |
| Laravel Sanctum | API authentication               |
| Pest / PHPUnit  | Automated testing                |
| GitHub Actions  | Continuous integration           |

---

# 👥 Core Roles

DeliveryHub initially defines three primary roles.

```text
┌───────────────┐
│     Admin     │
└───────┬───────┘
        |
        +----------------------+
        |                      |
        v                      v
┌───────────────┐      ┌───────────────┐
│   Merchant    │      │    Driver     │
└───────┬───────┘      └───────┬───────┘
        |                      |
        +----------+-----------+
                   |
                   v
              Deliveries
```

### Admin

Responsible for platform administration and management.

### Merchant

Creates and manages delivery requests.

### Driver

Accepts and completes delivery assignments.

---

# 📦 Delivery Lifecycle

The core delivery workflow is designed around a predictable state machine.

```text
                    Merchant
                       |
                       v
                Create Delivery
                       |
                       v
                     Pricing
                       |
                       v
                 Driver Matching
                       |
                       v
                Driver Accepts
                       |
                       v
                     Pickup
                       |
                       v
                  In Transit
                       |
                       v
                   Delivered
                       |
                       v
              Proof of Delivery
```

Future versions may introduce additional states such as:

```text
Scheduled
Cancelled
Failed
Returned
Rescheduled
```

---

# 🔐 Authentication & Security

DeliveryHub will use Laravel Sanctum for API authentication.

Security is treated as a core part of the platform rather than a feature added later.

Planned security capabilities include:

* API authentication
* Role-based authorization
* Request validation
* Rate limiting
* Secure password handling
* Token management
* Audit logging
* Sensitive data protection
* Secure environment configuration

Security vulnerabilities should be reported privately.

See:

[SECURITY.md](SECURITY.md)

---

# 🌐 API

DeliveryHub uses a versioned API structure.

```text
/api/v1/
```

Planned API areas include:

```text
/api/v1/auth
/api/v1/users
/api/v1/merchants
/api/v1/drivers
/api/v1/deliveries
/api/v1/addresses
/api/v1/pricing
/api/v1/assignments
```

The API documentation will evolve alongside the implementation.

See:

[API Documentation](docs/api.md)

---

# 🗄 Database

The platform is designed around a relational data model.

Initial core entities include:

```text
User
 |
 +---- Admin
 |
 +---- Merchant
 |
 +---- Driver
          |
          v
       Delivery
          |
     +----+----+
     |         |
     v         v
 Address    Assignment
               |
               v
        Status History
               |
               v
       Proof of Delivery
```

See:

[Database Documentation](docs/database.md)

---

# 🐳 Docker Development

DeliveryHub is designed to run locally using Docker.

The development environment includes:

```text
Docker Compose
      |
      +---- Laravel / PHP 8.4
      |
      +---- Nginx
      |
      +---- MySQL 8.4
      |
      +---- Redis 7
```

This allows contributors to work with a consistent development environment without manually installing PHP, MySQL, Redis, and other infrastructure dependencies.

---

# 💻 Getting Started

## Requirements

Before starting, install:

* Git
* Docker Desktop
* Docker Compose

---

## Clone the repository

```bash
git clone https://github.com/AsmarWeb/deliveryhub.git

cd deliveryhub
```

---

## Configure environment

```bash
cp .env.example .env
```

---

## Start the application

```bash
docker compose up -d --build
```

---

## Generate application key

```bash
docker compose exec app php artisan key:generate
```

---

## Run database migrations

```bash
docker compose exec app php artisan migrate
```

---

## Run tests

```bash
docker compose exec app php artisan test
```

---

## Open the application

```text
http://localhost:8080
```

---

# 🔧 Useful Commands

### Check containers

```bash
docker compose ps
```

### View application logs

```bash
docker compose logs -f app
```

### Open Laravel container

```bash
docker compose exec app bash
```

### Run Artisan

```bash
docker compose exec app php artisan
```

### Run migrations

```bash
docker compose exec app php artisan migrate
```

### Run tests

```bash
docker compose exec app php artisan test
```

### Stop the environment

```bash
docker compose down
```

---

# 🧪 Testing

Automated testing is an important part of the project.

Run the complete test suite:

```bash
docker compose exec app php artisan test
```

Tests are also executed through GitHub Actions.

The CI pipeline is designed to verify:

```text
Code
  |
  v
Dependencies
  |
  v
Environment
  |
  v
Database
  |
  v
Migrations
  |
  v
Tests
```

---

# 🔄 Continuous Integration

GitHub Actions is used for automated testing.

The CI pipeline currently targets:

* PHP 8.4
* MySQL 8.4
* Laravel
* Automated migrations
* Automated tests

Workflow:

```text
Pull Request
      |
      v
GitHub Actions
      |
      v
Install Dependencies
      |
      v
Prepare Environment
      |
      v
Run Migrations
      |
      v
Run Tests
      |
      v
   ✅ / ❌
```

---

# 🗺 Roadmap

## v0.1 — Foundation

* [x] GitHub repository
* [x] Laravel 12
* [x] PHP 8.4
* [x] Docker
* [x] Docker Compose
* [x] Nginx
* [x] MySQL
* [x] Redis
* [x] Open-source documentation
* [x] Contribution guidelines
* [x] Security policy
* [x] Code of Conduct
* [x] GitHub Actions
* [ ] Authentication
* [ ] API authentication
* [ ] Admin role
* [ ] Merchant role
* [ ] Driver role

---

## v0.2 — Delivery MVP

* [ ] Merchant delivery creation
* [ ] Delivery addresses
* [ ] Pricing
* [ ] Driver matching
* [ ] Driver assignment
* [ ] Driver acceptance
* [ ] Pickup
* [ ] In Transit
* [ ] Delivered
* [ ] Proof of Delivery
* [ ] Delivery status history

---

## v0.3 — Platform

* [ ] Merchant dashboard
* [ ] Driver dashboard
* [ ] Admin dashboard
* [ ] Notifications
* [ ] Scheduled deliveries
* [ ] Delivery analytics
* [ ] Driver availability
* [ ] Advanced permissions

---

## v0.4 — Real-Time

* [ ] Real-time delivery status
* [ ] Driver location
* [ ] Live tracking
* [ ] WebSocket infrastructure
* [ ] Customer tracking page
* [ ] Push notifications

---

## Future

* [ ] Payments
* [ ] Merchant wallets
* [ ] Driver wallets
* [ ] External integrations
* [ ] Webhooks
* [ ] Advanced analytics
* [ ] Mobile applications
* [ ] AI-assisted delivery operations
* [ ] Multi-country support
* [ ] Multi-currency support

---

# 🤝 Contributing

DeliveryHub is an open-source project and contributions are welcome.

You can contribute by:

* Reporting bugs
* Suggesting features
* Improving documentation
* Fixing issues
* Writing tests
* Improving the API
* Improving performance
* Improving security
* Building integrations

Before contributing, please read:

[CONTRIBUTING.md](CONTRIBUTING.md)

---

# 🌱 Development Workflow

We recommend the following workflow for contributors:

```text
Fork Repository
       |
       v
Create Branch
       |
       v
Develop Feature
       |
       v
Write Tests
       |
       v
Run Test Suite
       |
       v
Push Branch
       |
       v
Open Pull Request
       |
       v
Code Review
       |
       v
Merge
```

Example:

```bash
git checkout -b feature/delivery-assignment
```

Make your changes, run the tests, then open a Pull Request.

---

# 📚 Documentation

Project documentation:

* [Architecture](docs/architecture.md)
* [Database](docs/database.md)
* [API](docs/api.md)
* [Contributing](CONTRIBUTING.md)
* [Security](SECURITY.md)
* [Code of Conduct](CODE_OF_CONDUCT.md)

---

# 🏗 Project Structure

The project follows a Laravel-based structure with additional infrastructure and documentation.

```text
deliveryhub/
│
├── app/
├── bootstrap/
├── config/
├── database/
├── docker/
│   └── nginx/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
│
├── .github/
│   ├── workflows/
│   ├── ISSUE_TEMPLATE/
│   └── pull_request_template.md
│
├── docs/
│   ├── architecture.md
│   ├── database.md
│   └── api.md
│
├── Dockerfile
├── docker-compose.yml
├── composer.json
├── CONTRIBUTING.md
├── SECURITY.md
├── CODE_OF_CONDUCT.md
├── LICENSE
└── README.md
```

---

# 🌍 Open Source

DeliveryHub is being developed with an open-source-first approach.

The goal is to build a platform where developers, logistics companies, merchants, and technology partners can contribute ideas, integrations, improvements, and new capabilities.

If you are interested in helping build the project, contributions are welcome.

---

# 📄 License

DeliveryHub is released under the **MIT License**.

See [LICENSE](LICENSE) for more information.

---

# ⭐ Support the Project

If you find DeliveryHub useful:

* ⭐ Star the repository
* 🐛 Report issues
* 💡 Suggest features
* 🔧 Submit Pull Requests
* 📖 Improve documentation
* 🤝 Share the project with other developers

Every contribution helps the project grow.

---

## DeliveryHub

**Build the infrastructure for modern delivery operations.**

[GitHub Repository](https://github.com/AsmarWeb/deliveryhub)
