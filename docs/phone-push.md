# Native phone push (Expo)

Requires the in-app notification release (327 backend tests). Optional, disabled by default, with no OpenAI requirement. Web continues using in-app alerts. No browser Push, custom background task, silent update or driver location changes are added.

## Data and sender

POST /api/v1/push/device requires an approved authenticated merchant/driver, a real Sanctum bearer token, matching session_id, configured EAS project UUID, platform ios/android, language ar/en and an Expo push token. Encrypted tokens are stored with unique SHA-256 lookup hash. One token belongs to its latest account/session. Registrations expire after 24 hours and renew while opted-in app is foregrounded (on entry/foreground/token rotation and hourly). DELETE on the same endpoint removes devices for the authenticated session; it remains usable when the feature is disabled. Successful server logout deletes its personal access token, cascading devices and pending rows. Cookie sessions cannot register.

Existing database notifications and an idempotent outbox entry per device/notification are written together after delivery commit. The notification itself remains available when Push is disabled. Server command `notifications:send-push` runs each minute, up to 20 send/receipt operations per pass, without overlapping. No additional queue worker is needed. Each operation uses 2s connect/5s total HTTP timeout. Session expiry/revocation, approval, account ownership, registration expiry, deleted/read notifications and stale/non-pending driver offers are checked before submission. Pending notices older than five minutes are abandoned.

Expo send tickets are recorded as `ticket`, not delivered. Receipts are first checked 15 minutes later. `handed_off` means accepted by APNs/FCM, not displayed/read by the phone. Transient errors retry with bounded exponential backoff (four send attempts/eight receipt attempts); DeviceNotRegistered expires the registration. Unknown HTTP success without a usable ticket is not claimed as success. Requests with uncertain network outcomes can produce duplicate alerts; exactly-once delivery is not promised. Terminal entries purge after 30 days; devices expired over seven days purge with dependent rows. APP_KEY encrypts token material; rotating it requires device re-registration.

Only a generic bilingual body and opaque notification UUID leave the server. No delivery/assignment IDs, addresses, customer names, tracking numbers or COD values are sent to the lock screen/provider. Tapping resolves GET /notifications/{id} through the current account, then existing typed in-app routes; arbitrary URLs in push data are ignored. Old account notification UUIDs return 404 for a new account. In-app read markers remain server authoritative.

Offline logout cannot guarantee immediate server revocation; the token lease expires after 24 hours without foreground renewal. Already submitted OS notifications cannot be recalled by deleting a registration. Generic bodies deliberately limit what a stale or already queued alert reveals.

## Setup

1. Apply backend patch, migrate, clear cache and run tests. Apply mobile patch and run npm ci/test/typecheck/lint. No credentials in patches.
2. From deliveryhub-mobile: `npx eas-cli@latest login`, then `npx eas-cli@latest init` to link your own Expo project. Keep the real extra.eas.projectId in app.json; do not replace it with a sample UUID. Configure final Android package/iOS bundle identifier before creating credentials. Current anonymous iOS identifier is not a production identity.
3. Configure Android FCM v1 or iOS APNs credentials following official Expo setup. Android requires its client google-services.json configuration and server service-account credentials in EAS; keep credential files out of public git. iOS physical builds require your Apple signing/provisioning setup. Do not paste credentials into chat. EAS access token, if used for enhanced push security, belongs only in backend EXPO_PUSH_ACCESS_TOKEN.
4. Backend .env: EXPO_PUSH_ENABLED=true; EXPO_PUSH_PROJECT_ID=<your real EAS UUID>; optional EXPO_PUSH_ACCESS_TOKEN=<server-only token if enhanced push security enabled>. Clear cache and restart scheduler. Never put that access token in EXPO_PUBLIC variables.
5. Set EXPO_PUBLIC_API_URL to an API URL reachable from the phone. Phone localhost points to the phone, not the Mac; prefer a suitable HTTPS endpoint. A LAN development URL also needs appropriate native networking configuration. Set API URL in the build environment for bundled previews; dev-client Metro uses your development environment.
6. Build a physical-device development build: `npx eas-cli@latest build --profile development --platform android` (or ios). Install it and run `npx expo start --dev-client --clear`. The included eas.json is an internal-development starting point, not store publishing. Rebuild after adding expo-notifications/config changes. Expo Go and web do not test native remote Push.
7. On the phone sign in, open Notifications and press Enable phone notifications. Grant OS permission. No automatic permission prompt occurs on login. Disable unregisters this session after server success; a network failure shows an error and does not falsely show disabled.
8. With a separate merchant/driver, create a new event and run `docker compose exec app php artisan notifications:send-push`. Confirm phone receives a generic alert while backgrounded/closed, and opens the owned delivery/offer. A ticket alone is not evidence of phone delivery. Run command again after 15 minutes to check receipt. Test denied permission, wrong account/expired offer, logout, token invalidation and disabled feature. A force-stopped app or OS policies may suppress alerts.

## Validation

Six new mobile tests cover opt-in, permission denial, session change while acquiring a token, serialized enable/disable, network failure on disable, owned-notification routing, Expo Go and cancellation. Total 49 mobile tests passed, TypeScript/lint passed and all-platform export passed. Eleven added PHP feature tests cover device registration/encryption/idempotency/session binding, scoped notification lookup, invalid input/project/session, outbox idempotency/privacy, tickets/receipts, DeviceNotRegistered, retry, revoked/read notices, logout cascade, original account rebinding and listener integration. PHP files syntax-parsed; Docker/PHP not installed in packaging environment, so these tests must run locally. No live Expo send, credentials provisioning, EAS build, real-device test or authenticated visual verification has been performed.

Official references:
- https://docs.expo.dev/versions/v57.0.0/sdk/notifications/
- https://docs.expo.dev/push-notifications/push-notifications-setup/
- https://docs.expo.dev/push-notifications/sending-notifications/
- https://docs.expo.dev/build/eas-json/
