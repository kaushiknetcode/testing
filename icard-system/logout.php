<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect to main page (go back one directory since we're in auth folder)
header('Location: ../index.php');
exit();
?>