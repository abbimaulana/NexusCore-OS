<?php
/**
 * NexusCore OS — logout.php
 * Destroys the admin session and redirects to the login page.
 */

session_start();
session_unset();
session_destroy();

// Use a plain redirect (config.php not strictly needed here)
header('Location: gate-access.php');
exit;
