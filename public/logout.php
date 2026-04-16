<?php
require_once __DIR__ . '/../src/config.php';
session_destroy();
header('Location: login.php');
exit;
