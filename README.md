# Raslo

**Open-source delivery infrastructure for merchants, drivers, and e-commerce.**

Raslo connects businesses with delivery partners to manage pricing, assignments, pickup, tracking, and proof of delivery. Previously named DeliveryHub, the project is built with Laravel and an API-first architecture, with a companion Expo application for merchants and drivers.

> Active development. Existing delivery workflows are implemented; store integrations described below are planned. Test coverage and a working development build do not constitute production certification.

## What exists today

- Merchant and driver registration, authentication, account completion, and administration.
- Delivery creation, pricing and merchant price approval, assignment, driver acceptance, pickup, transit, and completion.
- Driver availability and location updates, tracking, status history, and proof of delivery.
- Merchant addresses, cash-on-delivery collection/receipt, and financial views.
- Subscription billing integration with Stripe.
- Recurring deliveries and automatic dispatch, with optional AI ranking.
- In-app notifications and Expo phone push, including device registration and an outbox.
- Docker development infrastructure, automated tests, and GitHub Actions.

The companion app has been tested on an Android development build with a real push notification. Other workflows and production credentials must be validated in their deployment environment.

## E-commerce direction

The store continues to own its products, checkout, and payment. Raslo will provide the delivery option:

1. The store submits pickup, destination, and parcel details to request delivery options.
2. Raslo returns service availability, a delivery price, and an estimated service window.
3. After the customer chooses delivery and the order is confirmed, the store creates a delivery using a stable external reference and an idempotency key.
4. Raslo dispatches the delivery and returns tracking and status updates to the store.

**This integration API is planned, not yet available.** It does not require copying an entire product catalog or collecting the store's checkout payment through Raslo.

## Roadmap

| Stage | State | Scope |
| --- | --- | --- |
| Foundation | Implemented | Laravel, Docker, authentication, roles, versioned API, CI |
| Delivery operations | Implemented; validation continues | Pricing, assignments, pickup, tracking, delivery proof, COD |
| Merchant and driver app | Development build | Account flows, delivery operations, in-app and phone notifications |
| Automation and subscriptions | Implemented; validation continues | Recurring deliveries, dispatch, Stripe billing |
| Integration foundation | Next | Store connections, scoped credentials, merchant isolation, idempotency, event logs |
| Delivery integration API | Planned | Serviceability and quotes, booking, tracking, signed outgoing webhooks, sandbox |
| Store connectors | Planned | Shopify, WooCommerce, and custom stores using the shared integration layer |
| Production pilot | Planned | End-to-end merchant trials, failure recovery, monitoring, deployment hardening |

## Stack

Laravel 12, PHP 8.4 development target, Sanctum, Laratrust, Stripe Cashier, Docker Compose, Nginx, Redis, and relational storage. The environment template defaults to SQLite; Compose also includes MySQL. Configure database settings to match the selected deployment.

The [mobile application](https://github.com/AsmarWeb/deliveryhub-mobile) uses Expo SDK 57, React Native, and TypeScript.

## Run locally

The repository is currently hosted under its original GitHub name:

```bash
git clone https://github.com/AsmarWeb/deliveryhub.git raslo
cd raslo
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Check `docker-compose.yml` for the published HTTP port. Configure the database, application URL, and mail settings in your local `.env`. Never commit live credentials.

For an existing installation, follow [the rename guide](docs/raslo-rebrand.md). Existing Docker and database identifiers remain compatible.

## Checks

```bash
docker compose exec app php artisan test
docker compose exec app php artisan schedule:list
```

Inspect GitHub Actions for the current CI result. Earlier local results are not a guarantee that the latest branch passes.

## API and operations

Application endpoints currently use `/api/v1` with Sanctum authentication and resource authorization. These are application APIs; a dedicated, scoped store integration contract is the next milestone.

- [API notes](docs/api.md)
- [Database notes](docs/database.md)
- [Architecture notes](docs/architecture.md)
- [Phone push](docs/phone-push.md)
- [Automatic dispatch](docs/automatic-dispatch.md)
- [Stripe billing](docs/stripe-billing.md)
- [Raslo rename and compatibility](docs/raslo-rebrand.md)

For push, configure your own Expo/Firebase credentials and use a development build. The scheduler handles `notifications:send-push`; enabling it alone does not verify device delivery.

## Contributing

Open issues and pull requests for fixes, tests, documentation, and integrations. Keep provider-specific store logic outside the core delivery workflow. Include validation evidence and clearly distinguish implemented behavior from proposed APIs.

Please read [the code of conduct](CODE_OF_CONDUCT.md) and [security policy](SECURITY.md). Do not publish secrets or customer data in issues.

## License

[MIT](LICENSE).
