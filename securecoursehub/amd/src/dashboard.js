// amd/src/dashboard.js — uOttawa Chess Club Secure Course Hub
// Handles all client-side API interactions using fetch().
// Communicates with ajax.php using JSON request and response bodies.
// CSRF protection is included via M.cfg.sesskey on every state-changing call.

// ---------------------------------------------------------------
// Section 1: Utility — build the ajax.php endpoint URL
// Uses Moodle's M.cfg.wwwroot so the path is always correct
// regardless of the Moodle installation subfolder.
// ---------------------------------------------------------------
function getEndpoint() {
    return M.cfg.wwwroot + '/local/securecoursehub/ajax.php';
}

// ---------------------------------------------------------------
// Section 2: Utility — display a visible feedback message
// Finds #sch-message and sets its text and class without innerHTML
// to prevent XSS from untrusted server text.
// ---------------------------------------------------------------
function showMessage(text, type) {
    const box = document.querySelector('#sch-message');
    if (!box) return;
    box.textContent = text;           // textContent — never innerHTML
    box.className = 'alert alert-' + (type === 'error' ? 'danger' : 'success');
    box.removeAttribute('hidden');
}

function hideMessage() {
    const box = document.querySelector('#sch-message');
    if (!box) return;
    box.textContent = '';
    box.setAttribute('hidden', 'hidden');
}

