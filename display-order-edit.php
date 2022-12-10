<? // display-order-edit.php
// itemList:value|label|value|label
// listSeparator (optional)
// title
// numbered
// instructions
// saveAction (page to accept resulting list) e.g., save-service-order.php?order=21,13,9...

require_once "gui-fns.php";
require_once "dragsortJQ.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$windowTitle = isset($title) ? $title : 'Edit Item Order';
$numbered = isset($numbered) ? $numbered : '1';
$sortListName = 'dragsortlist';
$extraHeadContent = headerInsert($sortListName);
require "frame-bannerless.php";

echo "<h2>$windowTitle</h2>\n";
$instructions = isset($instructions) ? $instructions : "Drag and drop items to reorder this list.";
echo $instructions."<p><p>";
$test = isset($test) ? 1 : 0;
$testtarget = isset($test) ? "\"TEST\"" : 'null';
echoButton('', 'Save List', "ajaxGetAndCallWith(\"$saveAction\"+getListOrder(), postSave, 0);");
echo "<p>";
echo "<div id='TEST'></div>";  // \"TEST\"
$listSeparator = isset($listSeparator) ? $listSeparator : '|';
if(!is_array($itemList)) {
	$parts = explode($listSeparator, $itemList);
	$itemList = array();
	for($i=0; $i<count($parts); $i+=2) $itemList[$parts[$i]] = $parts[$i+1];
}
echo "<style>li {border: solid black 1px;background:white;}</style>";
echo "<div>";
echoSortList($itemList, $sortListName, $numbered=false);
echo "</div><p>\n";
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function postSave(unused, content) {
	window.opener.update();
	window.close();
}
</script>
</body>
</html>
