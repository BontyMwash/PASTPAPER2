<?php
echo "<h1>Simple Test Page</h1>";
echo "<p>This is a test page with a simple name to verify web server access.</p>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>Server name: " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
?>