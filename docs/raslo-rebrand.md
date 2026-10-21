# Raslo rename guide

The product display name is now **Raslo**. Website copy, dashboard labels, mobile screens, push notification titles, and project documentation use the new name.

## Existing installations

1. Pull the latest changes in both repositories with a clean working tree (`git pull --ff-only`).
2. Set `APP_NAME=Raslo` in the backend's actual `.env`; updating `.env.example` does not modify your local environment.
3. Run `docker compose exec app php artisan optimize:clear` and restart the scheduler and any long-running queue workers.
4. Restart Metro for the mobile app. Create and install a new development build to update the phone launcher name.
5. Test login, billing return links, and a real push notification.

## Compatibility identifiers

These identifiers intentionally retain their legacy values so an identity change does not disconnect existing installations:

- EAS project ID `61936adc-7439-441f-a258-a6b1dcabed36`, owner `asmarweb`, and slug `deliveryhub-mobile`.
- Android package `com.asmarweb.deliveryhub`, existing iOS bundle identifier, and Firebase association.
- URL scheme `deliveryhubmobile`, including billing return links.
- Mobile authentication, language and push preference storage keys; backend cache keys.
- Docker service/container/network configuration, database names/users, and existing volumes.
- `DELIVERYHUB_AI_API_KEY`, `DELIVERYHUB_AI_MODEL`, `DELIVERYHUB_LEADS_EMAIL`, and the matching internal services configuration namespace.
- Existing token names, demo email addresses, and GitHub repository URLs.

Do not globally replace these strings in a live installation. A later technical identifier migration needs explicit data/credential and deep-link migration steps. Existing PNG artwork is retained; a new visual logo/icon is a separate design task.

## Rename the GitHub repositories

The connector used for this change can edit code but cannot rename repository metadata.

As the repository owner, open each repository's **Settings → General → Repository name**:

- `AsmarWeb/deliveryhub` → `AsmarWeb/raslo`
- `AsmarWeb/deliveryhub-mobile` → `AsmarWeb/raslo-mobile`

Suggested descriptions:

- Backend: `Open-source delivery infrastructure for merchants, drivers, and e-commerce.`
- Mobile: `Raslo merchant and driver app built with Expo and React Native.`

After the rename, update your local remotes:

```bash
# Inside the backend repository
git remote set-url origin https://github.com/AsmarWeb/raslo.git

# Inside the mobile repository
git remote set-url origin git@github.com:AsmarWeb/raslo-mobile.git
```

Update repository links in both READMEs and the backend `HomeController` release lookup and website source links once the new names exist. Existing links are retained until then rather than pointing at uncreated repositories.

Renaming the Expo project is separate from GitHub. Keep its current slug and project ID during this change. Coordinate any Expo dashboard rename with `app.json` before the next EAS build.
