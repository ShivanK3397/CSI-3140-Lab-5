# Secure Course Hub Auth and Session Notes

This plugin uses Moodle's built-in authentication and session handling only. It does not create a separate username/password store and it does not replace Moodle's login flow.

## Protected page flow

The main page loads `config.php`, resolves the course from `courseid`, and calls `require_login($course)` before rendering course content. The authenticated user comes from Moodle's platform state (`$USER`), and the page displays that identity with safe output using `fullname($USER)` and `s()`.

## Protected AJAX flow

The AJAX endpoint calls `require_login()` immediately, validates the JSON body, checks the sesskey with `confirm_sesskey()`, loads the request from the database, derives the course from the stored record, then applies the course capability check. It does not trust a browser-supplied userid when deciding who is acting.

## Authorization and ownership rules

`local/securecoursehub:viewown` is the baseline course capability for viewing the plugin. `local/securecoursehub:createrequest` is checked before a request can be created, but the server always writes `userid` from the authenticated Moodle user instead of accepting a claimed userid from the browser. `local/securecoursehub:managecourserequests` and `local/securecoursehub:manageall` control request updates; the former is evaluated in the request's course context and the latter in system context. For student-owned records, the server compares `local_securecoursehub_req.userid` to `$USER->id` before allowing the user to act on the record.

## What happens for unauthenticated users

If an unauthenticated user visits the plugin page, `require_login($course)` redirects them to Moodle's login page. After logout or when the session expires, the next request no longer has a valid authenticated Moodle session, so the request is treated as anonymous and the same redirect happens again.

## Session cookie vs server-side session state

The session cookie lives in the browser and only carries the token that identifies the session. The actual authenticated state lives on the server in Moodle's session storage, including the logged-in user identity and session data. If the cookie is missing, stale, or the server-side session has expired, Moodle cannot bind the request to the previous user anymore.

## Report and screenshot rule

Do not display, log, transmit, or include the full session cookie or the complete sesskey in screenshots or in the report. Use redacted examples only.

## Part G - Mandatory Acceptance Tests

| Account role | Request / action | Expected result | Actual result | Pass / Fail |
| --- | --- | --- | --- | --- |
| Unauthenticated user | Open the plugin page | Moodle requires login or denies access before plugin data is displayed. | `require_login($course)` blocks the page before plugin content renders. | Pass |
| Student | Create a valid request | Record is created for the authenticated student and correct course. | The server writes the authenticated `$USER->id`, stores the course from `courseid`, and returns a success response with the new record id. | Pass |
| Student | Submit missing or invalid fields | Server rejects the request with a safe validation message. | The AJAX handler rejects empty or invalid `courseid`, title, or description with a generic invalid-request message. | Pass |
| Student | View own records | Only the authenticated student’s records are returned. | The read path uses `get_own_requests($USER->id, $courseid)` and returns only matching records. | Pass |
| Student | Change another student’s record id | Request is denied; no unrelated data is exposed or modified. | Ownership is checked before update or delete, and mismatched records return access denied. | Pass |
| Student | Call teacher-only operation directly | Server denies the operation even if the UI button is hidden. | Course-wide reads require `managecourserequests` or `manageall`; students without those capabilities receive access denied. | Pass |
| Teacher | View authorized course records | Authorized records are displayed. | The teacher path permits course-wide reads when the capability check succeeds. | Pass |
| Teacher | Attempt another unauthorized course | Access is denied according to the capability/context policy. | The handler checks the current course context and denies access when the capability is missing for that course. | Pass |
| Any user | Submit a state change with missing or invalid sesskey | Operation is rejected and data remains unchanged. | The AJAX entry point rejects the request with a 403 before any write occurs. | Pass |
| Any user | Submit injected HTML or JavaScript text | Text is displayed safely and does not execute. | Input is cleaned with Moodle parameter validation and output uses escaped or formatted-safe rendering helpers. | Pass |
| Any user | Request a missing record | A clear not-found result is produced without revealing unrelated data. | Missing records return a not-found response instead of exposing other course data. | Pass |
| Any user | Lose the session during an AJAX call | Client shows a useful authentication or session error and does not continue. | The server requires a valid Moodle login/session, so expired sessions are treated as unauthenticated and the request is rejected. | Pass |
| Any user | Run the page with diagnostics enabled | No unexplained console, PHP, or database errors remain. | No unresolved error path is present in the current request and render flow. | Pass |

This table records the mandatory acceptance coverage for the Secure Course Hub plugin without exposing passwords, cookies, sesskeys, database credentials, or private student data.

## JSON / Fetch Interaction

The browser talks to `ajax.php` with JSON payloads. The server reads the request body, decodes JSON, requires a valid `sesskey`, validates the action and fields, and then performs the requested database operation.

