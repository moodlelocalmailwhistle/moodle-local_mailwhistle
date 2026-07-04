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
 * @param array $head Column header cells (already localised).
 * @param array $rows Row data (each an array of cell HTML).
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
    global $DB, $OUTPUT;

    // Show campaigns still being prepared (draft) or ready to send (ready).
    [$insql, $inparams] = $DB->get_in_or_equal(['draft', 'ready'], SQL_PARAMS_NAMED, 'st');
    $drafts = $DB->get_records_select(
        'local_mailwhistle_campaigns',
        "status $insql",
        $inparams,
        'timecreated DESC',
        'id, name, status, timecreated',
        0,
        LOCAL_MAILWHISTLE_DRAFT_LIMIT
    );

    $rows = [];
    foreach ($drafts as $draft) {
        $editurl = new moodle_url('/local/mailwhistle/campaign_edit.php', ['campaignid' => $draft->id]);

        // A ready campaign gets a "Send now" button (POST, sesskey-guarded).
        $action = '';
        if ($draft->status === 'ready') {
            $sendurl = new moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'send',
                'action' => 'sendnow',
                'campaignid' => $draft->id,
            ]);
            $action = $OUTPUT->single_button($sendurl, get_string('sendnow', 'local_mailwhistle'), 'post');
        }

        $rows[] = [
            html_writer::link($editurl, format_string($draft->name)),
            local_mailwhistle_status_badge($draft->status),
            userdate($draft->timecreated),
            $action,
        ];
    }

    $section = local_mailwhistle_render_campaign_table(
        get_string('draftcampaigns_heading', 'local_mailwhistle'),
        get_string('nodraftcampaigns', 'local_mailwhistle'),
        [
            get_string('col_name', 'local_mailwhistle'),
            get_string('col_status', 'local_mailwhistle'),
            get_string('col_created', 'local_mailwhistle'),
            get_string('col_actions', 'local_mailwhistle'),
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
    $meta->attributes['class'] = 'table generaltable w-auto local-mailwhistle-mailmeta mb-3';
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
 * @return array List of sample sent mail records.
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
 * @return array|null The matching record, or null if not found.
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
        'draft' => 'badge bg-secondary text-white',
        'ready' => 'badge bg-primary text-white',
        'sent' => 'badge bg-success text-white',
        'sending' => 'badge bg-info text-white',
        'scheduled' => 'badge bg-secondary text-white',
        'failed' => 'badge bg-danger text-white',
    ];
    $class = $classes[$status] ?? 'badge bg-secondary text-white';

    return html_writer::span(
        get_string('status_' . $status, 'local_mailwhistle'),
        $class
    );
}

/**
 * Serve files stored in the plugin's resources file area.
 *
 * @param stdClass $course Course object (unused; system-context plugin).
 * @param stdClass $cm Course module object (unused).
 * @param context $context The context the file was requested in.
 * @param string $filearea The requested file area.
 * @param array $args The remaining file path arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options for file serving.
 * @return bool False if the file cannot be served, otherwise sends the file and exits.
 */
function local_mailwhistle_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
): bool {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }
    if ($filearea !== \local_mailwhistle\output\resources::FILEAREA) {
        return false;
    }
    $filename = array_pop($args);
    if ($args) {
        $filepath = '/' . implode('/', $args);
    } else {
        $filepath = '/';
    }

    $fs = get_file_storage();
    if (!$file = $fs->get_file($context->id, 'local_mailwhistle', $filearea, 0, $filepath, $filename)) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload);
    return true;
}

/**
 * Render the templates tab.
 *
 * @param string $action The requested templates action.
 * @param int $id The selected template id, if any.
 * @return string Rendered HTML.
 */
function local_mailwhistle_render_templates_page(string $action, int $id = 0): string {
    $validactions = ['list', 'create', 'edit', 'preview', 'export', 'archive', 'restore', 'delete'];
    if (!in_array($action, $validactions, true)) {
        $action = 'list';
    }

    switch ($action) {
        case 'create':
            return local_mailwhistle_render_template_form();
        case 'edit':
            if ($id <= 0) {
                throw new moodle_exception('templatenotfound', 'local_mailwhistle');
            }
            return local_mailwhistle_render_template_form($id);
        case 'preview':
            if ($id <= 0) {
                throw new moodle_exception('templatenotfound', 'local_mailwhistle');
            }
            return local_mailwhistle_render_template_preview($id);
        case 'export':
            local_mailwhistle_export_template_action($id);
            return '';
        case 'archive':
            local_mailwhistle_archive_template_action($id);
            return '';
        case 'restore':
            local_mailwhistle_restore_template_action($id);
            return '';
        case 'delete':
            if ($id <= 0) {
                throw new moodle_exception('templatenotfound', 'local_mailwhistle');
            }
            return local_mailwhistle_render_template_delete_confirmation($id);
        case 'list':
        default:
            return local_mailwhistle_render_templates_overview();
    }
}

/**
 * Render the template cards overview.
 *
 * @return string Rendered HTML.
 */
function local_mailwhistle_render_templates_overview(): string {
    global $OUTPUT;

    $context = context_system::instance();
    $canmanage = has_capability('local/mailwhistle:manage', $context);
    $filter = optional_param('filter', 'active', PARAM_ALPHA);
    if (!in_array($filter, ['active', 'archived', 'all'], true)) {
        $filter = 'active';
    }
    $templates = local_mailwhistle_get_templates($filter);

    $output = html_writer::start_div('local-mailwhistle-templates');
    $output .= html_writer::start_div('local-mailwhistle-templates-header');
    $output .= html_writer::tag('h3', get_string('templates_heading', 'local_mailwhistle'));
    if ($canmanage) {
        $createurl = new moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'action' => 'create',
        ]);
        $output .= html_writer::link(
            $createurl,
            get_string('template_create', 'local_mailwhistle'),
            ['class' => 'btn btn-primary']
        );
    }
    $output .= html_writer::end_div();
    $output .= local_mailwhistle_render_template_filters($filter);

    if (empty($templates)) {
        $output .= $OUTPUT->notification(
            get_string('templates_empty_' . $filter, 'local_mailwhistle'),
            \core\output\notification::NOTIFY_INFO
        );
        $output .= html_writer::end_div();
        return $output;
    }

    $output .= html_writer::start_div('local-mailwhistle-template-grid');
    foreach ($templates as $template) {
        $output .= local_mailwhistle_render_template_card($template, $canmanage);
    }
    $output .= html_writer::end_div();
    $output .= html_writer::end_div();

    return $output;
}

/**
 * Render template status filters.
 *
 * @param string $selected Selected filter.
 * @return string Rendered filter HTML.
 */
function local_mailwhistle_render_template_filters(string $selected): string {
    $items = [];
    foreach (['active', 'archived', 'all'] as $filter) {
        $url = new moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'filter' => $filter,
        ]);
        $classes = ['btn', $selected === $filter ? 'btn-primary' : 'btn-secondary'];
        $items[] = html_writer::link($url, get_string('template_filter_' . $filter, 'local_mailwhistle'), [
            'class' => implode(' ', $classes),
        ]);
    }

    return html_writer::div(implode(' ', $items), 'local-mailwhistle-template-filters');
}

/**
 * Render a single template overview card.
 *
 * @param stdClass $template Template record.
 * @param bool $canmanage Whether the current user can manage templates.
 * @return string Rendered card HTML.
 */
