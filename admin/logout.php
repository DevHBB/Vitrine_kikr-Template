<?php
require_once __DIR__ . '/../config.php';
start_session();
$_SESSION=[];session_destroy();
header('Location:'.BASE_URL.'/admin/login.php');exit;
