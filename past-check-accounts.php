<? // past-check-accounts.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

locked('o-');

require "frame-bannerless.php";

$clientptr = $_REQUEST['id'];

$sources = fetchCol0(
	"SELECT sourcereference FROM tblcredit 
	 WHERE payment = 1 AND clientptr = $clientptr
	 	AND payment = 1
	 	AND sourcereference NOT LIKE 'CC:%'
	 	AND sourcereference NOT LIKE 'ACH:%'
	 	AND sourcereference NOT LIKE 'Paypal%'
	 	ORDER BY creditid DESC", 1);
$sources = array_unique($sources); 	
foreach($sources as $i => $source) {
	if(!$i) $source = "<b>$source</b>";
	fauxLink($source, "choose(\"".strip_tags($source)."\")");
	echo "<br>";
}
?>
<script>
function choose(src) {
	window.opener.document.getElementById('sourcereference').value = src;
	window.close();
}
</script>
