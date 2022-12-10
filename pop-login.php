<?php // pop-login.php
// params: user_name, user_pass

define("DEBUG",true);
$logChangeTable = 'pop-login';
if(DEBUG) $_GET['expected_role'] = 'c';
else $_POST['expected_role'] = 'c';
require_once "mmd-login.php";
