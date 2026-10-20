# Progressive merchant and driver registration

Merchant signup keeps five visible essentials: full name, business name, email, UAE phone and password. Driver signup keeps four: name, email, UAE phone and password. The UI sends password_confirmation from the single password field and offers a visibility toggle. Existing full payloads remain compatible.

Registration still returns pending without an authentication token. Users sign in through the existing login endpoint and open Account details from application status while awaiting approval. Operational route protections and driver role assignment remain unchanged.

GET/PATCH `/api/v1/auth/account` require Sanctum and infer the profile from the authenticated user, never from a client ID. They expose only explicit profile fields. Pending applicants may save partial details. Required missing driver details are listed. Business email, trade licence and tax number remain optional as before. No policy or legal verification has been added.

Admin driver activation now requires phone, vehicle type, plate and licence number. Completing fields does not approve an applicant or assign a driver role. Reviewed (active/inactive) details require administrative support to change; names can still be updated. This avoids silently changing previously reviewed vehicle/business data. No migration or token permission changes are required.

Run `php artisan test` before deployment. New feature coverage checks short signup, profile completion without activation, account ownership/privilege injection, reviewed field immutability, guest access and incomplete driver approval. The development environment used for packaging has no PHP runtime; PHP files were parsed with php-parser, not executed.

Manual checks: Arabic/English, narrow screen/keyboard, password visibility, duplicate email, invalid UAE phone, offline failure, account created success → login → application status → complete account → admin review → operational dashboard. Also test existing approved users and full legacy signup payloads.
