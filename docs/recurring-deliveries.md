# Recurring delivery runtime

Recurring deliveries create **drafts for merchant review**. They do not automatically approve prices, dispatch a driver, or charge a delivery payment.

## Local Docker setup

Start the app first so its entrypoint prepares Composer dependencies and `.env`:

```bash
docker compose up -d --build app mysql redis nginx
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=SubscriptionPlanSeeder
docker compose up -d --build scheduler
docker compose exec app php artisan schedule:list
docker compose logs --tail=100 scheduler
```

The scheduler uses the same image, source, environment file and database as the app. It runs `schedule:work`; the scheduled command executes every minute with overlap protection. Keep only one scheduler service per environment. The command also has a cache lock and locks each schedule in the database.

The scheduler deliberately bypasses the app initialization entrypoint to avoid concurrent dependency installation or application-key generation. Finish the app setup before starting it. After changing source or configuration, restart the scheduler.

## Initial plan defaults

| Plan | Active automations |
| --- | ---: |
| Starter | 0 |
| Growth | 5 |
| Business | 20 |
| Enterprise | 50 |

These are initial product defaults, not a Stripe configuration change. Running the plan seeder applies them to existing standard plans and also reapplies the other standard plan attributes. Review any customized plan values before seeding. An active local subscription and a valid linked Stripe subscription remain required.

## Execution and recovery

- Only due, active schedules are processed; an inactive merchant or a plan without automation access cannot generate deliveries.
- A successful occurrence produces one draft, records its delivery ID and advances the next execution in the schedule's local timezone.
- If subscription validation or delivery creation fails, partial delivery writes roll back. A failed run remains recorded with a generic error; detailed exceptions stay in application logs.
- The same occurrence retries on the next scheduler invocation. Once it is more than 15 minutes late it is marked skipped and advances, so an outage does not generate backdated deliveries.
- The default command limit is 50 schedules per invocation. A backlog may need repeated processing; monitor scheduler logs and skipped runs.

Manual processing:

```bash
docker compose exec app php artisan deliveries:run-recurring --limit=50
```

## Verification

Runtime tests cover scheduler registration, duplicate prevention, transactional rollback, failure recovery, expiry, access revocation and weekly timezone changes. API tests cover guest access, subscription validation, creation, ownership and activation limits.

```bash
docker compose exec app php artisan test --filter=RecurringDelivery
```
