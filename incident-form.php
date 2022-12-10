<? // incident-form.php

$params = explode('&', $_SERVER["QUERY_STRING"]);
foreach($params as $pair) {
	$pair = explode('=', $pair);
	if($pair[0] == 'id')
		$id = $pair[1];
}
if($pair) {
	if($_SERVER["SCRIPT_NAME"] == "/client-edit.php") $_REQUEST["clientcontext"] = $pair;
	else if($_SERVER["SCRIPT_NAME"] == "/provider-edit.php") $_REQUEST["providercontext"] = $pair;
}
$_REQUEST['id'] = 'f8';  // figure out best way to designate 
require "survey-form.php";