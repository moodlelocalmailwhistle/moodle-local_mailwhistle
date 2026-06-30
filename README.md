# mailwhistle: Moodle Local Plugin Boilerplate (Moodle 5+)

This folder is a reusable starter for creating Moodle local plugins on Moodle 5 LTS and above.

## 1) Boilerplate structure (folders + files)

```
local/mailwhistle/
  index.php
  lib.php
  settings.php
  styles.css
  version.php
  README.md
  db/
    access.php
    install.xml
    upgrade.php
  lang/
    en/
      local_mailwhistle.php
  classes/
    helper.php
    event/
      data_created.php
    privacy/
      provider.php
  tests/
    helper_test.php
```

## 2) Essential files and purpose

| File | Purpose | Required |
|------|---------|----------|
| `version.php` | Plugin metadata and Moodle compatibility | Yes |
| `lib.php` | Main plugin library with hooks (install, upgrade, uninstall, navigation) | Yes |
| `db/access.php` | Capability definitions (permissions) | Yes |
| `lang/en/local_mailwhistle.php` | Language strings | Yes |
| `settings.php` | Global admin settings page | Optional (recommended) |
| `classes/` | Business logic and helper classes (PSR-4 namespaced) | Optional (recommended) |
| `classes/privacy/provider.php` | Privacy provider (export/delete personal data) | Optional (required if storing personal data) |
| `db/install.xml` | Database schema if storing data | Conditional |
| `db/upgrade.php` | Versioned upgrade steps for schema/data changes | Conditional |
| `styles.css` | Plugin-specific CSS | Optional |
| `index.php` | Custom admin/user page | Optional |
| `tests/` | PHPUnit test cases | Optional (recommended) |

## 3) Minimal working behavior in this seed

After install:

1. Plugin is discovered and listed in **Site administration → Plugins → Local plugins**.
2. Site admins can configure settings in **Site administration → Plugins → Local plugins → Mail Whistle**.
3. Language strings are properly loaded.
4. Helper class can be instantiated and used (`new \local_mailwhistle\helper()`).

This proves that:
- Plugin discovery works
- Language strings are wired
- Admin settings integration works
- Class autoloading (PSR-4) works

Note: This boilerplate includes a minimal privacy provider stub at `classes/privacy/provider.php`.
If your plugin stores or processes personal data (for example `local_mailwhistle_data.userid`), implement the export and deletion logic in that provider before shipping the plugin.

## 4) What to change for each new plugin (mandatory)

When cloning this boilerplate for a real plugin, replace all these consistently:

1. **Folder name**
   - `local/mailwhistle` → `local/<yourpluginname>`

2. **Component and frankenstyle**
   - `local_mailwhistle` → `local_<yourpluginname>`

3. **Class namespace**
   - `namespace local_mailwhistle;` → `namespace local_<yourpluginname>;`

4. **Language file name and string keys**
   - `lang/en/local_mailwhistle.php` → `lang/en/local_<yourpluginname>.php`
   - Update all language key prefixes if needed

5. **Capabilities namespace**
   - `local/mailwhistle:*` → `local/<yourpluginname>:*`

6. **Template namespace**
   - `local_mailwhistle/example` → `local_<yourpluginname>/example`

7. **Settings key prefix**
   - `local_mailwhistle/...` → `local_<yourpluginname>/...`

8. **Version metadata**
   - Update `version.php`: version, release, copyright

## 5) Moodle community best practices (Moodle 5+)

### Naming conventions

- Use frankenstyle component name: `local_<pluginname>`.
- Folder name must match plugin name exactly: `local/<pluginname>`.
- Capability names must be `local/<pluginname>:<action>`.
- Keep plugin name lowercase, short, and stable; renaming later is expensive.
- Use PSR-4 namespacing for all classes: `namespace local_<pluginname>;`.

### Security checks

- Put `defined('MOODLE_INTERNAL') || die();` in every PHP entry file.
- Enforce capability checks at the correct context (`context_system`, `context_course`, etc.).
- Validate and sanitize all external input with Moodle parameter APIs (`required_param`, `optional_param`, `PARAM_*`).
- Escape output properly: prefer Mustache templates and Moodle renderers.
- Keep direct SQL minimal and always parameterized with `$DB->prepare_sql()` or similar.

### Privacy API

- Implement `classes/privacy/provider.php` to declare, export and delete any personal data your plugin stores.
- This boilerplate includes a stub provider — customize it to match your schema (exports, deletions, and context discovery).

### Directory hardening

- Add `index.html` files to non-public directories (this boilerplate includes `index.html` in `amd/`, `classes/`, `db/`, `lang/`, `templates/`, and `tests/`) to reduce directory listing exposure on misconfigured servers.

### Database and upgrade path handling

- Put install-time schema in `db/install.xml` (for first install).
- Handle subsequent schema/data changes in `db/upgrade.php` only.
- Never edit old upgrade steps in `db/upgrade.php`; append new versioned blocks only.
- Increase `$plugin->version` every time schema/data upgrade logic is added.
- End each upgrade step with `upgrade_plugin_savepoint(true, <newversion>, '<pluginname>');`.

### Coding style and maintainability

- Follow Moodle coding style and run `moodle-plugin-ci` (or PHPCS with Moodle standard) in CI.
- Keep business logic out of `lib.php`; move reusable logic to `classes/`.
- Keep templates presentation-only; avoid heavy logic in Mustache.
- Use language strings for all UI text (no hard-coded user-facing text in PHP/JS).
- Add tests early for critical logic (`tests/` with PHPUnit where relevant).
- Use events (`classes/event/`) to signal important business actions.

## 6) New plugin checklist (copy each time)

- [ ] Copy `local/mailwhistle` to `local/<newname>`.
- [ ] Replace all `mailwhistle` and `local_mailwhistle` references across files.
- [ ] Update namespaces in all classes (header of each `.php` file in `classes/`).
- [ ] Rename language file to `lang/en/local_<newname>.php`.
- [ ] Confirm `version.php` has correct:
  - `component` name (`local_<newname>`)
  - Fresh `version` timestamp
  - `requires` for Moodle 5+
- [ ] Update `db/access.php` capability names to `local/<newname>:*`.
- [ ] Update template names and `render_from_template()` calls to use `local_<newname>/...`.
- [ ] Review settings keys in `settings.php` and change prefix to `local_<newname>/`.
- [ ] If storing data: configure `db/install.xml` and plan `db/upgrade.php` steps.
- [ ] Purge caches and test install/upgrade on a clean Moodle 5 instance.
- [ ] Add at least one PHPUnit test before feature growth.
 - [ ] Implement `classes/privacy/provider.php` when storing user data.
 - [ ] Ensure language strings exist for all `get_string()` usages (check `lang/en/local_<newname>.php`).
 - [ ] Keep `index.html` hardening files in non-public directories.

## 7) Install quick test

1. Copy/symlink boilerplate to `local/<newname>` in a Moodle 5 test instance.
2. Go to **Site administration → Notifications** to trigger plugin install.
3. Purge caches (`Site administration → Development → Purge caches`).
4. Verify plugin is listed in **Site administration → Plugins → Local plugins**.
5. Visit **Site administration → Plugins → Local plugins → Local <Newname>** and check settings page loads.
6. Write/run a test: `php vendor/bin/phpunit --testsuite local_<newname>`.
7. Review plugin logs if custom functionality is added.

---

**About**: Reusable Moodle 5+ local plugin boilerplate for quickly scaffolding new system-level plugins with database, settings, events, and testing in place.

**Moodle Compatibility**: 5.0 LTS+