function local_mailwhistle_render_template_card(stdClass $template, bool $canmanage): string {
    $previewurl = new moodle_url('/local/mailwhistle/index.php', [
        'tab' => 'templates',
        'action' => 'preview',
        'id' => $template->id,
    ]);

    $previewhtml = local_mailwhistle_prepare_template_preview_html(
        (string) $template->bodyhtml
    );
    if ($previewhtml === '') {
        $previewhtml = html_writer::div(
            get_string('template_no_body', 'local_mailwhistle'),
            'local-mailwhistle-template-preview-empty'
        );
    }

    $background = local_mailwhistle_normalise_template_background((string) ($template->background ?? ''));
    $output = html_writer::start_div('local-mailwhistle-template-card');
    $output .= html_writer::div(
        html_writer::div($previewhtml, 'local-mailwhistle-template-preview-canvas', [
            'style' => 'background:' . $background . ';',
        ]),
        'local-mailwhistle-template-preview'
    );
    $output .= html_writer::start_div('local-mailwhistle-template-card-body');
    $title = format_string($template->name);
    if (!empty($template->archived)) {
        $title .= ' ' . html_writer::span(
            get_string('template_archived_badge', 'local_mailwhistle'),
            'badge bg-secondary'
        );
    }
    $output .= html_writer::tag('h4', $title, ['class' => 'local-mailwhistle-template-title']);
    $output .= html_writer::start_div('local-mailwhistle-template-meta-list');

    if (trim((string) $template->previewtext) !== '') {
        $output .= local_mailwhistle_render_template_card_meta(
            get_string('template_previewtext_label', 'local_mailwhistle'),
            format_string($template->previewtext),
            'preview'
        );
    }

    $output .= local_mailwhistle_render_template_card_meta(
        get_string('template_lastedited_label', 'local_mailwhistle'),
        userdate($template->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        'date'
    );
    $output .= html_writer::end_div();
    $output .= html_writer::end_div();
    $output .= local_mailwhistle_render_template_card_actions($template, $previewurl, $canmanage);
    $output .= html_writer::end_div();

    return $output;
}

/**
 * Render one compact template card metadata row.
 *
 * @param string $label Row label.
 * @param string $value Row value.
 * @param string $type Row type class suffix.
 * @return string Rendered row.
 */
function local_mailwhistle_render_template_card_meta(string $label, string $value, string $type): string {
    return html_writer::div(
        html_writer::span($label, 'local-mailwhistle-template-label')
            . html_writer::span($value, 'local-mailwhistle-template-value'),
        'local-mailwhistle-template-meta local-mailwhistle-template-meta-' . $type
    );
}

/**
 * Render compact card actions.
 *
 * @param stdClass $template Template record.
 * @param moodle_url $previewurl Preview URL.
 * @param bool $canmanage Whether the current user can manage templates.
 * @return string Rendered actions.
 */
function local_mailwhistle_render_template_card_actions(stdClass $template, moodle_url $previewurl, bool $canmanage): string {
    $actions = html_writer::link($previewurl, get_string('template_preview', 'local_mailwhistle'), [
        'class' => 'btn btn-primary',
    ]);

    if ($canmanage) {
        $items = [];
        $items[] = html_writer::link(
            new moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'templates',
                'action' => 'edit',
                'id' => $template->id,
            ]),
            get_string('template_edit', 'local_mailwhistle'),
            ['class' => 'dropdown-item']
        );
        $items[] = html_writer::link(
            new moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'templates',
                'action' => 'export',
                'id' => $template->id,
            ]),
            get_string('template_export', 'local_mailwhistle'),
            ['class' => 'dropdown-item']
        );

        if (empty($template->archived)) {
            $items[] = html_writer::link(
                new moodle_url('/local/mailwhistle/index.php', [
                    'tab' => 'templates',
                    'action' => 'archive',
                    'id' => $template->id,
                    'sesskey' => sesskey(),
                ]),
                get_string('template_archive', 'local_mailwhistle'),
                ['class' => 'dropdown-item']
            );
        } else {
            $items[] = html_writer::link(
                new moodle_url('/local/mailwhistle/index.php', [
                    'tab' => 'templates',
                    'action' => 'restore',
                    'id' => $template->id,
                    'sesskey' => sesskey(),
                ]),
                get_string('template_restore', 'local_mailwhistle'),
                ['class' => 'dropdown-item']
            );
        }

        if (!local_mailwhistle_template_has_usage((int) $template->id)) {
            $items[] = html_writer::link(
                new moodle_url('/local/mailwhistle/index.php', [
                    'tab' => 'templates',
                    'action' => 'delete',
                    'id' => $template->id,
                ]),
                get_string('delete'),
                ['class' => 'dropdown-item text-danger']
            );
        }

        $toggle = html_writer::tag('button', get_string('template_actions', 'local_mailwhistle'), [
            'class' => 'btn btn-secondary dropdown-toggle',
            'type' => 'button',
            'data-toggle' => 'dropdown',
            'data-bs-toggle' => 'dropdown',
            'aria-haspopup' => 'true',
            'aria-expanded' => 'false',
        ]);
        $menu = html_writer::div(implode('', $items), 'dropdown-menu dropdown-menu-right');
        $actions .= html_writer::div($toggle . $menu, 'dropdown local-mailwhistle-template-actions-menu');
    }

    return html_writer::div($actions, 'local-mailwhistle-template-actions');
}

/**
 * Render and process the create/edit template form.
 *
 * @param int $id Existing template id, or 0 for create.
 * @return string Rendered form HTML.
 */
