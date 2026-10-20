# UAE delivery addresses

Adds nullable emirate, landmark and Makani number fields. Existing addresses, city/address_line and existing building/unit fields remain unchanged. No backfill or conversion is performed. Apply the backend patch and run migrations before applying the mobile update.

Emirate codes: abu_dhabi, dubai, sharjah, ajman, umm_al_quwain, ras_al_khaimah, fujairah. New fields are optional at API level for backwards compatibility. The new mobile address form asks for an emirate when creating a new map address. Existing saved addresses can still be selected without editing.

Makani is optional, allowed for Dubai only, and stored as a ten-character string to preserve leading zeros. Arabic/Persian digits and spaces normalize to Latin digits. This validates format only; no address lookup or claim of a real Makani match is made. All seven emirates can save without Makani. API and merchant web forms share the same validation rules.

The mobile form exposes building/villa, unit, landmark, access instructions and the new emirate/Makani fields. A pin must be selected or explicitly confirmed. Saving errors are inline and the save lock clears on failure. Driver assignment details show the structured address and access instructions; navigation uses valid coordinates when present and falls back to text for legacy addresses.

Checks: mobile unit tests, TypeScript, lint and platform exports. Five Laravel feature tests cover storage and ownership, legacy payloads, Makani constraints/normalization, invalid values and non-Dubai addresses. PHP tests must run in Docker; packaging environment has no PHP runtime. PHP parser checks are not execution tests. Device/authenticated visual checks still required.

Manual acceptance: create Dubai pickup with Arabic Makani and building/unit; create Sharjah dropoff without Makani; view both as assigned driver; check map navigation pin; retry a failed save; select old saved addresses; edit new fields in merchant web address book.
