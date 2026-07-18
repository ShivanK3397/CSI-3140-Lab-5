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
