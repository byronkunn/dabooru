<?php

return [
    // An existing administrator designated to grant or revoke administrator access.
    'owner_id' => (int) env('ADMIN_OWNER_ID', 0),
    'urgent_report_email' => env('ADMIN_URGENT_REPORT_EMAIL'),
];
