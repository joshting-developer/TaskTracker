<?php

return [
    'disk' => env('ATTACHMENTS_DISK', 'attachments'),
    'max_bytes' => (int) env('ATTACHMENTS_MAX_BYTES', 10 * 1024 * 1024),
    'max_pixels' => (int) env('ATTACHMENTS_MAX_PIXELS', 25000000),
    'per_issue' => (int) env('ATTACHMENTS_PER_ISSUE', 20),
    'pending_per_user' => (int) env('ATTACHMENTS_PENDING_PER_USER', 20),
    'workspace_bytes' => (int) env('ATTACHMENTS_WORKSPACE_BYTES', 1024 * 1024 * 1024),
    'pending_hours' => (int) env('ATTACHMENTS_PENDING_HOURS', 24),
    'retention_days' => (int) env('ATTACHMENTS_RETENTION_DAYS', 30),
];
