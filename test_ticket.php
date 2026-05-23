<?php
// Mock server environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'student_submit_ticket',
    'student_email' => 'test@example.com',
    'subject' => 'Support Request',
    'message' => 'I have a problem with something.'
];

// Include api.php
ob_start();
include 'api.php';
$output = ob_get_clean();

echo "OUTPUT:\n$output\n";
