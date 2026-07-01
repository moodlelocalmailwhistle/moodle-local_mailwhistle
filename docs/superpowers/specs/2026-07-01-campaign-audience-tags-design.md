# Campaign audience-tag selection

## Goal

After a user creates a campaign, send them to a new page where they choose which
audience tag(s) the campaign targets. Selection is stored as audience rules so the
campaign knows who it will be sent to.

## Flow

```
create.php (enter name)
  -> helper::create_campaign($name) returns $campaignid
  -> redirect to campaign_audience.php?campaignid=N

campaign_audience.php
  -> load campaign (MUST_EXIST, else moodle_exception)
  -> if no tags exist: show notice + link to Audience tab, no form
  -> else: render multi-select tag form, pre-checked with current selection
  -> on submit (with sesskey): audience_manager::set_campaign_tags(N, tagids)
  -> redirect to send tab with success notice
  -> on cancel: redirect to send tab
```

The page is bookmarkable and re-editable: visiting it again for the same campaign
shows the previously chosen tags checked.

## Data model

Existing table `local_mailwhistle_audrules`:

| Column | Use |
|--------|-----|
| campaignid | the campaign |
| type | literal `'tag'` for tag rules |
| instanceid | the tag id |
| roleid | unused for tag rules (0) |
| timecreated | insert time |

One row per selected tag. Save is idempotent: delete all `type='tag'` rows for the
campaign, then insert one row per selected tag. This is the first writer of this table.

## Components

### `campaign_audience.php` (new)
Entry page. `require_login()` + `require_capability('local/mailwhistle:manage')`.
Reads `campaignid` (PARAM_INT, MUST_EXIST). Handles empty-tag state, renders the form,
processes submit/cancel.

### `classes/form/campaign_audience_form.php` (new)
Moodle `moodleform`. Elements:
- hidden `campaignid` (PARAM_INT)
- `advcheckbox` group OR multi-select `select` (multiple) named `tagids[]`, options from
  `tag_manager::get_all_tags()` (id => format_string(name))
- standard submit/cancel

Uses the same tag-option pattern as the existing `audience_filter_form`.

### `classes/manager/audience_manager.php` (new)
Encapsulates audrules access so the page/form stay thin:
- `set_campaign_tags(int $campaignid, array $tagids): void` — validates each tag id
  exists in `local_mailwhistle_tag`, deletes existing `type='tag'` rows for the campaign,
  inserts the new set inside a delegated transaction.
- `get_campaign_tagids(int $campaignid): array` — returns the tag ids currently linked
  to the campaign (for pre-checking the form).

### `create.php` (changed)
Capture the return of `create_campaign()` and redirect to
`campaign_audience.php?campaignid=N` instead of straight to the send tab.

### `lang/en/local_mailwhistle.php` (changed)
New strings: page heading, form label/help, empty-state message + link text, success
notice, submit label.

### `version.php` (changed)
Bump `$plugin->version`.

## Error handling

- Invalid/missing `campaignid` -> `moodle_exception('invalidcampaign', ...)` via MUST_EXIST.
- `set_campaign_tags` skips/validates tag ids that do not exist (defensive; never trusts
  posted ids).
- `require_sesskey()` on the submit path (moodleform handles this).
- Empty selection is allowed (clears the campaign's audience) — not an error.

## Testing

Unit test `tests/audience_manager_test.php`:
- set then get roundtrip returns the same tag ids
- re-setting replaces (does not duplicate) rows
- setting `[]` clears all tag rows for the campaign
- invalid tag id is not written
- rows use `type='tag'` and correct `campaignid`

## Out of scope

- Cohort/role audience rule types (schema supports them; not built here).
- Actually sending to the audience (sending engine is separate work).
- Inline tag creation on this page (user creates tags on the Audience tab).
