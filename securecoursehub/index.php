<?php
require_once(__DIR__ . '/../../config.php');
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
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_securecoursehub'));
echo html_writer::div(
	get_string('loggedinas', 'local_securecoursehub', s(fullname($USER))),
	'alert alert-info'
);
echo html_writer::div(
	get_string('coursecontext', 'local_securecoursehub', s(format_string($course->fullname))),
	'small text-muted'
);
echo '<div id="securecoursehub-root"></div>';
echo html_writer::div('', 'securecoursehub-permissions', [
	'data-can-create' => $cancreate ? '1' : '0',
	'data-can-manage-course' => $canmanagecourse ? '1' : '0',
	'data-can-manage-all' => $cansiteadmin ? '1' : '0',
]);
echo $OUTPUT->footer();