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

use local_mailwhistle\manager\tracking_manager;
use local_mailwhistle\output\status_badge;

/**
 * table_sql listing the snapshotted recipients of one campaign.
 *
 * Opened/clicked flags are loaded for the current page only, matching the
 * audience table's per-page tag map.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recipient_table extends \table_sql {
    /** @var int Campaign whose recipients are listed. */
    protected int $campaignid;

    /** @var array<int, bool> Recipient ids that have an open event. */
    public array $openedmap = [];

    /** @var array<int, bool> Recipient ids that have a click event. */
    public array $clickedmap = [];

    /**
     * Initialise the recipient roster table.
     *
     * @param string $uniqueid Unique HTML id for this table instance.
     * @param \moodle_url $baseurl Base URL for pagination.
     * @param int $campaignid Campaign id.
     */
    public function __construct(string $uniqueid, \moodle_url $baseurl, int $campaignid) {
        parent::__construct($uniqueid);

        $this->campaignid = $campaignid;
        $this->define_baseurl($baseurl);
        $this->define_columns([
            'recipientname',
            'email',
            'status',
            'timesent',
            'opened',
            'clicked',
            'error',
        ]);
        $this->define_headers([
            get_string('col_user', 'local_mailwhistle'),
            get_string('col_email', 'local_mailwhistle'),
            get_string('col_status', 'local_mailwhistle'),
            get_string('col_sentat', 'local_mailwhistle'),
            get_string('col_opened', 'local_mailwhistle'),
            get_string('col_clicked', 'local_mailwhistle'),
            get_string('col_error', 'local_mailwhistle'),
        ]);
        $this->no_sorting('opened');
        $this->no_sorting('clicked');
        $this->no_sorting('error');
        $this->no_sorting('recipientname');
        $this->sortable(true, 'lastname', SORT_ASC);
        $this->is_downloadable(false);
    }

    /**
     * Apply the campaign-scoped recipient query.
     *
     * @return void
     */
    public function build_sql(): void {
        $fields = 'r.id, r.firstname, r.lastname, r.email, r.status, r.timesent, r.error';
        $this->set_sql(
            $fields,
            '{local_mailwhistle_recipients} r',
            'r.campaignid = :campaignid',
            ['campaignid' => $this->campaignid]
        );
    }

    /**
     * Fetch the page and the open/click map for those recipient ids.
     *
     * @param int $pagesize Rows per page.
     * @param bool $useinitialsbar Whether to render the initials bar.
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true): void {
        parent::query_db($pagesize, $useinitialsbar);

        $ids = array_map('intval', array_keys($this->rawdata ?? []));
        $maps = self::fetch_tracking_map($this->campaignid, $ids);
        $this->openedmap = $maps['opened'];
        $this->clickedmap = $maps['clicked'];
    }

    /**
     * Map recipient ids to open/click flags for one campaign page.
     *
     * @param int $campaignid Campaign id.
     * @param int[] $recipientids Recipient row ids on the current page.
     * @return array{opened: array<int, bool>, clicked: array<int, bool>}
     */
    public static function fetch_tracking_map(int $campaignid, array $recipientids): array {
        global $DB;

        $opened = [];
        $clicked = [];
        if (empty($recipientids)) {
            return ['opened' => $opened, 'clicked' => $clicked];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($recipientids, SQL_PARAMS_NAMED, 'rid');
        $params = $inparams + [
            'campaignid' => $campaignid,
        ];
        $sql = "SELECT recipientid, eventtype
                  FROM {local_mailwhistle_tracking}
                 WHERE campaignid = :campaignid
                   AND recipientid $insql";
        $rows = $DB->get_recordset_sql($sql, $params);
        foreach ($rows as $row) {
            $rid = (int) $row->recipientid;
            if ($row->eventtype === tracking_manager::EVENT_OPEN) {
                $opened[$rid] = true;
            } else if ($row->eventtype === tracking_manager::EVENT_CLICK) {
                $clicked[$rid] = true;
            }
        }
        $rows->close();

        return ['opened' => $opened, 'clicked' => $clicked];
    }

    /**
     * Snapshotted full name.
     *
     * @param \stdClass $row Recipient row.
     * @return string
     */
    public function col_recipientname(\stdClass $row): string {
        $name = trim($row->firstname . ' ' . $row->lastname);
        return format_string($name, true, ['context' => \context_system::instance()]);
    }

    /**
     * Snapshotted email.
     *
     * @param \stdClass $row Recipient row.
     * @return string
     */
    public function col_email(\stdClass $row): string {
        return s($row->email);
    }

    /**
     * Send status badge.
     *
     * @param \stdClass $row Recipient row.
     * @return string
     */
    public function col_status(\stdClass $row): string {
        global $PAGE;
        $renderer = $PAGE->get_renderer('local_mailwhistle');
        return $renderer->render(new status_badge((string) $row->status));
    }

    /**
     * Send time, or empty when still pending.
     *
     * @param \stdClass $row Recipient row.
     * @return string
     */
    public function col_timesent(\stdClass $row): string {
        $timesent = (int) $row->timesent;
        return $timesent > 0 ? userdate($timesent) : '';
    }

    /**
     * Whether this recipient opened the mail.
     *
     * @param \stdClass $row Recipient row.
     * @return string
     */
    public function col_opened(\stdClass $row): string {
        $opened = !empty($this->openedmap[(int) $row->id]);
        return $opened ? get_string('yes') : get_string('no');
    }

    /**
     * Whether this recipient clicked any tracked link.
     *
     * @param \stdClass $row Recipient row.
     * @return string
     */
    public function col_clicked(\stdClass $row): string {
        $clicked = !empty($this->clickedmap[(int) $row->id]);
        return $clicked ? get_string('yes') : get_string('no');
    }

    /**
     * Last send error, if any.
     *
     * @param \stdClass $row Recipient row.
     * @return string
     */
    public function col_error(\stdClass $row): string {
        return s((string) $row->error);
    }
}
