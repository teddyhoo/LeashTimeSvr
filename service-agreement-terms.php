<? // service-agreement-terms.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "agreement-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('o-');
extract(extractVars('version', $_REQUEST));
$agreement = str_replace("\n\n", '<p>', getServiceAgreement($version, $withoutTerms=0));
$terms = str_replace("“", '"', $agreement['terms']);
$terms = str_replace("”", '"', $terms);
$terms = str_replace("’", "'", $terms);
if(!$agreement['html']) {
	$terms = str_replace("\n\n", '<p>', $terms);
	$terms = str_replace("\n", '<br>', $terms);
}
echo $terms;