<?php
require __DIR__ . '/includes/connection.php';
$_SESSION = [];
session_destroy();
redirect('login.php');
