# local_mailwhistle

Moodle local plugin for sending newsletters / email campaigns to an audience of users. Moodle 5.0 LTS+.

> Status: early development (`MATURITY_ALPHA`). The send engine and most tabs are placeholders; the data model, admin UI shell, and privacy provider are in place.

## Install

1. Copy this folder to `local/mailwhistle` in your Moodle.
2. Visit **Site administration → Notifications** to run the install.
3. Purge caches.

Open at **Site administration → Mail Whistle**, or `/local/mailwhistle/index.php`.

## What works today

- Tabbed admin page: **Send**, Audience, Templates, Reports. Non-send tabs show "coming soon".
- Send tab lists a sample sent-newsletter history with a detail view.
- Database schema for campaigns, audience rules, recipients, send logs, and unsubscribes (`db/install.xml`).
- Privacy (GDPR) provider for recipient and unsubscribe data.

## Capabilities

| Capability | Role | Purpose |
|------------|------|---------|
| `local/mailwhistle:view` | user, manager | View the plugin |
| `local/mailwhistle:manage` | manager | Manage campaigns |
| `local/mailwhistle:configure` | manager | Configure settings |

## Development

- Run tests: `php vendor/bin/phpunit local/mailwhistle/tests/`
- Lint: `phpcs --standard=moodle .` (or `moodle-plugin-ci`)

## License

GNU GPL v3 or later.
