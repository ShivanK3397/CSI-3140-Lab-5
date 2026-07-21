<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/local/request_service.php');

global $USER, $OUTPUT, $PAGE;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/securecoursehub:viewown', $context);
$cancreate = has_capability('local/securecoursehub:createrequest', $context);
$canmanagecourse = has_capability('local/securecoursehub:managecourserequests', $context);
$cansiteadmin = has_capability('local/securecoursehub:manageall', context_system::instance());
$PAGE->set_url(new moodle_url('/local/securecoursehub/index.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_securecoursehub'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd('local_securecoursehub/dashboard', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_securecoursehub'));
echo html_writer::div(
	get_string('loggedinas', 'local_securecoursehub', s(fullname($USER))),
	'alert alert-info'
);
echo html_writer::div(
	get_string('coursecontext', 'local_securecoursehub', s(format_string($course->fullname))),
	'small text-muted mb-3'
);
echo html_writer::div('', 'alert', [
    'id'     => 'sch-message',
    'hidden' => 'hidden',
    'aria-live' => 'polite',
]);
echo '<div id="securecoursehub-root"></div>';
echo html_writer::div('', 'securecoursehub-permissions', [
	'data-can-create' => $cancreate ? '1' : '0',
	'data-can-manage-course' => $canmanagecourse ? '1' : '0',
	'data-can-manage-all' => $cansiteadmin ? '1' : '0',
]);

// ---------------------------------------------------------------
// Step 10: Create request form — shown to users with createrequest.
// All output uses html_writer — no raw string interpolation.
// ---------------------------------------------------------------
if ($cancreate) {
    echo html_writer::start_div('card p-3 mb-4');
    echo html_writer::tag('h3', get_string('createrequestheading', 'local_securecoursehub'));
 
    echo html_writer::tag('label',
        get_string('titlelabel', 'local_securecoursehub'),
        ['for' => 'sch-title', 'class' => 'form-label font-weight-bold']
    );
    echo html_writer::empty_tag('input', [
        'type'        => 'text',
        'id'          => 'sch-title',
        'class'       => 'form-control mb-2',
        'maxlength'   => '100',
        'placeholder' => get_string('titleplaceholder', 'local_securecoursehub'),
    ]);
 
    echo html_writer::tag('label',
        get_string('descriptionlabel', 'local_securecoursehub'),
        ['for' => 'sch-description', 'class' => 'form-label font-weight-bold']
    );
    echo html_writer::tag('textarea', '', [
        'id'          => 'sch-description',
        'class'       => 'form-control mb-2',
        'rows'        => '4',
        'maxlength'   => '1000',
        'placeholder' => get_string('descriptionplaceholder', 'local_securecoursehub'),
    ]);
 
    echo html_writer::tag('button',
        get_string('submitrequest', 'local_securecoursehub'),
        ['type' => 'button', 'id' => 'sch-submit-request', 'class' => 'btn btn-primary']
    );
 
    echo html_writer::end_div();
}
 
// ---------------------------------------------------------------
// Step 11: Teacher course management view.
// Filter buttons and course request list rendered by dashboard.js.
// ---------------------------------------------------------------
if ($canmanagecourse || $cansiteadmin) {
    echo html_writer::start_div('card p-3 mb-4');
    echo html_writer::tag('h3', get_string('courserequestsheading', 'local_securecoursehub'));
 
    // Filter buttons — data-status is read by dashboard.js.
    echo html_writer::start_div('mb-3', ['role' => 'group', 'aria-label' => 'Filter by status']);
    foreach (['', 'open', 'inprogress', 'resolved'] as $status) {
        $label = $status === '' ? get_string('filterall', 'local_securecoursehub') : $status;
        echo html_writer::tag('button', s($label), [
            'type'        => 'button',
            'class'       => 'btn btn-outline-secondary btn-sm mr-1 sch-filter-btn',
            'data-status' => $status,
        ]);
    }
    echo html_writer::end_div();
 
    // Container populated dynamically by loadCourseRequests() in dashboard.js.
    echo html_writer::div('', 'mt-2', ['id' => 'sch-course-request-list']);
    echo html_writer::end_div();
}
 
// ---------------------------------------------------------------
// Step 12: Student own-requests view.
// ---------------------------------------------------------------
if (!$canmanagecourse && !$cansiteadmin) {
    echo html_writer::start_div('card p-3 mb-4');
    echo html_writer::tag('h3', get_string('myrequestsheading', 'local_securecoursehub'));
 
    // Container populated dynamically by loadOwnRequests() in dashboard.js.
    echo html_writer::div('', 'mt-2', ['id' => 'sch-request-list']);
    echo html_writer::end_div();
}

echo $OUTPUT->footer();