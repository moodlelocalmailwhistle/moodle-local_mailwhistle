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

namespace local_mailwhistle;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use local_mailwhistle\manager\tracking_manager;
use local_mailwhistle\privacy\provider;

/**
 * Tracking tokens must not restore erased recipient data.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\tracking_manager
 * @covers    \local_mailwhistle\manager\recipient_manager
 * @covers    \local_mailwhistle\privacy\provider
 * @covers    \local_mailwhistle\observer
 */
final class tracking_privacy_test extends \advanced_testcase {
    /**
     * Allow external user-deletion observers to see actual commits.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
    }

    /**
     * Every erasure API must revoke recording without invalidating old redirects.
     *
     * @dataProvider erasure_provider
     * @param string $mode Erasure API to exercise.
     */
    public function test_erased_recipient_cannot_be_recreated(string $mode): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $author = $this->getDataGenerator()->create_user();
        $subject = $this->getDataGenerator()->create_user();
        $bystander = $this->getDataGenerator()->create_user();
        $campaign = $generator->create_campaign(['createdby' => $author->id, 'status' => 'sending']);
        $recipient = $generator->create_recipient(['campaignid' => $campaign->id, 'userid' => $subject->id]);
        $othercampaign = $generator->create_campaign();
        $other = $generator->create_recipient(['campaignid' => $othercampaign->id, 'userid' => $bystander->id]);
        $target = 'https://example.com/?email=' . rawurlencode($subject->email);
        $opentoken = tracking_manager::make_open_token($campaign->id, $recipient->id);
        $clicktoken = tracking_manager::make_click_token($campaign->id, $recipient->id, $target);
        $this->assertTrue(tracking_manager::record_open($campaign->id, $recipient->id));
        $this->assertTrue(tracking_manager::record_click($campaign->id, $recipient->id, $target));
        $this->assertTrue(tracking_manager::record_open($othercampaign->id, $other->id));
        helper::log_send($campaign->id, $recipient->id, 'success', 'Sent');

        $context = \context_system::instance();
        switch ($mode) {
            case 'user':
            case 'author':
                $user = $mode === 'user' ? $subject : $author;
                provider::delete_data_for_user(new approved_contextlist($user, 'local_mailwhistle', [$context->id]));
                break;
            case 'users':
            case 'authors':
                $userid = $mode === 'users' ? $subject->id : $author->id;
                provider::delete_data_for_users(new approved_userlist($context, 'local_mailwhistle', [$userid]));
                break;
            case 'all':
                provider::delete_data_for_all_users_in_context($context);
                break;
            case 'account':
                delete_user($subject);
                break;
            case 'accounttransaction':
                $transaction = $DB->start_delegated_transaction();
                delete_user($subject);
                // Cleanup must wait for the surrounding transaction to actually commit.
                $this->assertTrue($DB->record_exists('local_mailwhistle_recipients', ['id' => $recipient->id]));
                $transaction->allow_commit();
                break;
        }

        $this->assertFalse($DB->record_exists('local_mailwhistle_recipients', ['id' => $recipient->id]));
        $this->assertFalse($DB->record_exists('local_mailwhistle_tracking', ['recipientid' => $recipient->id]));
        $this->assertFalse($DB->record_exists('local_mailwhistle_sendlogs', ['recipientid' => $recipient->id]));

        // These are the same verification and recording calls made by the public endpoints.
        $open = tracking_manager::verify_open_token($opentoken);
        $click = tracking_manager::verify_click_token($clicktoken, $target);
        $this->assertNotNull($open);
        $this->assertNotNull($click);
        $this->assertFalse(tracking_manager::record_open($open->campaignid, $open->recipientid));
        $this->assertFalse(tracking_manager::record_click($click->campaignid, $click->recipientid, $target));
        $this->assertFalse($DB->record_exists('local_mailwhistle_tracking', ['recipientid' => $recipient->id]));
        $this->assertNotContains($target, $DB->get_fieldset_select('local_mailwhistle_tracking', 'targeturl', '1 = 1'));
        if ($mode !== 'all') {
            $this->assertTrue(tracking_manager::record_open($othercampaign->id, $other->id));
            $this->assertTrue(tracking_manager::record_click($othercampaign->id, $other->id, 'https://example.com/other'));
        }
    }

    /**
     * Deletion entry points, including campaign ownership and an outer transaction.
     *
     * @return array
     */
    public static function erasure_provider(): array {
        return array_combine(
            ['user', 'users', 'all', 'author', 'authors', 'account', 'accounttransaction'],
            [['user'], ['users'], ['all'], ['author'], ['authors'], ['account'], ['accounttransaction']]
        );
    }

    /**
     * Missing or mismatched subjects cannot acquire new tracking data.
     *
     * @dataProvider invalid_subject_provider
     * @param string $mode Invalid relationship to exercise.
     */
    public function test_recording_requires_a_live_matching_subject(string $mode): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $user = $this->getDataGenerator()->create_user();
        $campaign = $generator->create_campaign();
        $recipient = $generator->create_recipient(['campaignid' => $campaign->id, 'userid' => $user->id]);
        $campaignid = $campaign->id;
        switch ($mode) {
            case 'recipient':
                $DB->delete_records('local_mailwhistle_recipients', ['id' => $recipient->id]);
                break;
            case 'campaign':
                $DB->delete_records('local_mailwhistle_campaigns', ['id' => $campaign->id]);
                break;
            case 'mismatch':
                $campaignid = $generator->create_campaign()->id;
                break;
            case 'user':
                $DB->delete_records('user', ['id' => $user->id]);
                break;
            case 'deleteduser':
                $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);
                break;
        }
        $this->assertFalse(tracking_manager::record_open($campaignid, $recipient->id));
        $this->assertFalse(tracking_manager::record_click($campaignid, $recipient->id, 'https://example.com/'));
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tracking'));
    }

    /**
     * Invalid subject relationships.
     *
     * @return array
     */
    public static function invalid_subject_provider(): array {
        return [
            'missing recipient' => ['recipient'],
            'missing campaign' => ['campaign'],
            'mismatched campaign' => ['mismatch'],
            'missing user' => ['user'],
            'deleted user' => ['deleteduser'],
        ];
    }

    /**
     * Erasure contention skips recording, and released locks permit later requests.
     */
    public function test_recording_respects_recipient_erasure_lock(): void {
        global $CFG, $DB;

        $CFG->lock_factory = \core\lock\file_lock_factory::class;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $user = $this->getDataGenerator()->create_user();
        $campaign = $generator->create_campaign(['status' => 'sending']);
        $recipient = $generator->create_recipient([
            'campaignid' => $campaign->id, 'userid' => $user->id, 'status' => 'uncertain',
        ]);
        $factory = \core\lock\lock_config::get_lock_factory('local_mailwhistle_tracking');
        $lock = $factory->get_lock('recipient:' . $recipient->id, 0);
        $this->assertNotFalse($lock);
        try {
            $this->assertFalse(tracking_manager::record_open($campaign->id, $recipient->id));
            $this->assertFalse(tracking_manager::record_click($campaign->id, $recipient->id, 'https://example.com'));
            $this->assertSame(0, $DB->count_records('local_mailwhistle_tracking'));
        } finally {
            $lock->release();
        }
        $this->assertTrue(tracking_manager::record_open($campaign->id, $recipient->id));
        $this->assertTrue(tracking_manager::record_click($campaign->id, $recipient->id, 'https://example.com'));
        $this->assertSame(2, $DB->count_records('local_mailwhistle_tracking'));
    }
}
