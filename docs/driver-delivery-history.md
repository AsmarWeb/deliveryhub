# Driver delivery history

The web dashboard sidebar links to `/driver/delivery-history` instead of a Soon placeholder. Authenticated users with a driver role and Driver profile can view delivered/completed shipments credited to their last accepted assignment. Declined, expired and active offers are excluded. A subsequent accepted driver assignment removes credit from the previous driver. Results are paginated, searchable by tracking number and filterable by delivery status. Times are shown in Asia/Dubai. Counts reflect the full own history, independent of the search filter.

The details button uses the existing owned assignment page. Fees are not presented as driver earnings. No migration, mobile change or new delivery state transition is introduced.

Verify with `php artisan test --filter=DriverDeliveryHistoryTest` and the full suite. Test desktop/mobile widths and Arabic/English in the deployed dashboard. Automated tests cover access, ownership, reassignment, status selection, search and pagination; no real browser visual check was run in this execution environment.
