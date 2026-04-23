<?php
/**
 * profile/sync.php
 * Lightweight endpoint called ONCE per app open.
 * Handles: location sync, last_active update, and daily credit refresh.
 * Previously these 2 DB writes happened on every get_users.php request — moved here.
 */
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db     = getDB();

// Run the sync (location + last_active + daily credits)
autoSyncUserMeta($userId, $db);

$db->close();

echo json_encode(['status' => 'success']);
