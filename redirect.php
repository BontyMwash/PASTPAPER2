<?php
// This script helps diagnose URL access issues

echo "<h1>URL Diagnostic Tool</h1>";

echo "<h2>Server Information:</h2>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>Document root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Server name: " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Script name: " . $_SERVER['SCRIPT_NAME'] . "</p>";

echo "<h2>Try these URLs:</h2>";
echo "<ul>";
echo "<li><a href='http://localhost/PastPaper%20Web/'>http://localhost/PastPaper%20Web/</a></li>";
echo "<li><a href='http://localhost/PastPaper_Web/'>http://localhost/PastPaper_Web/</a></li>";
echo "<li><a href='http://localhost/PastPaperWeb/'>http://localhost/PastPaperWeb/</a></li>";
echo "<li><a href='http://localhost/PastPaper-Web/'>http://localhost/PastPaper-Web/</a></li>";
echo "</ul>";

echo "<h2>Available Files:</h2>";
echo "<ul>";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "<li><a href='$file'>$file</a></li>";
    }
}
echo "</ul>";
?>