<?php
// Root entry point for the application.
// Redirect to the existing login page so platforms like Railpack can detect this as a PHP project.
header('Location: login.php');
exit;
