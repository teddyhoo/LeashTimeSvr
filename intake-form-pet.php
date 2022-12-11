<? // intake-form-pet.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "intake-form-fns.php";
?>
<style>
.blankline {display:inline;border-bottom:solid black 1px;}
.linetable {display:block;}
.linetable td {padding-top:5px; padding-left:0px;}
.sectionhead {font-weight:bold;}
.newpage { page-break-before: always; }

</style>
<?

echo "<h2 class='newpage'>Pet Intake form"
	.($_REQUEST['clientname'] ? " for client {$_REQUEST['clientname']}" : '')
	.($petNum && $numpets ? " (#$petNum of $numpets)" : '')
	."</h2>";


$space = "<img src='art/spacer.gif' width=30 height=1>";
$cb = "<input type='checkbox'>";
oneLineEntry('Pet Name', 500, false, $pet['name']);
$petTypes = explode('|', $_SESSION['preferences']['petTypes']);
for($i=0;$i<count($petTypes);$i++) if($petTypes[$i] == $pet['type']) $petTypes[$i] = "<b><u>{$pet['type']}</u></b>";
$petTypes = join($space, $petTypes);
lineStart();
echo "<td>".formattedLabel('Pet Type (circle)')."</td><td style='width:600px;padding-left: 20px;'>$petTypes</td>";
lineEnd();

lineStart();
echo "<td>".formattedLabel('Sex')."<td>$space Male ".simpleCheckBox($pet['sex'] == 'm')."$space"."Female ".simpleCheckBox($pet['sex'] == 'f')."$space</td>";
oneLineCheckbox('Neutered/Spayed', 1, $pet['fixed']);
lineEnd();

lineStart();
oneLineEntry('Breed', 300, true, $pet['breed']);echo " ";
oneLineEntry('Color', 100, true, $pet['color']);
lineEnd();
oneLineEntry('Birthday ('.getI18Property('shortdateformat', 'm/d/Y').')', 100, true, ($pet['dob'] ? shortDate(strtotime($pet['dob'])) : ''));
oneLineEntry('Description', 500, false, $pet['description']);
echo "<p>";

textBox('Notes', $width=700, $height=100, $pet['notes']);

$fields = array();
for($i=1;isset($_SESSION['preferences']["petcustom$i"]); $i++) $fields[] = $_SESSION['preferences']["petcustom$i"];

$fields = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE 'petcustom%'");
$order = array_keys(customFieldDisplayOrder('petcustom'));
 
$petCustom = fetchKeyValuePairs("SELECT fieldname, value FROM relpetcustomfield WHERE petptr = '{$pet['petid']}'");

foreach($order as $key) {
	$field = $fields[$key];
	$descr = explode('|', $field);
	if(!$descr[1] || !$descr[3]) continue; // private
	if($descr[2] == 'oneline') oneLineEntry($descr[0], 500, false, $petCustom[$key]);
	if($descr[2] == 'boolean') {
		$checked = $pet['clientid'] ? $petCustom[$key] : noBooleanValue();
		oneLineBoolean($descr[0], $noRow=false, $checked);
	}
	if($descr[2] == 'text') textBox($descr[0], $width=700, $height=100, $petCustom[$key]);

}