// ---------------------------------------------------------------
// Section 3: Utility — safe fetch wrapper
// Handles network failure, non-OK HTTP status, invalid JSON,
// and expired/logged-out sessions uniformly.
// ---------------------------------------------------------------
async function safeFetch(payload) {
    let response;

    try {
        response = await fetch(getEndpoint(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
    } catch (networkError) {
        // Network failure — server unreachable or connection dropped.
        throw new Error('Network error: could not reach the server.');
    }

    // Handle session expiry — Moodle redirects to login with 303/200
    if (response.redirected || response.url.includes('login')) {
        throw new Error('Your session has expired. Please log in again.');
    }

    let result;
    try {
        result = await response.json();
    } catch (parseError) {
        throw new Error('The server returned an unexpected response.');
    }

    // Treat non-OK HTTP status as an error using the server message.
    if (!response.ok || !result.success) {
        throw new Error(result.error || 'The request failed.');
    }

    return result;
}

// ---------------------------------------------------------------
// Section 4: Update status (teacher operation)
// Called when a teacher changes a request status via the dropdown.
// Sends action, id, status, and sesskey. Updates the row in place
// without a full page reload.
// ---------------------------------------------------------------
export async function updateStatus(requestId, newStatus) {
    hideMessage();

    // Client-side validation of allowed status values.
    const allowed = ['open', 'inprogress', 'resolved'];
    if (!allowed.includes(newStatus)) {
        showMessage('Invalid status value.', 'error');
        return;
    }

    try {
        const result = await safeFetch({
            action:  'update_status',
            id:      requestId,
            status:  newStatus,
            sesskey: M.cfg.sesskey,     // CSRF token from Moodle
        });

        // Update the visible status badge in the row without reloading.
        const row = document.querySelector('[data-request-id="' + requestId + '"]');
        if (row) {
            const badge = row.querySelector('.sch-status-badge');
            if (badge) {
                badge.textContent = newStatus;
                badge.className = 'sch-status-badge badge-' + newStatus;
            }
        }

        showMessage(result.message || 'Status updated.', 'success');

    } catch (error) {
        showMessage(error.message, 'error');
    }
}

// ---------------------------------------------------------------
// Section 5: Create a new request (student operation)
// Reads form fields, validates presence client-side, then POSTs.
// On success the new row is prepended to the requests list.
// ---------------------------------------------------------------
export async function createRequest(courseid) {
    hideMessage();

    const titleInput       = document.querySelector('#sch-title');
    const descriptionInput = document.querySelector('#sch-description');

    if (!titleInput || !descriptionInput) return;

    const title       = titleInput.value.trim();
    const description = descriptionInput.value.trim();

    // Client-side presence check — server repeats this validation.
    if (title === '' || description === '') {
        showMessage('Title and description are required.', 'error');
        return;
    }

    if (title.length > 100) {
        showMessage('Title must be 100 characters or fewer.', 'error');
        return;
    }

    if (description.length > 1000) {
        showMessage('Description must be 1000 characters or fewer.', 'error');
        return;
    }

    try {
        const result = await safeFetch({
            action:      'create_request',
            courseid:    courseid,
            title:       title,
            description: description,
            sesskey:     M.cfg.sesskey,
        });

        // Clear the form after success.
        titleInput.value       = '';
        descriptionInput.value = '';

        showMessage(result.message || 'Request created.', 'success');

        // Reload the requests list dynamically without a full page reload.
        await loadOwnRequests(courseid);

    } catch (error) {
        showMessage(error.message, 'error');
    }
}

// ---------------------------------------------------------------
// Section 6: Delete a request (student/owner operation)
// Confirms before sending. Removes the row from the DOM on success.
// ---------------------------------------------------------------
export async function deleteRequest(requestId, courseid) {
    hideMessage();

    if (!window.confirm('Are you sure you want to delete this request?')) {
        return;
    }

    try {
        const result = await safeFetch({
            action:  'delete_request',
            id:      requestId,
            sesskey: M.cfg.sesskey,
        });

        // Remove the row from the DOM without reloading the page.
        const row = document.querySelector('[data-request-id="' + requestId + '"]');
        if (row) {
            row.remove();
        }

        showMessage(result.message || 'Request deleted.', 'success');

    } catch (error) {
        showMessage(error.message, 'error');
    }
}

// ---------------------------------------------------------------
// Section 7: Load own requests (student view)
// Fetches GET-style action via POST with action: 'get_own_requests'.
// Renders results into #sch-request-list using textContent only.
// ---------------------------------------------------------------
export async function loadOwnRequests(courseid) {
    hideMessage();

    const list = document.querySelector('#sch-request-list');
    if (!list) return;

    try {
        const result = await safeFetch({
            action:   'get_own_requests',
            courseid: courseid,
            sesskey:  M.cfg.sesskey,
        });

        list.innerHTML = '';

        if (!result.requests || result.requests.length === 0) {
            const empty = document.createElement('p');
            empty.textContent = 'You have no requests for this course.';
            list.appendChild(empty);
            return;
        }

        result.requests.forEach(function (req) {
            const item = document.createElement('div');
            item.className = 'sch-request-item card mb-2 p-3';
            item.setAttribute('data-request-id', req.id);

            // Title — textContent prevents XSS from stored input.
            const titleEl = document.createElement('strong');
            titleEl.textContent = req.title;

            // Status badge.
            const badge = document.createElement('span');
            badge.className = 'sch-status-badge badge-' + req.status + ' ml-2';
            badge.textContent = req.status;

            // Description.
            const desc = document.createElement('p');
            desc.className = 'mt-1 mb-1';
            desc.textContent = req.description;

            // Delete button — only shown for open requests.
            const actions = document.createElement('div');
            actions.className = 'sch-request-actions mt-2';

            if (req.status === 'open') {
                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn btn-sm btn-danger';
                deleteBtn.textContent = 'Delete';
                deleteBtn.addEventListener('click', function () {
                    deleteRequest(req.id, courseid);
                });
                actions.appendChild(deleteBtn);
            }

            item.appendChild(titleEl);
            item.appendChild(badge);
            item.appendChild(desc);
            item.appendChild(actions);
            list.appendChild(item);
        });

    } catch (error) {
        showMessage(error.message, 'error');
    }
}

// ---------------------------------------------------------------
// Section 8: Load course requests (teacher view)
// Accepts an optional status filter string.
// ---------------------------------------------------------------
export async function loadCourseRequests(courseid, statusfilter) {
    hideMessage();

    const list = document.querySelector('#sch-course-request-list');
    if (!list) return;

    try {
        const result = await safeFetch({
            action:       'get_course_requests',
            courseid:     courseid,
            statusfilter: statusfilter || '',
            sesskey:      M.cfg.sesskey,
        });

        list.innerHTML = '';

        if (!result.requests || result.requests.length === 0) {
            const empty = document.createElement('p');
            empty.textContent = 'No requests found for this filter.';
            list.appendChild(empty);
            return;
        }

        result.requests.forEach(function (req) {
            const item = document.createElement('div');
            item.className = 'sch-request-item card mb-2 p-3';
            item.setAttribute('data-request-id', req.id);

            // Title and badge — textContent prevents XSS.
            const titleEl = document.createElement('strong');
            titleEl.textContent = req.title;

            const badge = document.createElement('span');
            badge.className = 'sch-status-badge badge-' + req.status + ' ml-2';
            badge.textContent = req.status;

            const desc = document.createElement('p');
            desc.className = 'mt-1 mb-1';
            desc.textContent = req.description;

            // Status dropdown for teacher — triggers updateStatus on change.
            const select = document.createElement('select');
            select.className = 'sch-status-select form-control form-control-sm w-auto mt-1';
            select.setAttribute('aria-label', 'Change status');

            ['open', 'inprogress', 'resolved'].forEach(function (status) {
                const option = document.createElement('option');
                option.value = status;
                option.textContent = status;
                if (status === req.status) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            select.addEventListener('change', function () {
                updateStatus(req.id, select.value);
            });

            item.appendChild(titleEl);
            item.appendChild(badge);
            item.appendChild(desc);
            item.appendChild(select);
            list.appendChild(item);
        });

    } catch (error) {
        showMessage(error.message, 'error');
    }
}

// ---------------------------------------------------------------
// Section 9: DOM ready — wire up buttons and load initial data
// ---------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('#securecoursehub-root');
    if (!root) return;

    const perms   = document.querySelector('.securecoursehub-permissions');
    const courseid = perms
        ? parseInt(perms.getAttribute('data-courseid') || '0', 10)
        : 0;

    const canCreate      = perms && perms.getAttribute('data-can-create') === '1';
    const canManageCourse = perms && perms.getAttribute('data-can-manage-course') === '1';

    // Load the correct view based on role.
    if (canManageCourse) {
        loadCourseRequests(courseid, '');

        // Filter buttons for teacher view.
        document.querySelectorAll('.sch-filter-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                loadCourseRequests(courseid, btn.getAttribute('data-status') || '');
            });
        });
    } else {
        loadOwnRequests(courseid);
    }

    // Create request form submission.
    if (canCreate) {
        const submitBtn = document.querySelector('#sch-submit-request');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                createRequest(courseid);
            });
        }
    }
});