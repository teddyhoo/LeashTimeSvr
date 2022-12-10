<? // set-flag.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "encryption.php";
require_once "preference-fns.php";

if(userRole() == 'd') $locked = locked('d-');
else $locked = locked('o-');


// execute: param: nugget, an encrypted key-value pair created as follows
// urlencode(lt_encrypt($keyvaluepair))
// where keyvaluepair is an array like {"key":"value"}
// optional: "scope":"session"|"preference"|"userpreference"


if($_GET['nugget']) {
	$keyValues = json_decode(lt_decrypt($_GET['nugget']), 'assoc');
	$scope = $keyValues['scope'];
if(mattOnlyTEST()) 	{
	echo "is_string: (".is_string($_GET['nugget']).") :".print_r($_GET['nugget'], 1);
	echo "<p>base64: ".base64_decode($_GET['nugget']);
	echo "<hr>";
	echo "SCOPE: $scope<hr>";
	print_r(lt_decrypt($_GET['nugget']));
	echo "<hr>KeyValues: ".print_r($keyValues, 1);
}

	foreach($keyValues as $k => $v) {
		if($k == 'scope') continue;
		if($scope == 'preference')
			setPreference($k, $v);
		else if($scope == 'userpreference')
			setUserPreference($_SESSION['auth_user_id'], $k, $v);
		else if($scope == 'session')
			$_SESSION['sessionpreferences'][$k] = $v;
	}
}
