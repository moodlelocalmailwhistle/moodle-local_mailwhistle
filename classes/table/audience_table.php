<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mailwhistle\table;

use local_mailwhistle\manager\tag_manager;

/**
 * table_sql subclass that renders the audience listing for Mail Whistle.
 *
 * Columns: select (checkbox, canmanage only), Full name, Email, Tags.
 * Tags are fetched in a second per-page query inside query_db() so the
 * main pager COUNT stays a clean per-user count (no GROUP BY fan-out).
 *
 * The outer POST form is owned by index.php.  This class deliberately
 * does NOT override wrap_html_start / wrap_html_finish so no second
 * <form> element is emitted (AC#15).
 *
 * col_tags renders tag chips with remove links when the viewer has
 * :managetags.  The tagmap stores objects with (id, name) so that
 * remove links can include the tagid.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience_table extends \table_sql {
    /** @var bool Whether the current viewer has local/mailwhistle:managetags. */
    protected bool $canmanage;

    /** @var array Active filter values (search, tagid, suspended, auth). */
    protected array $filters;

    /**
     * Per-page tag map: userid => array of stdClass {id, name}.
     *
     * Populated in query_db() immediately after the parent fetches $rawdata.
     * Read in col_tags() to render tag chips + remove links.
     *
     * @var array<int, \stdClass[]>
     */
    public array $tagmap = [];

    /**
     * Initialise the audience table.
     *
     * @param string      $uniqueid Unique HTML id for this table instance.
     * @param \moodle_url $baseurl  Base URL carrying active filter params for pagination.
     * @param array       $filters  Associative array: search, tagid, suspended, auth.
     * @param bool        $canmanage Whether the current viewer has :managetags.
     */
    public function __construct(string $uniqueid, \moodle_url $baseurl, array $filters, bool $canmanage) {
        parent::__construct($uniqueid);

        $this->canmanage = $canmanage;
        $this->filters   = $filters;

        $this->define_baseurl($baseurl);

        // Only include the checkbox column when the viewer can actually manage tags.
        if ($canmanage) {
            $this->define_columns(['select', 'fullname', 'email', 'tags']);
            $this->define_headers([
                '', // Checkbox column — no sortable header text needed.
                get_string('col_user', 'local_mailwhistle'),
                get_string('col_email', 'local_mailwhistle'),
                get_string('col_tags', 'local_mailwhistle'),
            ]);
        } else {
            $this->define_columns(['fullname', 'email', 'tags']);
            $this->define_headers([
                get_string('col_user', 'local_mailwhistle'),
                get_string('col_email', 'local_mailwhistle'),
                get_string('col_tags', 'local_mailwhistle'),
            ]);
        }

        // Select and Tags columns are display-only — no sort.
        if ($canmanage) {
            $this->no_sorting('select');
        }
        $this->no_sorting('tags');

        // Default sort: lastname ASC, firstname ASC (deterministic).
        $this->sortable(true, 'lastname', SORT_ASC);

        // Disable CSV/Excel download — bulk PII export requires a separate
        // capability + audit decision (D7).
        $this->is_downloadable(false);
    }

    /**
     * Build and apply the SQL query for this table.
     *
     * Uses \core_user\fields::for_name() to select all name fields in a
     * portable way, configured so it does NOT emit an id column (avoiding
     * the duplicate-id SQL collision).  u.id AS id leads the field list so
     * $this->rawdata is keyed by userid.
     *
     * @return void
     */
    public function build_sql(): void {
        global $CFG, $DB;

        // Name fields — for_name() with $leadingcomma=false so we control
        // the separator; it does NOT select id when called this way.
        // Signature: get_sql(tableprefix, useid, fieldprefix, fieldalias, leadingcomma).
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);

        // Lead with u.id AS id (keys rawdata by userid), then email, then
        // all name parts from for_name().  $namefields->selects does not
        // start with a comma when leadingcomma=false, so we add one.
        $fields = 'u.id AS id, u.email, ' . $namefields->selects;
        $from   = '{user} u' . $namefields->joins;
        $params = $namefields->params ?? [];

        // Baseline WHERE: exclude deleted users and the guest account.
        $whereclauses = [
            'u.deleted = 0',
            'u.id <> :guestid',
        ];
        $params['guestid'] = $CFG->siteguest;

        // Optional filter: name / email search.
        $search = trim($this->filters['search'] ?? '');
        if ($search !== '') {
            $whereclauses[] = '('
                . $DB->sql_like('u.firstname', ':s1', false)
                . ' OR '
                . $DB->sql_like('u.lastname', ':s2', false)
                . ' OR '
                . $DB->sql_like('u.email', ':s3', false)
                . ')';
            $escaped = '%' . $DB->sql_like_escape($search) . '%';
            $params['s1'] = $escaped;
            $params['s2'] = $escaped;
            $params['s3'] = $escaped;
        }

        // Optional filter: tag (EXISTS subquery).
        $tagid = (int) ($this->filters['tagid'] ?? 0);
        if ($tagid > 0) {
            $whereclauses[] = 'EXISTS ('
                . 'SELECT 1'
                . ' FROM {local_mailwhistle_tag_assign} ta'
                . ' WHERE ta.userid = u.id'
                . ' AND ta.tagid = :filtertagid'
                . ')';
            $params['filtertagid'] = $tagid;
        }

        // Optional filter: suspended status.
        $suspended = $this->filters['suspended'] ?? '';
        if ($suspended === 'active') {
            $whereclauses[] = 'u.suspended = :susp';
            $params['susp'] = 0;
        } else if ($suspended === 'suspended') {
            $whereclauses[] = 'u.suspended = :susp';
            $params['susp'] = 1;
        }

        // Optional filter: authentication plugin.
        $auth = $this->filters['auth'] ?? '';
        if ($auth !== '' && $auth !== 'any') {
            $whereclauses[] = 'u.auth = :auth';
            $params['auth'] = $auth;
        }

        $this->set_sql($fields, $from, implode(' AND ', $whereclauses), $params);
    }

    /**
     * Fetch the current page of users and immediately build the per-page
     * tag map so col_tags() can render tag chips without additional queries.
     *
     * The tag map is an array of (id, name) objects keyed by userid, fetched
     * via a single JOIN query after the parent populates $this->rawdata.
     *
     * @param int  $pagesize      Rows per page.
     * @param bool $useinitialsbar Whether to render the initials bar.
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true): void {
        parent::query_db($pagesize, $useinitialsbar);

        $userids = array_keys($this->rawdata);
        $this->tagmap = self::fetch_tagmap($userids);
    }

    /**
     * Fetch a map of userid => [{id, name}, …] for the given user ids.
     *
     * Kept as a separate static helper so tests can call it directly.
     *
     * @param int[] $userids
     * @return array
     */
    public static function fetch_tagmap(array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        $sql = "SELECT ta.id AS assignid, ta.userid, ta.tagid, t.name
                  FROM {local_mailwhistle_tag_assign} ta
                  JOIN {local_mailwhistle_tag} t ON t.id = ta.tagid
                 WHERE ta.userid $insql
              ORDER BY t.name ASC";

        $rows = $DB->get_records_sql($sql, $inparams);

        $map = [];
        foreach ($rows as $row) {
            $uid = (int) $row->userid;
            if (!isset($map[$uid])) {
                $map[$uid] = [];
            }
            // Store (tagid, name) objects so col_tags can render remove links.
            $map[$uid][] = (object) [
                'id'   => (int) $row->tagid,
                'name' => $row->name,
            ];
        }

        return $map;
    }

    /**
     * Render the checkbox cell for bulk tag-apply.
     *
     * Only reachable when $canmanage is true (the select column is only
     * defined in that case).
     *
     * @param \stdClass $row Current row from rawdata.
     * @return string HTML checkbox input.
     */
    public function col_select(\stdClass $row): string {
        return \html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'userids[]', 'value' => (int) $row->id]);
    }

    /**
     * Render the Full name cell.
     *
     * Signature intentionally omits the parameter type hint and return type so
     * it stays compatible with the untyped parent core_table\flexible_table::col_fullname($row).
     *
     * @param \stdClass $row Current row.
     * @return string Formatted full name.
     */
    public function col_fullname($row) {
        return fullname($row);
    }

    /**
     * Render the Email cell (XSS-safe).
     *
     * @param \stdClass $row Current row.
     * @return string Escaped email address.
     */
    public function col_email(\stdClass $row): string {
        return s($row->email);
    }

    /**
     * Render the Tags cell.
     *
     * Displays tag chips using format_string() for XSS safety.  When the
     * viewer has :managetags each chip includes a "remove" link that posts
     * action=removetag with the sesskey so the PRG handler in index.php can
     * process it before any output.
     *
     * @param \stdClass $row Current row.
     * @return string HTML tag chips (and optional remove links).
     */
    public function col_tags(\stdClass $row): string {
        global $OUTPUT;
        $chips = $this->tagmap[(int) $row->id] ?? [];

        if (empty($chips)) {
            return get_string('no_tags', 'local_mailwhistle');
        }

        $parts = [];
        foreach ($chips as $chip) {
            $label = format_string($chip->name);

            if ($this->canmanage) {
                $removeurl = new \moodle_url('/local/mailwhistle/index.php', [
                    'tab'     => 'audience',
                    'action'  => 'removetag',
                    'tagid'   => $chip->id,
                    'userid'  => (int) $row->id,
                    'sesskey' => sesskey(),
                ]);

                $removelink = \html_writer::link(
                    $removeurl,
                    $OUTPUT->pix_icon('t/delete', get_string('remove_tag', 'local_mailwhistle')),
                    ['class' => 'mw-tag-remove']
                );
                $parts[] = \html_writer::span($label . ' ' . $removelink, 'badge bg-secondary mw-tag-chip');
            } else {
                $parts[] = \html_writer::span($label, 'badge bg-secondary mw-tag-chip');
            }
        }

        return implode(' ', $parts);
    }
}
