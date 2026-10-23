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

namespace local_mailwhistle\manager;

/**
 * Authorised, rate-limited test copies of saved campaigns.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_email_manager {
    /** @var int Maximum distinct destinations per submission. */
    public const MAX_ADDRESSES = 5;

    /** @var int Maximum destination attempts in a rolling hour, across campaigns and sessions. */
    public const HOURLY_LIMIT = 20;

    /** @var string User preference storing recent attempt timestamps, without destination addresses. */
    public const RATE_PREFERENCE = 'local_mailwhistle_testemail_attempts';

    /**
     * Validate the whole list before sending anything, preserving the first spelling of each address.
     *
     * @param string $input Comma, semicolon, or newline-separated addresses.
     * @return string[] Unique email addresses.
     */
    public static function parse_addresses(string $input): array {
        $addresses = [];
        if (strlen($input) > 5000) {
            throw new \moodle_exception('testmail_toomany', 'local_mailwhistle', '', self::MAX_ADDRESSES);
        }
        foreach (preg_split('/[,;\r\n]+/', $input, -1, PREG_SPLIT_NO_EMPTY) as $address) {
            $address = trim($address);
            if ($address === '') {
                continue;
            }
            if (strlen($address) > 254 || !validate_email($address)) {
                throw new \moodle_exception('testmail_invalidaddresses', 'local_mailwhistle');
            }
            $addresses[\core_text::strtolower($address)] ??= $address;
        }
        if (!$addresses) {
            throw new \moodle_exception('testmail_invalidaddresses', 'local_mailwhistle');
        }
        if (count($addresses) > self::MAX_ADDRESSES) {
            throw new \moodle_exception('testmail_toomany', 'local_mailwhistle', '', self::MAX_ADDRESSES);
        }
        return array_values($addresses);
    }

    /**
     * Read persisted recent attempts rather than a potentially stale session preference cache.
     *
     * @param int $userid Current reviewer id.
     * @return int[] Timestamps for attempts within the last hour.
     */
    private static function recent_attempts(int $userid): array {
        global $DB;

        $value = $DB->get_field('user_preferences', 'value', ['userid' => $userid, 'name' => self::RATE_PREFERENCE]);
        $attempts = json_decode((string) $value, true);
        $cutoff = time() - HOURSECS;
        return is_array($attempts) ? array_values(array_filter($attempts, static function ($timestamp) use ($cutoff): bool {
            return is_int($timestamp) && $timestamp > $cutoff;
        })) : [];
    }

    /**
     * Return a useful validation message when the current reviewer has exhausted the allowance.
     *
     * This check is advisory; send() repeats it while holding the reviewer lock.
     *
     * @param int $count Number of requested destinations.
     * @return string Empty when the request fits within the allowance.
     */
    public static function get_limit_error(int $count): string {
        global $USER;

        return count(self::recent_attempts((int) $USER->id)) + $count > self::HOURLY_LIMIT
            ? get_string('testmail_limit', 'local_mailwhistle', self::HOURLY_LIMIT) : '';
    }

    /**
     * Send saved content using the current manager as sample personalisation data.
     *
     * Locks protect both the persistent allowance and a consistent campaign snapshot.
     * Every attempted destination consumes allowance, including uncertain transport results.
     * Callers handling HTTP requests must also enforce the request's session key.
     *
     * @param int $campaignid Saved campaign id.
     * @param string $input Address list.
     * @return array[] One explicit result per unique requested address.
     */
    public static function send(int $campaignid, string $input): array {
        global $DB, $USER;

        require_capability('local/mailwhistle:manage', \context_system::instance());
        $addresses = self::parse_addresses($input);
        $factory = \core\lock\lock_config::get_lock_factory('local_mailwhistle');
        $userlock = $factory->get_lock('test-email:' . $USER->id, 10);
        if (!$userlock) {
            throw new \moodle_exception('testmail_busy', 'local_mailwhistle');
        }
        $campaignlock = null;
        try {
            if (self::get_limit_error(count($addresses)) !== '') {
                throw new \moodle_exception('testmail_limit', 'local_mailwhistle', '', self::HOURLY_LIMIT);
            }
            $campaignlock = $factory->get_lock('campaign:' . $campaignid, 10);
            if (!$campaignlock) {
                throw new \moodle_exception('campaignbusy', 'local_mailwhistle');
            }
            $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
            $reviewer = $DB->get_record('user', ['id' => $USER->id], '*', MUST_EXIST);
            $attempts = array_merge(self::recent_attempts((int) $reviewer->id), array_fill(0, count($addresses), time()));
            set_user_preference(self::RATE_PREFERENCE, json_encode($attempts), $reviewer->id);

            $results = [];
            foreach ($addresses as $address) {
                $results[] = send_manager::send_test_to_address($campaign, $reviewer, $address);
            }
            return $results;
        } finally {
            if ($campaignlock) {
                $campaignlock->release();
            }
            $userlock->release();
        }
    }
}
