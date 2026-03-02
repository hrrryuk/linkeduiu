<?php
// sync_trigger.php — Trigger sync from web with secret key protection
// Usage: https://yourdomain.com/sync_trigger.php?key=YOUR_SECRET_KEY

// Secret key!
define('SYNC_SECRET_KEY', 'admin_hrr_0112320004');

// Check for valid key in GET parameter
if (!isset($_GET['key']) || $_GET['key'] !== SYNC_SECRET_KEY) {
    http_response_code(403);
    exit("Access denied. Invalid or missing key.\n");
}

// Load DB connections
require_once '../portal_db.php';
require_once '../university_db.php';

// Load sync logic
require_once 'sync.php';

// Run sync
run_sync($portal, $university);
