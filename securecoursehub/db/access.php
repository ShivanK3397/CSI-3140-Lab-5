<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
'local/securecoursehub:viewown' => [
'captype' => 'read',
'contextlevel' => CONTEXT_COURSE,
'archetypes' => ['student' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
],
'local/securecoursehub:createrequest' => [
'captype' => 'write',
'contextlevel' => CONTEXT_COURSE,
'archetypes' => ['student' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
],
'local/securecoursehub:managecourserequests' => [
'captype' => 'write',
'contextlevel' => CONTEXT_COURSE,
'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
],
'local/securecoursehub:manageall' => [
'captype' => 'write',
'contextlevel' => CONTEXT_SYSTEM,
'archetypes' => ['manager' => CAP_ALLOW]
]
];