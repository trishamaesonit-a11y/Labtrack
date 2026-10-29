<?php
require __DIR__ . '/../includes/layout.php';
require_role(['admin']);
$area = 'admin'; $active = 'profile';
require __DIR__ . '/../includes/profile_form.php';
