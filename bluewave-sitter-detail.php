<? // bluewave-sitter-detail.php

$n = $_GET['n'] ? $_GET['n'] : 1;
$file = file_get_contents("/var/data/clientimports/pawsagogo/PAGG-sitter-detail-$n.htm");

// "w_str_dtl.cfm?str_id=8"

$matches = array();
if(preg_match('"w_str_dtl.cfm\?str_id=[\d]*"', $file, $matches)) 
	$bwsitterid = substr($matches[0], strpos($matches[0], '=')+1);
echo "BW Sitter: $bwsitterid<p>";

$matches = array();
if(preg_match('<input name="str_fl_nm" type="text"  value=".*" size="32">', $file, $matches)) {
	$match = $matches[0];
	$start = strpos($match, 'value="')+strlen('value="');
	$nickname = substr($match, $start, strpos($match, '"', $start)-$start);
}
echo "Nickname: $nickname<p>";

$matches = array();
if(preg_match('<input type="text" name="str_grphy" value=".*" size="32">', $file, $matches)) {
	$match = $matches[0];
	$start = strpos($match, 'value="')+strlen('value="');
	$area = substr($match, $start, strpos($match, '"', $start)-$start);
}
echo "Area: $area<p>";

$matches = array();
if(preg_match('<input name="dob" type="text" id="dob" value=".*">', $file, $matches)) {
	$match = $matches[0];
	$start = strpos($match, 'value="')+strlen('value="');
	$dob = substr($match, $start, strpos($match, '"', $start)-$start);
}
echo "DOB: $dob<p>";

$matches = array();
$pattern = '<select name="f_cmp_mthd" class="formtext" id="f_cmp_mthd">';
if(preg_match($pattern, $file, $matches, PREG_OFFSET_CAPTURE)) {
	//print_r($matches);
	$start = $matches[0][1]+strlen($pattern);
	$select = substr($file, $start, strpos($file, '</select>', $start)-$start);
	//echo "SELECT: ".htmlentities($select).'<p>';
	$matches = array();
	preg_match('<option .*option>', $select, $matches);
	for($i=0;$i<count($matches); $i++) if(strpos($matches[$i], 'selected')) $selected = $matches[$i];
	//$start = strpos($match, 'value="')+strlen('value="');
	//$dob = substr($match, $start, strpos($match, '"', $start)-$start);
	$matches = array();
	preg_match('/value="(?P<type>.*)"/', $selected, $matches);
	$paytype = $matches['type'];
}
//echo "Pay Type: ".($paytype == 'P' ? 'percentage' : 'dollars).'<p>';


$matches = array();
$pattern = '/<td colspan="2" valign="middle"><input name="str_cmpnstn" type="text" id="str_cmpnstn"  value="(?P<comp>.*)" size="10">/';
if(preg_match($pattern, $file, $matches))
	$rate = $matches['comp'];
echo "Rate: ".$rate.' '.($paytype == 'P' ? '%' : 'dollars').'<p>';


$matches = array();
$pattern = '/<input name="str_cd" type="text" value="(?P<username>.*)"  size="32">/';
if(preg_match($pattern, $file, $matches))
	$loginid = $matches['username'];
echo "Login ID: ".$loginid.'<p>';


$matches = array();
$pattern = '<textarea name="str_rmrk" cols="60" rows="5" class="formtext">';
if(preg_match($pattern, $file, $matches, PREG_OFFSET_CAPTURE)) {
	//print_r($matches);
	$start = $matches[0][1]+strlen($pattern)-1;
	$notes = substr($file, $start, strpos($file, '</textarea>', $start)-$start);
}
echo "Remarks: ".$notes.'<p>';
