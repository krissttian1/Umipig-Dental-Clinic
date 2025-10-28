<?php
// Optional: Get the name if you need it for later
$name = $_GET['name'] ?? null;

// Redirect to home.html
header("Location: home.php");
exit; // Always call exit after a redirect to stop further script execution
?>
