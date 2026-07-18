export async function updateStatus(endpoint, requestId, newStatus) {
const response = await fetch(endpoint, {
method: 'POST',
headers: {'Content-Type': 'application/json'},
body: JSON.stringify({
action: 'update_status',
id: requestId,
status: newStatus,
sesskey: M.cfg.sesskey
})
});
const result = await response.json();
if (!response.ok || !result.success) {
throw new Error(result.error || 'The request failed.');
}
return result;
}

//<?php
// Required server-side sequence in ajax.php or an approved external service:
// 1. Load config.php and call require_login().
// 2. Decode and validate the JSON body.
// 3. Validate sesskey before every state-changing operation.
// 4. Establish the course context and check the required capability.
// 5. Check record ownership or authorized course access.
// 6. Use the Moodle Database API to update the record.
// 7. Return a structured JSON success or error response.