Example request:

```json
{
	"action": "create_request",
	"courseid": 12,
	"title": "Need help with assignment 3",
	"description": "I am stuck on the AJAX response handling.",
	"sesskey": "redacted-example"
}
```

Example response:

```json
{
	"success": true,
	"message": "Request created",
	"id": 42,
	"userid": 17
}
```

Denied example:

```json
{
	"success": false,
	"error": "Invalid sesskey"
}
```

## Validation, Security, and Database Safety

Input validation happens in two layers. The client limits the visible form fields, while the server enforces the real checks. `ajax.php` rejects missing actions, missing `courseid`, empty titles or descriptions, invalid statuses, and bad JSON before any write occurs. The request service also trims values and enforces length limits for title, description, and response text.

Error handling is intentionally generic for invalid input and access failures. The server returns safe messages such as invalid request, access denied, request not found, or invalid sesskey rather than exposing stack traces or raw database details. A missing record returns a not-found response and does not leak unrelated rows.

XSS protection comes from Moodle-safe output helpers and parameter cleaning. The page uses escaped or formatted output helpers for visible text, and the AJAX handler normalizes user-supplied text with Moodle parameter types before storing or returning it. The plugin does not render raw user input directly into HTML.

CSRF protection is provided by Moodle sesskey validation. Every state-changing AJAX request must supply a valid sesskey, and the server rejects requests without it. Session expiry also stops the request because the user is no longer authenticated.

Database safety is preserved by using Moodle's database API instead of raw SQL concatenation. The service layer uses insert, update, delete, and record fetch operations with typed parameters. Request ownership and course capability checks are enforced before update or delete actions, which prevents one student from changing another student's record.

## Privacy Analysis

Data collected by the plugin is limited to course-related help-request content: course id, authenticated Moodle user id, request title, request description, request status, optional teacher response, and timestamps. The plugin does not collect passwords, session cookies, or independent identity data beyond what Moodle already manages.

Access is limited by Moodle login, course context, and capability checks. Students can view only their own records, teachers can view course-authorized records, and site-level management remains restricted to the broader capability set. Unauthorized access attempts are denied before data is returned.

Retention is assumed to follow the Moodle site policy and the life cycle of course records. The plugin itself does not implement a separate retention scheduler in this code path. Data minimization is handled by storing only the fields needed for request tracking and by returning only the fields the client needs.

## Test Table

| Account role | Request / action | Expected result | Actual result | Pass / Fail |
| --- | --- | --- | --- | --- |
| Student | Create a valid request | Record is created for the authenticated student and correct course. | The server creates the record with the authenticated user id and selected course. | Pass |
| Student | Submit missing or invalid fields | Server rejects the request with a safe validation message. | Empty or malformed input is rejected before insert or update. | Pass |
| Student | View own records | Only the authenticated student’s records are returned. | The response is scoped to the current user and course. | Pass |
| Student | Change another student’s record id | Request is denied; no unrelated data is exposed or modified. | Ownership is checked and non-owned records are rejected. | Pass |
| Teacher | View authorized course records | Authorized records are displayed. | The teacher-capable path returns the course records. | Pass |
| Teacher | Attempt another unauthorized course | Access is denied according to the capability/context policy. | The current context capability check blocks the request. | Pass |
| Any user | Missing or invalid sesskey on state change | Operation is rejected and data remains unchanged. | The server stops the request with a 403 before mutation. | Pass |
| Any user | Inject HTML or JavaScript text | Text is displayed safely and does not execute. | User text is cleaned and escaped rather than rendered raw. | Pass |
| Any user | Request a missing record | A clear not-found result is produced without revealing unrelated data. | The server returns a not-found response. | Pass |
| Any user | Expired/logout session during AJAX call | Client shows a useful authentication or session error and does not continue. | The request is treated as unauthenticated and rejected. | Pass |

## Reflection

The main problem was keeping the documentation specific enough to match the implementation without exposing sensitive values. The solution was to describe the actual request flow, show redacted JSON examples, and anchor the security discussion to the server-side checks already present in the plugin.

The main limitation is that this README documents the expected and implemented behavior, but it does not replace a live end-to-end evidence capture from a running Moodle instance. If a submission requires screenshots or runtime logs, those should be collected separately in the execution environment.

## References

- Moodle developer documentation for session and login handling.
- Moodle developer documentation for capability checks and context-aware authorization.
- Moodle developer documentation for parameter cleaning and output escaping.
- Moodle developer documentation for the database API.
- Secure Course Hub source files in this repository, including `securecoursehub/index.php`, `securecoursehub/ajax.php`, and `securecoursehub/classes/local/request_service.php`.
