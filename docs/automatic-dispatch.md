# Automatic driver offers and optional AI ranking

## Runtime

The existing scheduler runs `deliveries:auto-dispatch` each minute without overlapping. The command processes up to 25 due Matching orders per pass, oldest last-attempt first (new/unattempted orders first), plus up to 100 expired automatic offers. Waiting orders rotate through the batch instead of starving newer orders. Manual dispatch remains available and keeps its existing admin authorization. No new queue worker is required for dispatch itself.

An order needs merchant price approval, a recorded delivery fee (zero permitted), an active merchant/merchant role, valid pickup coordinates and a due/null scheduled_at. A driver must be approved/active, have the driver role, be available, opt into dispatch, have foreground presence within 180 seconds, a reported location within 300 seconds (and acceptable clock bounds) and spare active-task capacity. Reports require location accuracy <=500m and an observation timestamp within the last two minutes. Dispatch coordinates are separate from delivery-specific location tracking; older fixes cannot replace newer fixes. Going unavailable clears dispatch presence/location, so returning online needs a fresh update.

Candidates must be within DISPATCH_RADIUS_KM (default 30, straight-line distance). Among at most 500 recently present drivers, the rules rank pickup distance plus a 5 km penalty per active task, breaking ties by driver ID. Only the top five eligible candidates are considered. This is a pilot-scale heuristic, not route/traffic ETA prediction, vehicle weight/dimension matching or a trained optimization model.

Each offer reserves capacity via the existing assignment writer under delivery/driver row locks. Hard eligibility, state and active-assignment checks are repeated under those locks. It records automatic/rules/ai, distance, active load and capacity in delivery status history. The actor is the order's merchant user with explicit automation metadata; no fake administrator is created. Driver acceptance remains required.

Automatic pending offers expire after 120 seconds and return the order to Matching. Rejected/expired drivers are excluded for that order. Accepted offers and manual offers are not expired by this command. When no eligible driver exists the order stays Matching and retries in future passes. No fee, commission or COD amount is changed.

## Driver consent and merchant display

Driver account → Automatic driver offers → Enable & share availability location. Driver must first set availability on and allow foreground location. Dispatch sharing is off by default for existing drivers. Approved driver routes then refresh dispatch presence about every 60 seconds only while the app is foregrounded, opted in and available. It does not request background permission or add a GPS watcher. Disabling dispatch clears its coordinates/presence and prevents new automatic offers; it does not cancel an existing assignment. Delivery-specific tracking remains unchanged.

Merchant home shows up to 10 available opted-in drivers with recent GPS and capacity, updating about every 30 seconds while focused/foregrounded. Only ID, first name and vehicle type are returned. No precise coordinates, phone, plate or licence is exposed. This platform-wide list does not guarantee a driver is near an as-yet-unselected pickup address. Full order matching applies the radius check. Actual availability can change after display.

## Optional AI

DISPATCH_ENABLED defaults true. DISPATCH_AI_ENABLED defaults false. The rule-based automation is fully functional without an AI key. To enable AI ranking, configure local `.env`:

```env
DISPATCH_ENABLED=true
DISPATCH_RADIUS_KM=30
DISPATCH_AI_ENABLED=true
DELIVERYHUB_AI_API_KEY=YOUR_LOCAL_OPENAI_KEY
DELIVERYHUB_AI_MODEL=gpt-4.1-mini
```

Then run `php artisan optimize:clear` and restart the scheduler. Never commit the actual key.

AI receives only pseudonymous candidate IDs, distance, active-task count and task capacity. No names, phones, raw coordinates, addresses, customer/parcel details or COD values are sent. Responses API structured output is restricted to candidate IDs and the returned ID is checked again. `store:false` is sent; this is not a claim about provider-wide retention policy. A timeout, refusal, bad/unavailable model, invalid response, HTTP failure or absent key falls back to the rules. Hard constraints cannot be overridden by AI. At most five AI requests per command pass; each has a four-second timeout and a 100-output-token cap. A sole candidate does not require an AI request. Configured AI requests use your API account.

`ai_used` in command output counts offers actually placed using an AI recommendation; history `dispatch_method` records ai/rules/manual. No live AI request was made during packaging; AI behavior was tested with fake HTTP responses.

References: https://developers.openai.com/api/docs/guides/structured-outputs and https://docs.expo.dev/versions/v57.0.0/sdk/location/

## Deployment / acceptance

Apply after COD update. Migrate first, clear caches, restart scheduler and check `schedule:list`. Both rules and AI share the same hard checks. Existing drivers must opt in; missing/old GPS deliberately prevents automatic offers. On a phone/browser supporting geolocation, use separate merchant/driver sessions, enable driver availability and dispatch, keep the driver app foregrounded, create an order with a nearby actual pickup pin and approve its price. Run `deliveries:auto-dispatch` to test immediately or wait for the scheduler. The driver should receive a pending assignment and accept it. Test refusal/timeout with a second eligible driver; test offline/permission denied/full capacity/missing pin; verify merchant list has no private driver fields.

Thirteen Laravel tests cover selection, capacity, duplicates, stale/offline/out-of-radius/opted-out drivers, missing pickup coordinates, rejection/expiry/manual preservation, accepted offers, AI membership/fallback and valid ranking, payload privacy, consent, invalid reports, disabled automation, lock-time revalidation, scheduled dates and queue fairness. They must run in Docker; the packaging environment only syntax-parsed PHP. Five mobile tests verify GPS opt-in, offline/background/abort handling and safe opt-out; TypeScript, lint and exports also checked. Real device GPS, live AI, concurrent MySQL stress and authenticated visual review remain manual checks.