function local_mailwhistle_render_template_form(int $id = 0): string {
    require_capability('local/mailwhistle:manage', context_system::instance());

    $template = null;
    if ($id > 0) {
        $template = local_mailwhistle_get_template($id);
        if (!$template) {
            throw new moodle_exception('templatenotfound', 'local_mailwhistle');
        }
    }

    $formurl = new moodle_url('/local/mailwhistle/index.php', [
        'tab' => 'templates',
        'action' => $id > 0 ? 'edit' : 'create',
        'id' => $id,
    ]);
    $listurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates']);

    $builderjson = $template->builderjson ?? local_mailwhistle_get_default_builder_json();
    $builderstrings = local_mailwhistle_get_builder_strings();

    global $CFG;
    require_once($CFG->libdir . '/formslib.php');

    $mform = new \local_mailwhistle\form\template_form($formurl, [
        'submitlabel' => get_string('template_save', 'local_mailwhistle'),
        'builderjson' => $builderjson,
        'builderstrings' => $builderstrings,
    ]);

    if ($mform->is_cancelled()) {
        redirect($listurl);
    }

    if ($data = $mform->get_data()) {
        if ($id > 0) {
            local_mailwhistle_update_template($id, $data);
        } else {
            $id = local_mailwhistle_create_template($data);
        }

        redirect(
            new moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'templates',
                'action' => 'preview',
                'id' => $id,
            ]),
            get_string('template_saved', 'local_mailwhistle'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($template) {
        $mform->set_data([
            'id' => $template->id,
            'name' => $template->name,
            'previewtext' => $template->previewtext,
            'background' => $template->background ?? '#ffffff',
            'editormode' => $template->editormode ?? 'html',
            'builderjson' => $template->builderjson ?? local_mailwhistle_get_default_builder_json(),
            'bodyhtml_editor' => [
                'text' => $template->bodyhtml,
                'format' => FORMAT_HTML,
            ],
        ]);
    }

    $heading = $id > 0
        ? get_string('template_edit_heading', 'local_mailwhistle')
        : get_string('template_create_heading', 'local_mailwhistle');

    return html_writer::tag('h3', $heading) . $mform->render();
}

/**
 * Render a full template preview.
 *
 * @param int $id Template id.
 * @return string Rendered preview HTML.
 */
function local_mailwhistle_render_template_preview(int $id): string {
    $template = local_mailwhistle_get_template($id);
    if (!$template) {
        throw new moodle_exception('templatenotfound', 'local_mailwhistle');
    }

    $context = context_system::instance();
    $canmanage = has_capability('local/mailwhistle:manage', $context);
    $listurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates']);

    $output = html_writer::start_div('local-mailwhistle-template-fullpreview');
    $output .= html_writer::start_div('local-mailwhistle-preview-toolbar');
    $output .= html_writer::link($listurl, get_string('backtotemplates', 'local_mailwhistle'), [
        'class' => 'btn btn-secondary',
    ]);

    if ($canmanage) {
        $editurl = new moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'action' => 'edit',
            'id' => $template->id,
        ]);
        $output .= ' ' . html_writer::link($editurl, get_string('template_edit', 'local_mailwhistle'), [
            'class' => 'btn btn-primary',
        ]);
        $exporturl = new moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'action' => 'export',
            'id' => $template->id,
        ]);
        $output .= ' ' . html_writer::link($exporturl, get_string('template_export', 'local_mailwhistle'), [
            'class' => 'btn btn-secondary',
        ]);
    }
    $output .= html_writer::end_div();

    $output .= html_writer::tag('h3', format_string($template->name));
    if (trim((string) $template->previewtext) !== '') {
        $output .= html_writer::div(
            html_writer::span(get_string('template_previewtext_label', 'local_mailwhistle'), 'local-mailwhistle-template-label')
                . ' ' . format_string($template->previewtext),
            'local-mailwhistle-template-previewtext'
        );
    }

    $bodyhtml = local_mailwhistle_prepare_template_preview_html(
        (string) $template->bodyhtml
    );
    if ($bodyhtml === '') {
        $bodyhtml = html_writer::div(
            get_string('template_no_body', 'local_mailwhistle'),
            'local-mailwhistle-template-preview-empty'
        );
    }
    $background = local_mailwhistle_normalise_template_background((string) ($template->background ?? ''));
    $output .= html_writer::div($bodyhtml, 'local-mailwhistle-email-preview', [
        'style' => 'background:' . $background . ';',
    ]);
    $output .= html_writer::end_div();

    return $output;
}

/**
 * Export a template as portable JSON.
 *
 * @param int $id Template id.
 * @return void
 */
