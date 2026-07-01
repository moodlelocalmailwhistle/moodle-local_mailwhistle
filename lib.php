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

/**
 * Local plugin "Mail Whistle" - Main library file.
 *
 * This file contains hook implementations and callable functions for the plugin.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin installation callback.
 *
 * Runs once during initial plugin installation.
 * Use this to set up initial data, database records, or configuration.
 *
 * @return void
 */
function local_mailwhistle_install(): void {
    // Add installation logic here if needed.
}

/**
 * Plugin upgrade callback.
 *
 * Runs when the plugin is upgraded to a new version.
 * Database schema changes should be handled in db/upgrade.php file.
 *
 * @param int $oldversion The previous plugin version code.
 * @return bool True if successful, false otherwise.
 */
function local_mailwhistle_upgrade(int $oldversion): bool {
    // Database upgrades are handled in db/upgrade.php.
    // This function can handle non-database upgrade tasks if needed.
    return true;
}

/**
 * Plugin uninstall callback.
 *
 * Runs when the plugin is being uninstalled.
 * Clean up plugin-specific data, files, and configuration.
 *
 * @return bool True if successful, false otherwise.
 */
function local_mailwhistle_uninstall(): bool {
    // Add cleanup logic here if needed.
    // Note: Database tables are automatically dropped after this runs.
    return true;
}

/**
 * Hook to extend the global site navigation.
 *
 * Adds navigation items to the main navigation menu.
 * Check user capabilities before adding sensitive menu items.
 *
 * @param global_navigation $navigation The global navigation object.
 * @return void
 */
function local_mailwhistle_extend_navigation(global_navigation $navigation): void {
    // Add plugin navigation nodes here when required, after a capability check.
}

/**
 * Hook to extend course-specific navigation.
 *
 * Adds course-related menu items when viewing a course.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @return void
 */
function local_mailwhistle_extend_navigation_course(navigation_node $navigation, stdClass $course): void {
    // Add course-specific navigation items here if needed.
}

/**
 * Hook to extend user-specific navigation.
 *
 * Adds user profile menu items when viewing a user profile.
 *
 * @param navigation_node $navigation The user navigation node.
 * @param stdClass $user The user object.
 * @return void
 */
function local_mailwhistle_extend_navigation_user(navigation_node $navigation, stdClass $user): void {
    // Add user-specific navigation items here if needed.
}

/**
 * Hook called after every page is initialized.
 *
 * Use this to add global CSS, JavaScript, or make page modifications.
 * This hook runs very early in page initialization.
 *
 * @return void
 */
function local_mailwhistle_page_init(): void {
    global $PAGE;
    // Add custom CSS or JavaScript requirements for plugin pages here when required.
}

/**
 * Render the "previously sent newsletters" history table.
 *
 * Builds a table from sample data so the send-newsletters tab has something
 * to display before the real sending engine and data model land. Replace the
 * data source in {@see local_mailwhistle_get_sample_sent_mails()} with a query
 * against the mailings table once persistence is implemented.
 *
 * @return string Rendered HTML for the sent mails section.
 */
function local_mailwhistle_render_sent_mails(): string {
    $mails = local_mailwhistle_get_sample_sent_mails();

    $rows = [];
    foreach ($mails as $mail) {
        $viewurl = new moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'send',
            'view' => $mail['id'],
        ]);
        $rows[] = [
            html_writer::link($viewurl, format_string($mail['subject'])),
            format_string($mail['audience']),
            number_format($mail['recipients']),
            format_string($mail['sentby']),
            userdate($mail['sentat']),
            local_mailwhistle_status_badge($mail['status']),
        ];
    }

    return local_mailwhistle_render_campaign_table(
        get_string('sentmails_heading', 'local_mailwhistle'),
        get_string('nosentmails', 'local_mailwhistle'),
        [
            get_string('col_subject', 'local_mailwhistle'),
            get_string('col_audience', 'local_mailwhistle'),
            get_string('col_recipients', 'local_mailwhistle'),
            get_string('col_sentby', 'local_mailwhistle'),
            get_string('col_sentat', 'local_mailwhistle'),
            get_string('col_status', 'local_mailwhistle'),
        ],
        $rows,
        'local-mailwhistle-sentmails'
    );
}

/**
 * Maximum number of draft campaigns shown in the send-tab draft section.
 */
const LOCAL_MAILWHISTLE_DRAFT_LIMIT = 50;

