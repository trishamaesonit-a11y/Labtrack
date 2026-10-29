<?php
require __DIR__ . '/../includes/layout.php';
require_role(['student', 'staff']);
$area = 'user'; $active = 'profile';
require __DIR__ . '/../includes/profile_form.php';