function local_mailwhistle_export_template_action(int $id): void {
    require_capability('local/mailwhistle:view', context_system::instance());

    $template = local_mailwhistle_get_template($id);
    if (!$template) {
        throw new moodle_exception('templatenotfound', 'local_mailwhistle');
    }

    $export = [
        'format' => 'local_mailwhistle_template',
        'formatversion' => 1,
        'component' => 'local_mailwhistle',
        'exportedat' => gmdate('c'),
        'template' => [
            'name' => $template->name,
            'previewtext' => $template->previewtext,
            'background' => $template->background ?? '#ffffff',
            'editormode' => $template->editormode ?? 'html',
            'builder' => json_decode((string) ($template->builderjson ?? ''), true) ?: ['blocks' => []],
            'html' => $template->bodyhtml ?? '',
            'placeholders' => local_mailwhistle_extract_template_placeholders($template),
        ],
    ];

    $filename = clean_filename($template->name . '-mailwhistle-template.json');
    @header('Content-Type: application/json; charset=utf-8');
    @header('Content-Disposition: attachment; filename="' . $filename . '"');
    @header('Cache-Control: private, max-age=0, must-revalidate');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Handle archive action.
 *
 * @param int $id Template id.
 * @return void
 */
function local_mailwhistle_archive_template_action(int $id): void {
    require_capability('local/mailwhistle:manage', context_system::instance());
    require_sesskey();

    if ($id <= 0 || !local_mailwhistle_get_template($id)) {
        throw new moodle_exception('templatenotfound', 'local_mailwhistle');
    }

    local_mailwhistle_archive_template($id);
    redirect(
        new moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates']),
        get_string('template_archived', 'local_mailwhistle'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

/**
 * Handle restore action.
 *
 * @param int $id Template id.
 * @return void
 */
function local_mailwhistle_restore_template_action(int $id): void {
    require_capability('local/mailwhistle:manage', context_system::instance());
    require_sesskey();

    if ($id <= 0 || !local_mailwhistle_get_template($id)) {
        throw new moodle_exception('templatenotfound', 'local_mailwhistle');
    }

    local_mailwhistle_restore_template($id);
    redirect(
        new moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates', 'filter' => 'archived']),
        get_string('template_restored', 'local_mailwhistle'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

/**
 * Render and handle hard-delete confirmation.
 *
 * @param int $id Template id.
 * @return string Rendered confirmation HTML.
 */
function local_mailwhistle_render_template_delete_confirmation(int $id): string {
    global $OUTPUT;

    require_capability('local/mailwhistle:manage', context_system::instance());

    $template = local_mailwhistle_get_template($id);
    if (!$template) {
        throw new moodle_exception('templatenotfound', 'local_mailwhistle');
    }

    if (local_mailwhistle_template_has_usage($id)) {
        return $OUTPUT->notification(
            get_string('template_delete_used_blocked', 'local_mailwhistle'),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $confirmed = optional_param('confirm', 0, PARAM_BOOL);
    if ($confirmed) {
        require_sesskey();
        local_mailwhistle_delete_template($id);
        redirect(
            new moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates', 'filter' => 'archived']),
            get_string('template_deleted', 'local_mailwhistle'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $confirmurl = new moodle_url('/local/mailwhistle/index.php', [
        'tab' => 'templates',
        'action' => 'delete',
        'id' => $id,
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);
    $cancelurl = new moodle_url('/local/mailwhistle/index.php', [
        'tab' => 'templates',
        'filter' => !empty($template->archived) ? 'archived' : 'active',
    ]);

    return $OUTPUT->confirm(
        get_string('template_delete_confirm', 'local_mailwhistle', format_string($template->name)),
        $confirmurl,
        $cancelurl
    );
}

/**
 * Get templates ordered by most recently edited.
 *
 * @param string $filter Template visibility filter.
 * @return array Template records.
 */
function local_mailwhistle_get_templates(string $filter = 'active'): array {
    global $DB;

    $conditions = [];
    if ($filter === 'active') {
        $conditions['archived'] = 0;
    } else if ($filter === 'archived') {
        $conditions['archived'] = 1;
    }

    return $DB->get_records('local_mailwhistle_templates', $conditions, 'timemodified DESC, name ASC');
}

/**
 * Get one template.
 *
 * @param int $id Template id.
 * @return stdClass|null Template record or null.
 */
function local_mailwhistle_get_template(int $id): ?stdClass {
    global $DB;

    return $DB->get_record('local_mailwhistle_templates', ['id' => $id]) ?: null;
}

/**
 * Archive a template.
 *
 * @param int $id Template id.
 * @return void
 */
function local_mailwhistle_archive_template(int $id): void {
    global $DB;

    $DB->set_field('local_mailwhistle_templates', 'archived', 1, ['id' => $id]);
    $DB->set_field('local_mailwhistle_templates', 'timemodified', time(), ['id' => $id]);
}

/**
 * Restore an archived template.
 *
 * @param int $id Template id.
 * @return void
 */
function local_mailwhistle_restore_template(int $id): void {
    global $DB;

    $DB->set_field('local_mailwhistle_templates', 'archived', 0, ['id' => $id]);
    $DB->set_field('local_mailwhistle_templates', 'timemodified', time(), ['id' => $id]);
}

/**
 * Hard-delete a template when it has never been used.
 *
 * @param int $id Template id.
 * @return void
 */
function local_mailwhistle_delete_template(int $id): void {
    global $DB;

    if (local_mailwhistle_template_has_usage($id)) {
        throw new moodle_exception('templatecannotdeleteused', 'local_mailwhistle');
    }

    $DB->delete_records('local_mailwhistle_templates', ['id' => $id]);
}

/**
 * Check whether a template has been used by sent/scheduled newsletters.
 *
 * This is intentionally centralized so future sending/history tables can
 * enforce hard-delete protection without changing UI code.
 *
 * @param int $id Template id.
 * @return bool True when the template is referenced by usage history.
 */
function local_mailwhistle_template_has_usage(int $id): bool {
    global $DB;

    $checks = [
        'local_email_campaigns' => 'templateid',
    ];

    foreach ($checks as $table => $field) {
        if ($DB->get_manager()->table_exists(new xmldb_table($table))) {
            $columns = $DB->get_columns($table);
            if (array_key_exists($field, $columns) && $DB->record_exists($table, [$field => $id])) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Create a template.
 *
 * @param stdClass $data Form data.
 * @return int New template id.
 */
function local_mailwhistle_create_template(stdClass $data): int {
    global $DB;

    $now = time();
    $record = local_mailwhistle_template_record_from_form($data);
    $record->timecreated = $now;
    $record->timemodified = $now;

    return $DB->insert_record('local_mailwhistle_templates', $record);
}

/**
 * Update a template.
 *
 * @param int $id Template id.
 * @param stdClass $data Form data.
 * @return void
 */
function local_mailwhistle_update_template(int $id, stdClass $data): void {
    global $DB;

    $record = local_mailwhistle_template_record_from_form($data);
    $record->id = $id;
    $record->timemodified = time();

    $DB->update_record('local_mailwhistle_templates', $record);
}

/**
 * Build a database record from submitted form data.
 *
 * @param stdClass $data Form data.
 * @return stdClass Template record.
 */
function local_mailwhistle_template_record_from_form(stdClass $data): stdClass {
    $record = new stdClass();
    $record->name = trim((string) $data->name);
    $record->previewtext = trim((string) ($data->previewtext ?? ''));
    $record->background = local_mailwhistle_normalise_template_background((string) ($data->background ?? ''));
    $record->editormode = local_mailwhistle_normalise_editor_mode((string) ($data->editormode ?? 'html'));
    $record->builderjson = local_mailwhistle_normalise_builder_json((string) ($data->builderjson ?? ''));
    $record->bodyhtml = $record->editormode === 'builder'
        ? local_mailwhistle_render_builder_html($record->builderjson, $record->background)
        : ($data->bodyhtml_editor['text'] ?? '');

    return $record;
}

/**
 * Seed the default email template(s) bundled with the plugin.
 *
 * Reads every exported-template JSON file shipped under db/defaulttemplates and
 * creates it when a template of the same name does not already exist. Safe to
 * call from both db/install.php (fresh installs) and db/upgrade.php (existing
 * sites); the name check keeps repeated calls idempotent.
 *
 * @return int Number of templates created.
 */
function local_mailwhistle_install_default_templates(): int {
    global $CFG;

    $created = 0;
    $pattern = $CFG->dirroot . '/local/mailwhistle/db/defaulttemplates/*.json';
    foreach ((array) glob($pattern) as $file) {
        $json = file_get_contents($file);
        if ($json !== false && local_mailwhistle_import_template_json($json) > 0) {
            $created++;
        }
    }

    return $created;
}

/**
 * Create a template from an exported local_mailwhistle_template JSON document.
 *
 * The stored HTML is regenerated from the builder document so it always matches
 * the current renderer and passes through the plugin's own sanitiser. Returns 0
 * (skips creation) when the JSON is not a valid template export, or when a
 * template with the same name already exists.
 *
 * @param string $json Exported template JSON.
 * @return int New template id, or 0 when skipped.
 */
function local_mailwhistle_import_template_json(string $json): int {
    global $DB;

    $data = json_decode($json, true);
    if (!is_array($data) || ($data['format'] ?? '') !== 'local_mailwhistle_template') {
        return 0;
    }

    $template = $data['template'] ?? null;
    if (!is_array($template)) {
        return 0;
    }

    $name = trim((string) ($template['name'] ?? ''));
    if ($name === '') {
        return 0;
    }

    // Idempotency: never duplicate an existing template of the same name.
    if ($DB->record_exists('local_mailwhistle_templates', ['name' => $name])) {
        return 0;
    }

    $background = local_mailwhistle_normalise_template_background((string) ($template['background'] ?? '#ffffff'));
    $editormode = local_mailwhistle_normalise_editor_mode((string) ($template['editormode'] ?? 'html'));
    $builderjson = local_mailwhistle_normalise_builder_json(
        json_encode($template['builder'] ?? ['blocks' => []])
    );

    if ($editormode === 'builder') {
        $bodyhtml = local_mailwhistle_render_builder_html($builderjson, $background);
    } else {
        $bodyhtml = (string) ($template['html'] ?? '');
    }

    $now = time();
    $record = (object) [
        'name' => $name,
        'previewtext' => trim((string) ($template['previewtext'] ?? '')),
        'background' => $background,
        'editormode' => $editormode,
        'builderjson' => $builderjson,
        'bodyhtml' => $bodyhtml,
        'archived' => 0,
        'timecreated' => $now,
        'timemodified' => $now,
    ];

    return (int) $DB->insert_record('local_mailwhistle_templates', $record);
}

/**
 * Normalise the template-level email background.
 *
 * @param string $background Submitted background color.
 * @return string Safe hex color.
 */
function local_mailwhistle_normalise_template_background(string $background): string {
    $background = trim($background);

    return preg_match('/^#[0-9a-f]{6}$/i', $background) ? $background : '#ffffff';
}

/**
 * Extract placeholder names from a template record.
 *
 * Placeholders are written directly in template text as {{name}}.
 *
 * @param stdClass|null $template Template record.
 * @return array Unique placeholder names in first-seen order.
 */
function local_mailwhistle_extract_template_placeholders(?stdClass $template): array {
    if (!$template) {
        return [];
    }

    return local_mailwhistle_extract_placeholders_from_text(
        (string) ($template->previewtext ?? '') . "\n" . (string) ($template->bodyhtml ?? '')
    );
}

/**
 * Extract placeholder names from text.
 *
 * @param string $text Text that may contain {{name}} placeholders.
 * @return array Unique placeholder names in first-seen order.
 */
function local_mailwhistle_extract_placeholders_from_text(string $text): array {
    if (!preg_match_all('/\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/', $text, $matches)) {
        return [];
    }

    $placeholders = [];
    $seen = [];
    foreach ($matches[1] as $placeholder) {
        $key = strtolower($placeholder);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $placeholders[] = $placeholder;
    }

    return $placeholders;
}

/**
 * Normalise the selected template editor mode.
 *
 * @param string $mode Submitted editor mode.
 * @return string Normalised mode.
 */
function local_mailwhistle_normalise_editor_mode(string $mode): string {
    return $mode === 'builder' ? 'builder' : 'html';
}

/**
 * Get a default builder document.
 *
 * @return string JSON encoded builder document.
 */
function local_mailwhistle_get_default_builder_json(): string {
    return json_encode([
        'blocks' => [],
    ]);
}

/**
 * Get default values for all builder block types.
 *
 * @return array Builder defaults keyed by block type.
 */
function local_mailwhistle_builder_block_defaults(): array {
    return [
        'header' => [
            'type' => 'header',
            'title' => 'Newsletter title',
            'subtitle' => 'Short supporting text',
            'background' => '#1f4f82',
            'color' => '#ffffff',
            'fontfamily' => 'arial',
            'fontsize' => 28,
            'align' => 'center',
            'padding' => 32,
        ],
        'logo' => [
            'type' => 'logo',
            'url' => '',
            'alt' => '',
            'width' => 35,
            'align' => 'center',
            'padding' => 18,
        ],
        'text' => [
            'type' => 'text',
            'content' => 'Write your message here.',
            'color' => '#1f2933',
            'fontfamily' => 'arial',
            'fontsize' => 16,
            'align' => 'left',
            'padding' => 24,
        ],
        'button' => [
            'type' => 'button',
            'label' => 'Learn more',
            'url' => '#',
            'background' => '#1f4f82',
            'color' => '#ffffff',
            'align' => 'center',
            'padding' => 24,
        ],
        'image' => [
            'type' => 'image',
            'url' => '',
            'alt' => '',
            'width' => 100,
            'align' => 'center',
            'padding' => 24,
        ],
        'highlight' => [
            'type' => 'highlight',
            'title' => 'Important update',
            'content' => 'Add the key message here.',
            'background' => '#f3f7fb',
            'color' => '#1f2933',
            'bordercolor' => '#1f4f82',
            'fontfamily' => 'arial',
            'fontsize' => 16,
            'padding' => 20,
        ],
        'social' => [
            'type' => 'social',
            'label1' => 'Website',
            'url1' => '#',
            'label2' => 'LinkedIn',
            'url2' => '#',
            'label3' => 'Instagram',
            'url3' => '#',
            'align' => 'center',
            'padding' => 20,
        ],
        'columns' => [
            'type' => 'columns',
            'lefttitle' => 'Left column',
            'leftcontent' => 'Add text here.',
            'righttitle' => 'Right column',
            'rightcontent' => 'Add text here.',
            'background' => '#ffffff',
            'color' => '#1f2933',
            'fontfamily' => 'arial',
            'fontsize' => 15,
            'padding' => 20,
        ],
        'divider' => [
            'type' => 'divider',
        ],
        'footer' => [
            'type' => 'footer',
            'content' => 'You are receiving this email from {{university}}.',
            'color' => '#52616f',
            'fontfamily' => 'arial',
            'fontsize' => 13,
            'align' => 'center',
            'padding' => 22,
            'background' => '#f3f5f8',
        ],
    ];
}

/**
 * Provide localized strings for the builder JavaScript.
 *
 * @return array Builder strings.
 */
function local_mailwhistle_get_builder_strings(): array {
    return [
        'builderheading' => get_string('template_builder_heading', 'local_mailwhistle'),
        'addheader' => get_string('template_builder_add_header', 'local_mailwhistle'),
        'addlogo' => get_string('template_builder_add_logo', 'local_mailwhistle'),
        'addtext' => get_string('template_builder_add_text', 'local_mailwhistle'),
        'addbutton' => get_string('template_builder_add_button', 'local_mailwhistle'),
        'addimage' => get_string('template_builder_add_image', 'local_mailwhistle'),
        'addhighlight' => get_string('template_builder_add_highlight', 'local_mailwhistle'),
        'addsocial' => get_string('template_builder_add_social', 'local_mailwhistle'),
        'addcolumns' => get_string('template_builder_add_columns', 'local_mailwhistle'),
        'adddivider' => get_string('template_builder_add_divider', 'local_mailwhistle'),
        'addfooter' => get_string('template_builder_add_footer', 'local_mailwhistle'),
        'remove' => get_string('delete'),
        'drag' => get_string('template_builder_drag', 'local_mailwhistle'),
        'title' => get_string('template_builder_title', 'local_mailwhistle'),
        'subtitle' => get_string('template_builder_subtitle', 'local_mailwhistle'),
        'content' => get_string('template_builder_content', 'local_mailwhistle'),
        'label' => get_string('template_builder_label', 'local_mailwhistle'),
        'url' => get_string('template_builder_url', 'local_mailwhistle'),
        'alt' => get_string('template_builder_alt', 'local_mailwhistle'),
        'background' => get_string('template_builder_background', 'local_mailwhistle'),
        'color' => get_string('template_builder_color', 'local_mailwhistle'),
        'fontfamily' => get_string('template_builder_fontfamily', 'local_mailwhistle'),
        'fontsize' => get_string('template_builder_fontsize', 'local_mailwhistle'),
        'align' => get_string('template_builder_align', 'local_mailwhistle'),
        'padding' => get_string('template_builder_padding', 'local_mailwhistle'),
        'width' => get_string('template_builder_width', 'local_mailwhistle'),
        'bordercolor' => get_string('template_builder_bordercolor', 'local_mailwhistle'),
        'label1' => get_string('template_builder_label1', 'local_mailwhistle'),
        'label2' => get_string('template_builder_label2', 'local_mailwhistle'),
        'label3' => get_string('template_builder_label3', 'local_mailwhistle'),
        'url1' => get_string('template_builder_url1', 'local_mailwhistle'),
        'url2' => get_string('template_builder_url2', 'local_mailwhistle'),
        'url3' => get_string('template_builder_url3', 'local_mailwhistle'),
        'lefttitle' => get_string('template_builder_lefttitle', 'local_mailwhistle'),
        'leftcontent' => get_string('template_builder_leftcontent', 'local_mailwhistle'),
        'righttitle' => get_string('template_builder_righttitle', 'local_mailwhistle'),
        'rightcontent' => get_string('template_builder_rightcontent', 'local_mailwhistle'),
        'empty' => get_string('template_builder_empty', 'local_mailwhistle'),
        'imageplaceholder' => get_string('template_builder_image_placeholder', 'local_mailwhistle'),
        'logoplaceholder' => get_string('template_builder_logo_placeholder', 'local_mailwhistle'),
    ];
}

/**
 * Normalise submitted builder JSON.
 *
 * @param string $json Submitted builder JSON.
 * @return string Normalised JSON.
 */
function local_mailwhistle_normalise_builder_json(string $json): string {
    $builder = json_decode($json, true);
    if (!is_array($builder) || !isset($builder['blocks']) || !is_array($builder['blocks'])) {
        return local_mailwhistle_get_default_builder_json();
    }

    $blocks = [];
    foreach ($builder['blocks'] as $block) {
        if (!is_array($block)) {
            continue;
        }

        $normalised = local_mailwhistle_normalise_builder_block($block);
        if ($normalised !== null) {
            $blocks[] = $normalised;
        }

        if (count($blocks) >= 50) {
            break;
        }
    }

    return json_encode(['blocks' => $blocks]);
}

/**
 * Validate submitted builder JSON before saving.
 *
 * @param string $json Submitted builder JSON.
 * @return array Validation error messages.
 */
function local_mailwhistle_validate_builder_json(string $json): array {
    $builder = json_decode($json, true);
    if (!is_array($builder) || !isset($builder['blocks']) || !is_array($builder['blocks'])) {
        return [get_string('template_builder_invalid', 'local_mailwhistle')];
    }

    if (count($builder['blocks']) > 50) {
        return [get_string('template_builder_too_many_blocks', 'local_mailwhistle')];
    }

    $errors = [];
    foreach ($builder['blocks'] as $index => $block) {
        if (!is_array($block)) {
            $errors[] = get_string('template_builder_invalid_block', 'local_mailwhistle', $index + 1);
            continue;
        }

        $type = clean_param((string) ($block['type'] ?? ''), PARAM_ALPHA);
        if (!array_key_exists($type, local_mailwhistle_builder_block_defaults())) {
            $errors[] = get_string('template_builder_invalid_block', 'local_mailwhistle', $index + 1);
            continue;
        }

        foreach (['background', 'color', 'bordercolor'] as $field) {
            if (isset($block[$field]) && !preg_match('/^#[0-9a-f]{6}$/i', (string) $block[$field])) {
                $errors[] = get_string('template_builder_invalid_colour', 'local_mailwhistle', $index + 1);
            }
        }

        foreach (['url', 'url1', 'url2', 'url3'] as $field) {
            if (isset($block[$field]) && !local_mailwhistle_builder_is_valid_url((string) $block[$field])) {
                $errors[] = get_string('template_builder_invalid_url', 'local_mailwhistle', $index + 1);
            }
        }

        if (
            isset($block['fontfamily'])
                && !array_key_exists((string) $block['fontfamily'], local_mailwhistle_builder_font_families())
        ) {
            $errors[] = get_string('template_builder_invalid_font', 'local_mailwhistle', $index + 1);
        }

        if (isset($block['align']) && !in_array((string) $block['align'], ['left', 'center', 'right'], true)) {
            $errors[] = get_string('template_builder_invalid_align', 'local_mailwhistle', $index + 1);
        }

        $ranges = [
            'fontsize' => [10, 42],
            'padding' => [0, 56],
            'width' => [10, 100],
        ];
        foreach ($ranges as $field => $range) {
            if (isset($block[$field])) {
                $value = filter_var($block[$field], FILTER_VALIDATE_INT);
                if ($value === false || $value < $range[0] || $value > $range[1]) {
                    $errors[] = get_string('template_builder_invalid_number', 'local_mailwhistle', $index + 1);
                }
            }
        }
    }

    return $errors;
}

/**
 * Validate a URL-like builder value.
 *
 * @param string $url URL value.
 * @return bool Whether the value is acceptable.
 */
function local_mailwhistle_builder_is_valid_url(string $url): bool {
    $url = trim($url);
    if ($url === '' || $url === '#') {
        return true;
    }

    if (str_starts_with($url, '{{') && str_ends_with($url, '}}')) {
        return true;
    }

    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Normalise a single builder block.
 *
 * @param array $block Submitted block.
 * @return array|null Normalised block, or null when unsupported.
 */
function local_mailwhistle_normalise_builder_block(array $block): ?array {
    $type = clean_param((string) ($block['type'] ?? ''), PARAM_ALPHA);
    $defaults = local_mailwhistle_builder_block_defaults();
    if (!array_key_exists($type, $defaults)) {
        return null;
    }

    $normalised = $defaults[$type];

    $textfields = [
        'title', 'subtitle', 'content', 'label', 'alt', 'label1', 'label2', 'label3',
        'lefttitle', 'leftcontent', 'righttitle', 'rightcontent',
    ];
    foreach ($textfields as $field) {
        if (isset($block[$field])) {
            $normalised[$field] = shorten_text(clean_param((string) $block[$field], PARAM_TEXT), 2000);
        }
    }

    foreach (['url', 'url1', 'url2', 'url3'] as $field) {
        if (isset($block[$field])) {
            $normalised[$field] = clean_param((string) $block[$field], PARAM_URL);
        }
    }

    if (isset($block['fontfamily'])) {
        $fontfamily = clean_param((string) $block['fontfamily'], PARAM_ALPHA);
        if (array_key_exists($fontfamily, local_mailwhistle_builder_font_families())) {
            $normalised['fontfamily'] = $fontfamily;
        }
    }

    if (isset($block['align'])) {
        $align = clean_param((string) $block['align'], PARAM_ALPHA);
        if (in_array($align, ['left', 'center', 'right'], true)) {
            $normalised['align'] = $align;
        }
    }

    foreach (['background', 'color', 'bordercolor'] as $field) {
        if (isset($block[$field]) && preg_match('/^#[0-9a-f]{6}$/i', (string) $block[$field])) {
            $normalised[$field] = (string) $block[$field];
        }
    }

    if (isset($block['fontsize'])) {
        $normalised['fontsize'] = min(42, max(10, (int) $block['fontsize']));
    }

    if (isset($block['padding'])) {
        $normalised['padding'] = min(56, max(0, (int) $block['padding']));
    }

    if (isset($block['width'])) {
        $normalised['width'] = min(100, max(10, (int) $block['width']));
    }

    return $normalised;
}

/**
 * Email-safe font family options used by the builder.
 *
 * @return array
 */
function local_mailwhistle_builder_font_families(): array {
    return [
        'arial' => 'Arial,Helvetica,sans-serif',
        'verdana' => 'Verdana,Geneva,sans-serif',
        'georgia' => 'Georgia,serif',
        'times' => '"Times New Roman",Times,serif',
        'trebuchet' => '"Trebuchet MS",Arial,sans-serif',
    ];
}

/**
 * Get an email-safe font stack for a builder block.
 *
 * @param array $block Builder block.
 * @return string Font stack.
 */
function local_mailwhistle_builder_font_family(array $block): string {
    $fonts = local_mailwhistle_builder_font_families();
    $key = clean_param((string) ($block['fontfamily'] ?? 'arial'), PARAM_ALPHA);

    return $fonts[$key] ?? $fonts['arial'];
}

/**
 * Get a safe text alignment value for a builder block.
 *
 * @param array $block Builder block.
 * @param string $default Default alignment.
 * @return string Alignment.
 */
function local_mailwhistle_builder_align(array $block, string $default = 'left'): string {
    $align = clean_param((string) ($block['align'] ?? $default), PARAM_ALPHA);

    return in_array($align, ['left', 'center', 'right'], true) ? $align : $default;
}

/**
 * Get a bounded integer option from a builder block.
 *
 * @param array $block Builder block.
 * @param string $field Field name.
 * @param int $default Default value.
 * @param int $min Minimum value.
 * @param int $max Maximum value.
 * @return int Bounded value.
 */
function local_mailwhistle_builder_int(array $block, string $field, int $default, int $min, int $max): int {
    $value = isset($block[$field]) ? (int) $block[$field] : $default;

    return min($max, max($min, $value));
}

/**
 * Render builder JSON to email-friendly HTML.
 *
 * @param string $json Normalised builder JSON.
 * @param string $background Template background color.
 * @return string Rendered email HTML.
 */
function local_mailwhistle_render_builder_html(string $json, string $background = '#ffffff'): string {
    $builder = json_decode($json, true);
    if (!is_array($builder) || empty($builder['blocks']) || !is_array($builder['blocks'])) {
        return '';
    }

    $background = local_mailwhistle_normalise_template_background($background);
    $output = html_writer::start_tag('div', [
        'style' => 'max-width:640px;margin:0 auto;background:' . $background
            . ';font-family:Arial,Helvetica,sans-serif;color:#1f2933;',
    ]);

    foreach ($builder['blocks'] as $block) {
        if (is_array($block)) {
            $output .= local_mailwhistle_render_builder_block_html($block);
        }
    }

    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * Render one builder block to HTML.
 *
 * @param array $block Normalised block.
 * @return string Rendered block HTML.
 */
function local_mailwhistle_render_builder_block_html(array $block): string {
    $type = $block['type'] ?? '';

    switch ($type) {
        case 'header':
            $background = $block['background'] ?? '#1f4f82';
            $color = $block['color'] ?? '#ffffff';
            $title = format_string($block['title'] ?? '');
            $subtitle = format_string($block['subtitle'] ?? '');
            $padding = local_mailwhistle_builder_int($block, 'padding', 32, 8, 56);
            $fontsize = local_mailwhistle_builder_int($block, 'fontsize', 28, 16, 42);
            $align = local_mailwhistle_builder_align($block, 'center');
            $fontfamily = local_mailwhistle_builder_font_family($block);
            $content = html_writer::tag('h1', $title, [
                'style' => 'margin:0 0 8px;font-size:' . $fontsize . 'px;line-height:1.25;color:' . $color . ';',
            ]);
            if ($subtitle !== '') {
                $content .= html_writer::tag('p', $subtitle, [
                    'style' => 'margin:0;font-size:16px;line-height:1.5;color:' . $color . ';',
                ]);
            }
            return html_writer::div($content, '', [
                'style' => 'padding:' . $padding . 'px 28px;background:' . $background . ';color:' . $color
                    . ';font-family:' . $fontfamily . ';text-align:' . $align . ';',
            ]);

        case 'text':
            $content = nl2br(s(format_string($block['content'] ?? '')));
            $padding = local_mailwhistle_builder_int($block, 'padding', 24, 8, 48);
            $fontsize = local_mailwhistle_builder_int($block, 'fontsize', 16, 11, 28);
            $align = local_mailwhistle_builder_align($block);
            $fontfamily = local_mailwhistle_builder_font_family($block);
            $color = $block['color'] ?? '#1f2933';
            return html_writer::div($content, '', [
                'style' => 'padding:' . $padding . 'px 28px;font-family:' . $fontfamily . ';font-size:' . $fontsize
                    . 'px;line-height:1.6;text-align:' . $align . ';color:' . $color . ';',
            ]);

        case 'button':
            $background = $block['background'] ?? '#1f4f82';
            $color = $block['color'] ?? '#ffffff';
            $label = format_string($block['label'] ?? get_string('template_builder_button_default', 'local_mailwhistle'));
            $url = !empty($block['url']) ? $block['url'] : '#';
            $padding = local_mailwhistle_builder_int($block, 'padding', 24, 8, 48);
            $align = local_mailwhistle_builder_align($block, 'center');
            $button = html_writer::link($url, $label, [
                'style' => 'display:inline-block;padding:12px 20px;background:' . $background . ';color:' . $color
                    . ';text-decoration:none;border-radius:4px;font-weight:bold;',
            ]);
            return html_writer::div($button, '', [
                'style' => 'padding:8px 28px ' . $padding . 'px;text-align:' . $align . ';',
            ]);

        case 'image':
            $url = !empty($block['url']) ? $block['url'] : '';
            $padding = local_mailwhistle_builder_int($block, 'padding', 24, 0, 48);
            $width = local_mailwhistle_builder_int($block, 'width', 100, 10, 100);
            $align = local_mailwhistle_builder_align($block, 'center');
            if ($url === '') {
                return html_writer::div(
                    s(get_string('template_builder_image_placeholder', 'local_mailwhistle')),
                    '',
                    [
                        'style' => 'margin:8px 28px ' . $padding . 'px;padding:48px 20px;border:1px dashed #c8d0da;'
                            . 'background:#f3f5f8;color:#52616f;text-align:center;',
                    ]
                );
            }
            return html_writer::div(
                html_writer::empty_tag('img', [
                    'src' => $url,
                    'alt' => $block['alt'] ?? '',
                    'width' => $width . '%',
                    'style' => 'display:inline-block;width:' . $width . '%;max-width:100%;height:auto;border:0;',
                ]),
                '',
                ['style' => 'padding:8px 28px ' . $padding . 'px;text-align:' . $align . ';']
            );

        case 'logo':
            $url = !empty($block['url']) ? $block['url'] : '';
            $padding = local_mailwhistle_builder_int($block, 'padding', 18, 0, 40);
            $width = local_mailwhistle_builder_int($block, 'width', 35, 10, 60);
            $align = local_mailwhistle_builder_align($block, 'center');
            if ($url === '') {
                return html_writer::div(
                    s(get_string('template_builder_logo_placeholder', 'local_mailwhistle')),
                    '',
                    [
                        'style' => 'margin:8px 28px ' . $padding . 'px;padding:22px 20px;border:1px dashed #c8d0da;'
                            . 'background:#f8fafc;color:#52616f;text-align:center;',
                    ]
                );
            }
            return html_writer::div(
                html_writer::empty_tag('img', [
                    'src' => $url,
                    'alt' => $block['alt'] ?? '',
                    'width' => $width . '%',
                    'style' => 'display:inline-block;width:' . $width . '%;max-width:100%;height:auto;border:0;',
                ]),
                '',
                ['style' => 'padding:8px 28px ' . $padding . 'px;text-align:' . $align . ';']
            );

        case 'highlight':
            $background = $block['background'] ?? '#f3f7fb';
            $color = $block['color'] ?? '#1f2933';
            $bordercolor = $block['bordercolor'] ?? '#1f4f82';
            $padding = local_mailwhistle_builder_int($block, 'padding', 20, 8, 40);
            $fontsize = local_mailwhistle_builder_int($block, 'fontsize', 16, 12, 24);
            $fontfamily = local_mailwhistle_builder_font_family($block);
            $title = format_string($block['title'] ?? '');
            $content = nl2br(s(format_string($block['content'] ?? '')));
            $inner = '';
            if ($title !== '') {
                $inner .= html_writer::tag('strong', $title, [
                    'style' => 'display:block;margin-bottom:6px;font-size:' . $fontsize . 'px;',
                ]);
            }
            $inner .= html_writer::div($content, '', [
                'style' => 'font-size:' . $fontsize . 'px;line-height:1.5;',
            ]);
            return html_writer::div(
                html_writer::div($inner, '', [
                    'style' => 'padding:' . $padding . 'px;border-left:5px solid ' . $bordercolor
                        . ';background:' . $background . ';font-family:' . $fontfamily . ';color:' . $color . ';',
                ]),
                '',
                ['style' => 'padding:8px 28px 24px;']
            );

        case 'social':
            $padding = local_mailwhistle_builder_int($block, 'padding', 20, 8, 40);
            $align = local_mailwhistle_builder_align($block, 'center');
            $links = '';
            foreach ([1, 2, 3] as $number) {
                $label = trim((string) ($block['label' . $number] ?? ''));
                $url = trim((string) ($block['url' . $number] ?? ''));
                if ($label === '' && $url === '') {
                    continue;
                }
                $links .= html_writer::span(
                    html_writer::link($url !== '' ? $url : '#', $label !== '' ? format_string($label) : s($url), [
                        'style' => 'color:#1f4f82;text-decoration:underline;',
                    ]),
                    '',
                    ['style' => 'display:inline-block;margin:0 8px 8px;']
                );
            }
            return html_writer::div($links, '', [
                'style' => 'padding:8px 28px ' . $padding . 'px;text-align:' . $align
                    . ';font-family:Arial,Helvetica,sans-serif;font-size:14px;',
            ]);

        case 'columns':
            $background = $block['background'] ?? '#ffffff';
            $color = $block['color'] ?? '#1f2933';
            $padding = local_mailwhistle_builder_int($block, 'padding', 20, 8, 40);
            $fontsize = local_mailwhistle_builder_int($block, 'fontsize', 15, 11, 22);
            $fontfamily = local_mailwhistle_builder_font_family($block);
            $cellstyle = 'padding:' . $padding . 'px;font-family:' . $fontfamily . ';font-size:' . $fontsize
                . 'px;line-height:1.5;color:' . $color . ';vertical-align:top;';
            $left = html_writer::tag('strong', format_string($block['lefttitle'] ?? ''), [
                'style' => 'display:block;margin-bottom:6px;',
            ]) . nl2br(s(format_string($block['leftcontent'] ?? '')));
            $right = html_writer::tag('strong', format_string($block['righttitle'] ?? ''), [
                'style' => 'display:block;margin-bottom:6px;',
            ]) . nl2br(s(format_string($block['rightcontent'] ?? '')));
            $row = html_writer::tag(
                'tr',
                html_writer::tag('td', $left, ['width' => '50%', 'style' => $cellstyle])
                    . html_writer::tag('td', $right, ['width' => '50%', 'style' => $cellstyle])
            );
            return html_writer::tag('table', $row, [
                'role' => 'presentation',
                'width' => '100%',
                'cellspacing' => '0',
                'cellpadding' => '0',
                'style' => 'background:' . $background . ';',
            ]);

        case 'divider':
            return html_writer::div('', '', [
                'style' => 'height:1px;margin:8px 28px;background:#d8dee6;',
            ]);

        case 'footer':
            $content = nl2br(s(format_string($block['content'] ?? '')));
            $padding = local_mailwhistle_builder_int($block, 'padding', 22, 8, 48);
            $fontsize = local_mailwhistle_builder_int($block, 'fontsize', 13, 10, 18);
            $align = local_mailwhistle_builder_align($block, 'center');
            $fontfamily = local_mailwhistle_builder_font_family($block);
            $background = $block['background'] ?? '#f3f5f8';
            $color = $block['color'] ?? '#52616f';
            return html_writer::div($content, '', [
                'style' => 'padding:' . $padding . 'px 28px;background:' . $background . ';color:' . $color
                    . ';font-family:' . $fontfamily . ';font-size:' . $fontsize . 'px;line-height:1.5;text-align:' . $align . ';',
            ]);
    }

    return '';
}

/**
 * Prepare template HTML for on-page preview rendering.
 *
 * Full email documents often include doctype, html, head and body wrappers. The
 * Moodle page should preview the email body only, then let format_text() clean
 * the resulting HTML before it is displayed.
 *
 * @param string $html Stored template HTML.
 * @return string Cleaned preview HTML.
 */
function local_mailwhistle_prepare_template_preview_html(string $html): string {
    $html = local_mailwhistle_normalise_preview_html($html);
    if ($html === '') {
        return '';
    }

    if (preg_match('/&lt;\s*(?:!doctype|html|head|body)\b/i', $html)) {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $body = local_mailwhistle_extract_html_body($html);
    if ($body === '') {
        $body = local_mailwhistle_remove_html_document_shell($html);
    }

    $body = local_mailwhistle_normalise_preview_html($body);
    if ($body === '') {
        return '';
    }

    return format_text($body, FORMAT_HTML);
}

/**
 * Extract inner body HTML from a complete HTML document.
 *
 * @param string $html HTML document or fragment.
 * @return string Body inner HTML, or an empty string when no body tag exists.
 */
function local_mailwhistle_extract_html_body(string $html): string {
    if (!preg_match('/<\s*body\b/i', $html)) {
        return '';
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return '';
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }

    $innerhtml = '';
    foreach ($body->childNodes as $child) {
        $innerhtml .= $dom->saveHTML($child);
    }

    return $innerhtml;
}

/**
 * Remove document-level HTML wrappers from pasted email source.
 *
 * @param string $html HTML document or fragment.
 * @return string HTML without doctype/html/head/body wrappers where present.
 */
function local_mailwhistle_remove_html_document_shell(string $html): string {
    $html = preg_replace('/<!doctype[^>]*>/i', '', $html);
    $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html);
    $html = preg_replace('/<\/?(?:html|body)\b[^>]*>/i', '', $html);

    return $html ?? '';
}

/**
 * Normalize HTML text before thumbnail rendering.
 *
 * @param string $html HTML document or fragment.
 * @return string Normalized HTML.
 */
function local_mailwhistle_normalise_preview_html(string $html): string {
    $html = str_replace(["\xC2\xA0", "\xC3\x82"], [' ', ''], $html);
    $html = preg_replace('/\s+/', ' ', $html);

    return trim($html ?? '');
}

/**
 * Render the "use a template" picker shown above the campaign content step.
 *
 * Submits via GET back to the content step with a templateid, so the controller
 * can prefill the body editor from the chosen template (copy semantics). Returns
 * an empty string when no active templates exist.
 *
 * @param moodle_url $baseurl The campaign edit base URL (carries campaignid).
 * @param int $campaignid The campaign being edited.
 * @param int $selectedid Currently selected template id, if any.
 * @return string Rendered picker HTML.
 */
function local_mailwhistle_render_campaign_template_picker(moodle_url $baseurl, int $campaignid, int $selectedid = 0): string {
    $templates = local_mailwhistle_get_templates('active');
    if (empty($templates)) {
        return '';
    }

    $options = ['' => get_string('campaign_template_choose', 'local_mailwhistle')];
    foreach ($templates as $template) {
        $options[(int) $template->id] = format_string($template->name);
    }

    $out  = html_writer::start_tag('form', [
        'method' => 'get',
        'action' => (new moodle_url('/local/mailwhistle/campaign_edit.php'))->out(false),
        'class'  => 'local-mailwhistle-campaign-template-picker d-flex align-items-end mb-3',
    ]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'campaignid', 'value' => $campaignid]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'step', 'value' => 'content']);
    $out .= html_writer::start_div('me-2');
    $out .= html_writer::label(
        get_string('campaign_usetemplate', 'local_mailwhistle'),
        'mw-campaign-templateid',
        true,
        ['class' => 'd-block']
    );
    $out .= html_writer::select($options, 'templateid', $selectedid, false, ['id' => 'mw-campaign-templateid']);
    $out .= html_writer::end_div();
    $out .= html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('campaign_template_load', 'local_mailwhistle'),
        'class' => 'btn btn-secondary',
    ]);
    $out .= html_writer::end_tag('form');

    return $out;
}
