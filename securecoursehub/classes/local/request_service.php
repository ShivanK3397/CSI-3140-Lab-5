<?php
// local/securecoursehub/classes/local/request_service.php
// Handles all database operations for the Secure Course Hub plugin.
// All methods use the Moodle Database API — no raw SQL concatenation.

defined('MOODLE_INTERNAL') || die();

class local_securecoursehub_request_service {

    // Allowed status values — used for validation across create and update operations.
    private const ALLOWED_STATUSES = ['open', 'inprogress', 'resolved'];

    // Maximum character length for title and description fields.
    private const MAX_TITLE_LENGTH       = 100;
    private const MAX_DESCRIPTION_LENGTH = 1000;
    private const MAX_RESPONSE_LENGTH    = 500;

    // ---------------------------------------------------------------
    // CREATE — insert a new request for the authenticated student.
    // Validates courseid, title, description, and initial status.
    // Records the authenticated userid — never trusts a client value.
    // ---------------------------------------------------------------
    public static function create_request(
        int $courseid,
        int $userid,
        string $title,
        string $description
    ): int {
        global $DB;

        // Presence and length validation.
        if ($courseid <= 0) {
            throw new invalid_parameter_exception(
                get_string('invalidrequest', 'local_securecoursehub')
            );
        }

        $title       = trim($title);
        $description = trim($description);

        if ($title === '' || $description === '') {
            throw new invalid_parameter_exception(
                get_string('invalidrequest', 'local_securecoursehub')
            );
        }

        if (core_text::strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new invalid_parameter_exception(
                get_string('titletoolong', 'local_securecoursehub')
            );
        }

        if (core_text::strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new invalid_parameter_exception(
                get_string('descriptiontoolong', 'local_securecoursehub')
            );
        }

        // Confirm the course exists before writing.
        get_course($courseid); // Throws dml_exception if missing.

        $now = time();

        $record = (object) [
            'courseid'     => $courseid,
            'userid'       => $userid,
            'title'        => $title,
            'description'  => $description,
            'status'       => 'open',
            'response'     => null,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        return (int) $DB->insert_record('local_securecoursehub_req', $record);
    }

    // ---------------------------------------------------------------
    // READ OWN — return only records belonging to the current user.
    // ---------------------------------------------------------------
    public static function get_own_requests(int $userid, int $courseid): array {
        global $DB;

        if ($userid <= 0 || $courseid <= 0) {
            return [];
        }

        return $DB->get_records(
            'local_securecoursehub_req',
            ['userid' => $userid, 'courseid' => $courseid],
            'timecreated DESC'
        );
    }

    // ---------------------------------------------------------------
    // READ COURSE — return all requests for a course.
    // Caller must have already verified managecourserequests capability.
    // ---------------------------------------------------------------
    public static function get_course_requests(int $courseid, string $statusfilter = ''): array {
        global $DB;

        if ($courseid <= 0) {
            return [];
        }

        $conditions = ['courseid' => $courseid];

        if ($statusfilter !== '' && in_array($statusfilter, self::ALLOWED_STATUSES, true)) {
            $conditions['status'] = $statusfilter;
        }

        return $DB->get_records(
            'local_securecoursehub_req',
            $conditions,
            'timecreated DESC'
        );
    }

    // ---------------------------------------------------------------
    // READ ONE — fetch a single record by id.
    // Returns null when the record does not exist.
    // ---------------------------------------------------------------
    public static function get_request(int $requestid): ?stdClass {
        global $DB;

        if ($requestid <= 0) {
            return null;
        }

        $record = $DB->get_record(
            'local_securecoursehub_req',
            ['id' => $requestid],
            '*',
            IGNORE_MISSING
        );

        return $record ?: null;
    }

    // ---------------------------------------------------------------
    // UPDATE (STUDENT) — student may edit title/description of their
    // own open request only. Resolved requests cannot be changed.
    // ---------------------------------------------------------------
    public static function student_update_request(
        int $requestid,
        int $userid,
        string $title,
        string $description
    ): bool {
        global $DB;

        $record = self::get_request($requestid);

        if ($record === null) {
            throw new moodle_exception(
                'requestnotfound',
                'local_securecoursehub'
            );
        }

        // Ownership check — student may only edit their own record.
        if ((int) $record->userid !== $userid) {
            throw new required_capability_exception(
                context_course::instance($record->courseid),
                'local/securecoursehub:managecourserequests',
                'nopermissions',
                ''
            );
        }

        // Only open requests may be edited by a student.
        if ($record->status !== 'open') {
            throw new moodle_exception(
                'cannoteditresolved',
                'local_securecoursehub'
            );
        }

        $title       = trim($title);
        $description = trim($description);

        if ($title === '' || $description === '') {
            throw new invalid_parameter_exception(
                get_string('invalidrequest', 'local_securecoursehub')
            );
        }

        if (core_text::strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new invalid_parameter_exception(
                get_string('titletoolong', 'local_securecoursehub')
            );
        }

        if (core_text::strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new invalid_parameter_exception(
                get_string('descriptiontoolong', 'local_securecoursehub')
            );
        }

        $DB->update_record('local_securecoursehub_req', (object) [
            'id'           => $record->id,
            'title'        => $title,
            'description'  => $description,
            'timemodified' => time(),
        ]);

        return true;
    }

    // ---------------------------------------------------------------
    // UPDATE STATUS (TEACHER) — teacher may update status and response
    // for records in an authorized course. Validates allowed statuses
    // and response length before writing.
    // ---------------------------------------------------------------
    public static function teacher_update_status(
        int $requestid,
        string $newstatus,
        string $response = ''
    ): bool {
        global $DB;

        if (!in_array($newstatus, self::ALLOWED_STATUSES, true)) {
            throw new invalid_parameter_exception(
                get_string('invalidstatus', 'local_securecoursehub')
            );
        }

        $record = self::get_request($requestid);

        if ($record === null) {
            throw new moodle_exception(
                'requestnotfound',
                'local_securecoursehub'
            );
        }

        $response = trim($response);

        if (core_text::strlen($response) > self::MAX_RESPONSE_LENGTH) {
            throw new invalid_parameter_exception(
                get_string('responsetoolong', 'local_securecoursehub')
            );
        }

        $DB->update_record('local_securecoursehub_req', (object) [
            'id'           => $record->id,
            'status'       => $newstatus,
            'response'     => $response !== '' ? $response : null,
            'timemodified' => time(),
        ]);

        return true;
    }

    // ---------------------------------------------------------------
    // DELETE — owner may delete their own open request.
    // Teacher/manager may delete any record in authorized course.
    // ---------------------------------------------------------------
    public static function delete_request(
        int $requestid,
        int $userid,
        bool $ismanager
    ): bool {
        global $DB;

        $record = self::get_request($requestid);

        if ($record === null) {
            throw new moodle_exception(
                'requestnotfound',
                'local_securecoursehub'
            );
        }

        $isowner = ((int) $record->userid === $userid);

        // Students may only delete their own open requests.
        if (!$ismanager) {
            if (!$isowner) {
                throw new required_capability_exception(
                    context_course::instance($record->courseid),
                    'local/securecoursehub:managecourserequests',
                    'nopermissions',
                    ''
                );
            }

            if ($record->status !== 'open') {
                throw new moodle_exception(
                    'cannoteditresolved',
                    'local_securecoursehub'
                );
            }
        }

        $DB->delete_records('local_securecoursehub_req', ['id' => $record->id]);

        return true;
    }

    // ---------------------------------------------------------------
    // OWNERSHIP CHECK helper — returns true when userid owns record.
    // ---------------------------------------------------------------
    public static function user_owns_request(int $requestid, int $userid): bool {
        $record = self::get_request($requestid);
        if ($record === null) {
            return false;
        }
        return ((int) $record->userid === $userid);
    }
}