/**
 * Render a heading plus a data table (or an empty-state notice) as one block.
 *
 * Shared scaffolding for the sent-newsletters and draft-campaigns sections so
 * their heading, empty state and table markup stay consistent.
 *
 * @param string $heading Section heading text (already localised).
 * @param string $emptymessage Empty-state message (already localised).
 * @param array<int, string> $head Column header cells (already localised).
 * @param array<int, array<int, string>> $rows Row data (each an array of cell HTML).
 * @param string $tableclass Extra CSS class(es) for the table element.
 * @return string Rendered HTML for the section.
 */
function local_mailwhistle_render_campaign_table(
    string $heading,
    string $emptymessage,
    array $head,
    array $rows,
    string $tableclass
): string {
    $output = html_writer::tag('h3', $heading);

    if (empty($rows)) {
        return $output . html_writer::div($emptymessage, 'alert alert-info');
    }

    $table = new html_table();
    $table->head = $head;
    $table->attributes['class'] = 'generaltable ' . $tableclass;
    $table->data = $rows;

    return $output . html_writer::table($table);
}

/**
 * Render the draft campaigns section for the send-newsletters tab.
 *
 * Lists the most recent draft campaigns so managers can see what has been
 * created but not yet sent. Bounded to {@see LOCAL_MAILWHISTLE_DRAFT_LIMIT}
 * rows and only the displayed columns to keep the default page fast.
 *
 * @return string Rendered HTML for the draft campaigns section.
 */
function local_mailwhistle_render_draft_campaigns(): string {
    global $DB;

    $drafts = $DB->get_records(
        'local_email_campaigns',
        ['status' => 'draft'],
        'timecreated DESC',
        'id, name, status, timecreated',
        0,
        LOCAL_MAILWHISTLE_DRAFT_LIMIT
    );

    $rows = [];
    foreach ($drafts as $draft) {
        $rows[] = [
            format_string($draft->name),
            local_mailwhistle_status_badge($draft->status),
            userdate($draft->timecreated),
        ];
    }

    $section = local_mailwhistle_render_campaign_table(
        get_string('draftcampaigns_heading', 'local_mailwhistle'),
        get_string('nodraftcampaigns', 'local_mailwhistle'),
        [
            get_string('col_name', 'local_mailwhistle'),
            get_string('col_status', 'local_mailwhistle'),
            get_string('col_created', 'local_mailwhistle'),
        ],
        $rows,
        'local-mailwhistle-drafts-table'
    );

    return html_writer::div($section, 'local-mailwhistle-drafts');
}

/**
 * Render the detail view for a single sent newsletter.
 *
 * Shows the metadata and a preview of the rendered newsletter body. Falls back
 * to a not-found notice and the list link when the id does not match a record.
 *
 * @param int $id The sample mail id to view.
 * @return string Rendered HTML for the detail view.
 */
function local_mailwhistle_render_view_mail(int $id): string {
    $mail = local_mailwhistle_get_sample_sent_mail($id);

    $listurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'send']);
    $backlink = html_writer::div(
        html_writer::link($listurl, get_string('backtolist', 'local_mailwhistle')),
        'local-mailwhistle-back mb-3'
    );

    if ($mail === null) {
        return $backlink . html_writer::div(
            get_string('mailnotfound', 'local_mailwhistle'),
            'alert alert-warning'
        );
    }

    $output = $backlink;
    $output .= html_writer::tag('h3', format_string($mail['subject']));

    // Metadata summary table.
    $meta = new html_table();
    $meta->attributes['class'] = 'generaltable local-mailwhistle-mailmeta';
    $meta->data = [
        [get_string('col_audience', 'local_mailwhistle'), format_string($mail['audience'])],
        [get_string('col_recipients', 'local_mailwhistle'), number_format($mail['recipients'])],
        [get_string('col_sentby', 'local_mailwhistle'), format_string($mail['sentby'])],
        [get_string('col_sentat', 'local_mailwhistle'), userdate($mail['sentat'])],
        [get_string('col_status', 'local_mailwhistle'), local_mailwhistle_status_badge($mail['status'])],
    ];
    $output .= html_writer::table($meta);

    // Newsletter body preview. Sample bodies are trusted plugin content, so the
    // limited HTML is allowed through format_text() with no cleaning.
    $output .= html_writer::tag('h4', get_string('mailpreview', 'local_mailwhistle'));
    $bodyhtml = format_text($mail['body'], FORMAT_HTML, ['noclean' => true]);
    $output .= html_writer::div($bodyhtml, 'local-mailwhistle-mailbody card card-body');

    return $output;
}

/**
 * Provide sample sent-newsletter rows for the placeholder history table.
 *
 * Each row mirrors the shape expected from the future mailings table so the
 * rendering code does not need to change when real data is wired in.
 *
 * @return array<int, array<string, mixed>> List of sample sent mail records.
 */
