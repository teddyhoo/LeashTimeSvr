<? // cc-primary-set.php
// ajax
// id=clientid
// choice = CC|ACH
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "cc-processing-fns.php";
if($_GET['id']) locked('o-');
else locked('c-');
$failure = false;
if(userRole() == 'o' && !adequateRights('*cm')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
	$failure = "Insufficient Access Rights";
}
if($failure) {
	echo 'Insufficient Access Rights';
	exit;
}
//print_r($_GET);
$clientid = userRole() == 'c' ? $_SESSION["clientid"] : $_GET['id'];
$choices = array('CC'=>'credit card', 'ACH'=>'bank account');
$name = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient where clientid = $clientid LIMIT 1", 1);
$source = $_GET['choice'] == 'CC' ? getClearCC($clientid) : getClearACH($clientid);
if(is_array($source)) {
	setPrimaryPaySource($source);
	echo "$name's {$choices[$_GET['choice']]} has been set as the primary payment source";
}
else echo $source;