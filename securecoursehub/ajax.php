<?php
require_once(__DIR__ . '/../../config.php');

require_login();

header('Content-Type: application/json; charset=utf-8');

global $DB, $USER;

function securecoursehub_json_error(int $statuscode, string $message): void {
	http_response_code($statuscode);
	echo json_encode(['success' => false, 'error' => $message]);
	exit;
}

function securecoursehub_course_context_from_record(stdClass $record): context_course {
	$course = get_course($record->courseid);
	return context_course::instance($course->id);
}

try {
	$payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
	securecoursehub_json_error(400, get_string('invalidrequest', 'local_securecoursehub'));
}

if (!is_array($payload) || empty($payload['action'])) {
	securecoursehub_json_error(400, get_string('invalidrequest', 'local_securecoursehub'));
}

if (empty($payload['sesskey']) || !confirm_sesskey($payload['sesskey'])) {
	securecoursehub_json_error(403, get_string('invalidsesskey', 'local_securecoursehub'));
}

switch ($payload['action']) {
	case 'create_request':
		$courseid = isset($payload['courseid']) ? (int) $payload['courseid'] : 0;
		$title = isset($payload['title']) ? clean_param($payload['title'], PARAM_TEXT) : '';
		$description = isset($payload['description']) ? clean_param($payload['description'], PARAM_TEXT) : '';
		if ($courseid <= 0 || $title === '' || $description === '') {
			securecoursehub_json_error(400, get_string('invalidrequest', 'local_securecoursehub'));
		}
		$course = get_course($courseid);
		$context = context_course::instance($course->id);
		require_login($course);
		if (!has_capability('local/securecoursehub:createrequest', $context)) {
			securecoursehub_json_error(403, get_string('accessdenied', 'local_securecoursehub'));
		}
		$requestid = $DB->insert_record('local_securecoursehub_req', (object) [
			'courseid' => $course->id,
			'userid' => $USER->id,
			'title' => $title,
			'description' => $description,
			'status' => 'open',
			'response' => null,
			'timecreated' => time(),
			'timemodified' => time(),
		]);
		echo json_encode([
			'success' => true,
			'message' => get_string('requestcreated', 'local_securecoursehub'),
			'id' => $requestid,
			'userid' => $USER->id,
		]);
		exit;

	case 'update_status':
		$requestid = isset($payload['id']) ? (int) $payload['id'] : 0;
		$newstatus = isset($payload['status']) ? clean_param($payload['status'], PARAM_ALPHANUMEXT) : '';
		if ($requestid <= 0 || $newstatus === '') {
			securecoursehub_json_error(400, get_string('invalidrequest', 'local_securecoursehub'));
		}
		$request = $DB->get_record('local_securecoursehub_req', ['id' => $requestid], '*', IGNORE_MISSING);
		if (!$request) {
			securecoursehub_json_error(404, get_string('requestnotfound', 'local_securecoursehub'));
		}
		$context = securecoursehub_course_context_from_record($request);
		require_login(get_course($request->courseid));
		if (has_capability('local/securecoursehub:manageall', context_system::instance()) || has_capability('local/securecoursehub:managecourserequests', $context)) {
			$DB->set_field('local_securecoursehub_req', 'status', $newstatus, ['id' => $request->id]);
			$DB->set_field('local_securecoursehub_req', 'timemodified', time(), ['id' => $request->id]);
			echo json_encode([
				'success' => true,
				'message' => get_string('statusupdated', 'local_securecoursehub'),
				'user' => fullname($USER),
			]);
			exit;
		}
		if ((int) $request->userid !== (int) $USER->id) {
			securecoursehub_json_error(403, get_string('accessdenied', 'local_securecoursehub'));
		}
		$DB->set_field('local_securecoursehub_req', 'status', $newstatus, ['id' => $request->id]);
		$DB->set_field('local_securecoursehub_req', 'timemodified', time(), ['id' => $request->id]);
		echo json_encode([
			'success' => true,
			'message' => get_string('statusupdated', 'local_securecoursehub'),
			'user' => fullname($USER),
		]);
		exit;

	default:
		securecoursehub_json_error(400, get_string('invalidrequest', 'local_securecoursehub'));
}