function local_mailwhistle_get_sample_sent_mails(): array {
    // Fixed timestamps (UTC) keep the sample output stable across requests.
    return [
        [
            'id' => 1,
            'subject' => 'Welcome to the Autumn term',
            'audience' => 'All enrolled students',
            'recipients' => 1248,
            'sentby' => 'Admin User',
            'sentat' => 1725192000, // 2024-09-01 12:00 UTC.
            'status' => 'sent',
            'body' => '<h1>Welcome back!</h1>'
                . '<p>Dear student, the Autumn term starts on <strong>2 September</strong>. '
                . 'Your courses are now visible on your dashboard.</p>'
                . '<p>We wish you a great term ahead.</p>'
                . '<p>Kind regards,<br>The Mailwhistle Team</p>',
        ],
        [
            'id' => 2,
            'subject' => 'New course catalogue available',
            'audience' => 'Active learners',
            'recipients' => 873,
            'sentby' => 'Marketing Team',
            'sentat' => 1727784000, // 2024-10-01 12:00 UTC.
            'status' => 'sent',
            'body' => '<h1>Fresh courses, just for you</h1>'
                . '<p>Our new catalogue is live. Explore over 40 new courses across '
                . 'science, languages and the arts.</p>'
                . '<p><a href="#">Browse the catalogue &raquo;</a></p>',
        ],
        [
            'id' => 3,
            'subject' => 'Reminder: assignment deadline this Friday',
            'audience' => 'Biology 101 cohort',
            'recipients' => 64,
            'sentby' => 'Jane Teacher',
            'sentat' => 1730462400, // 2024-11-01 12:00 UTC.
            'status' => 'sending',
            'body' => '<h1>Deadline approaching</h1>'
                . '<p>This is a friendly reminder that your <strong>Cell Biology essay</strong> '
                . 'is due this Friday at 23:59.</p>'
                . '<p>Submit via the assignment activity in your course.</p>',
        ],
        [
            'id' => 4,
            'subject' => 'December newsletter (draft)',
            'audience' => 'Newsletter subscribers',
            'recipients' => 2105,
            'sentby' => 'Marketing Team',
            'sentat' => 1733054400, // 2024-12-01 12:00 UTC.
            'status' => 'scheduled',
            'body' => '<h1>What happened in December</h1>'
                . '<p>A round-up of the month: new features, community highlights and '
                . 'upcoming events for the new year.</p>',
        ],
        [
            'id' => 5,
            'subject' => 'Platform maintenance notice',
            'audience' => 'All users',
            'recipients' => 3490,
            'sentby' => 'Admin User',
            'sentat' => 1735732800, // 2025-01-01 12:00 UTC.
            'status' => 'failed',
            'body' => '<h1>Scheduled maintenance</h1>'
                . '<p>The platform will be unavailable on Sunday between 02:00 and 04:00 UTC '
                . 'for scheduled maintenance.</p>'
                . '<p>We apologise for any inconvenience.</p>',
        ],
    ];
}

/**
 * Find a single sample sent-newsletter record by its id.
 *
 * @param int $id The sample mail id.
 * @return array<string, mixed>|null The matching record, or null if not found.
 */
function local_mailwhistle_get_sample_sent_mail(int $id): ?array {
    foreach (local_mailwhistle_get_sample_sent_mails() as $row) {
        if ((int) $row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

/**
 * Render a coloured status badge for a sent newsletter.
 *
 * @param string $status One of: draft, sent, sending, scheduled, failed.
 * @return string Rendered badge HTML.
 */
function local_mailwhistle_status_badge(string $status): string {
    $classes = [
        'draft' => 'badge badge-secondary bg-secondary text-white',
        'sent' => 'badge badge-success bg-success text-white',
        'sending' => 'badge badge-info bg-info text-white',
        'scheduled' => 'badge badge-secondary bg-secondary text-white',
        'failed' => 'badge badge-danger bg-danger text-white',
    ];
    $class = $classes[$status] ?? 'badge badge-secondary bg-secondary text-white';

    return html_writer::span(
        get_string('status_' . $status, 'local_mailwhistle'),
        $class
    );
}

/**
 * Render the "previously sent newsletters" history table.
 *
 * Builds a table from sample data so the send-newsletters tab has something
 * to display before the real sending engine and data model land. Replace the
 * data source in {@see local_mailwhistle_get_sample_sent_mails()} with a query
 * against the mailings table once persistence is implemented.
 *
 * @return string Rendered HTML for the sent mails section.
 */
function local_mailwhistle_render_resources(): string {
    return '';
}
