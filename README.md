moodle-local_mailwhistle
=====================
[![Moodle Plugin CI](https://github.com/moodlelocalmailwhistle/moodle-local_mailwhistle/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=main)](https://github.com/moodlelocalmailwhistle/moodle-local_mailwhistle/actions/workflows/moodle-plugin-ci.yml)
[![Latest Release](https://img.shields.io/github/v/release/moodlelocalmailwhistle/moodle-local_mailwhistle?sort=semver&color=orange)](https://github.com/moodlelocalmailwhistle/moodle-local_mailwhistle/releases)
[![PHP Support](https://img.shields.io/badge/php-8.1--8.4-blue)](https://github.com/moodlelocalmailwhistle/moodle-local_mailwhistle/actions)
[![Moodle Support](https://img.shields.io/badge/Moodle-4.5--5.2+-orange)](https://github.com/moodlelocalmailwhistle/moodle-local_mailwhistle/actions)
[![License GPL-3.0](https://img.shields.io/github/license/moodlelocalmailwhistle/moodle-local_mailwhistle?color=lightgrey)](https://github.com/moodlelocalmailwhistle/moodle-local_mailwhistle/blob/main/LICENSE)
[![GitHub contributors](https://img.shields.io/github/contributors/moodlelocalmailwhistle/moodle-local_mailwhistle)](https://github.com/moodlelocalmailwhistle/moodle-local_mailwhistle/graphs/contributors)

Moodle local plugin for sending newsletters / email campaigns to an audience of users. Moodle 4.5 LTS+.

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
