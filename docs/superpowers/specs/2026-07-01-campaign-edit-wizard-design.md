# Draft campaign edit wizard

## Goal

Let a user click a draft campaign and step through forms to fill it in — name and
sender, email content, audience tags — then mark it complete once the required
parts are present. A draft can be re-opened and edited any number of times until it
is completed.

## Entry point

The draft-campaigns table (Send tab) lists draft campaigns. The campaign name becomes
a link to `campaign_edit.php?campaignid=N`. The wizard opens at step 1.

## Wizard

Single controller `campaign_edit.php?campaignid=N&step=<step>`. Steps:

| Step | Key | Form | Fields written |
|------|-----|------|----------------|
| 1 | `details` | campaign_details_form | name, sendername, senderemail |
| 2 | `content` | campaign_content_form | subject, bodyhtml (editor), bodytext (derived) |
| 3 | `audience` | campaign_audience_form (reused) | audrules tag rows |
| 4 | `review` | read-only summary + Mark complete | status |

Navigation: every step form has **Back** and **Save & continue**. Saving persists that
step and advances to the next. Back goes to the previous step without saving. A step
indicator (tabtree) shows progress and allows jumping to any step directly.

Review step shows all current values and a completeness checklist. The **Mark complete**
button is enabled only when the campaign passes the completeness gate; clicking it sets
`status` from `draft` to `ready` and returns to the Send tab.

## Completeness gate

`campaign_manager::is_complete(int $campaignid): bool` returns true when ALL of:
- `name` is non-empty
- `subject` is non-empty
- `bodyhtml` OR `bodytext` is non-empty
- the campaign has at least one audience tag (audrules tag row)

## Components

### `campaign_edit.php` (new)
Wizard controller. `require_login()` + `require_capability('local/mailwhistle:manage')`.
Loads the campaign (`MUST_EXIST`). Blocks editing when `status !== 'draft'` (already
sent/scheduled): shows a read-only notice and a link back. Reads `step` (PARAM_ALPHA,
clamped to a known step, default `details`). Routes to the step form, handles
save/back/complete.

### `classes/form/campaign_details_form.php` (new)
name (required), sendername, senderemail (validated email when non-empty). Hidden
campaignid + step.

### `classes/form/campaign_content_form.php` (new)
subject (required), body via `editor` element (HTML). On save, store the editor HTML in
`bodyhtml` and a plain-text version (`html_to_text`) in `bodytext`.

### `classes/form/campaign_audience_form.php` (reused)
Already exists from the audience-tags feature. The wizard sets/reads tags through
`audience_manager`.

### `classes/manager/campaign_manager.php` (new)
- `update_fields(int $campaignid, array $fields): void` — whitelist-updates allowed
  campaign columns, sets `timemodified`. Never writes arbitrary keys.
- `is_complete(int $campaignid): bool` — the completeness gate above.
- `mark_complete(int $campaignid): void` — guarded by `is_complete`; sets `status` to
  `ready` and `timemodified`. Throws if not complete.

### `lib.php` (changed)
Draft table: render the campaign name as a link to `campaign_edit.php`.

### `lang/en/local_mailwhistle.php` (changed)
Wizard step labels, form field labels/help, review checklist, complete button, status
`ready`, blocked-edit notice.

### `version.php` (changed)
Bump.

## Data

All fields live in `local_mailwhistle_campaigns` (already defined). Audience uses
`local_mailwhistle_audrules` via `audience_manager`. New status value `ready` is stored
in the existing `status` char column (no schema change).

## Error handling

- Invalid/missing `campaignid` -> `moodle_exception` via MUST_EXIST.
- Non-draft campaign -> read-only notice, no forms.
- Unknown `step` -> clamp to `details`.
- `require_sesskey()` on saves (moodleform handles).
- `mark_complete` refuses when `is_complete` is false (defensive; the button is also
  disabled in that state).
- Email field validated only when provided (sender email optional at draft stage).

## Testing

Unit test `tests/campaign_manager_test.php`:
- `update_fields` writes only whitelisted columns and bumps timemodified
- `update_fields` ignores non-whitelisted keys
- `is_complete` false when any required part missing; true when all present
- `mark_complete` sets status to `ready` when complete
- `mark_complete` throws when not complete

## Out of scope

- Actually sending / scheduling the campaign (sending engine is separate).
- Rich media / file attachments in the body.
- Cohort/role audience types (tags only, per the audience feature).
- Undo of `ready` back to `draft` (not needed for this iteration).
