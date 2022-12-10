<? // pet-services.php
$pageTitle = "Assigned Pet Service Types";
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
require_once "service-fns.php";
require_once "pet-service-fns.php";
include "gui-fns.php";
locked('o-');

if(requestIsJSON()) $_POST = getJSONRequestInput();

if($_POST) {
	//header("Content-type: application/json");
	foreach($_POST as $pettype => $servicecodes) {
		//$debug .= setPetServiceTypes($pettype, $servicecodes);
		setPetServiceTypes($pettype, $servicecodes);
		//$debug .= "$pettype: ".print_r($servicecodes, 1);
	}
	echo json_encode(array('status'=>'ok', 'debug'=>$debug));
	exit;
}

$allTypesArray = getAllServicePetTypes();
//print_r($allTypesArray);
$servicesByPet = getAllPetsTypesServices($allTypesArray);
$currentPettypes = explode('|', $_SESSION['preferences']['petTypes']);
$pettypes = array_unique(array_merge($currentPettypes, array_keys($servicesByPet)));

foreach($pettypes as $type)
	if(!$servicesByPet[$type]) $servicesByPet[$type] = array();
$payload = json_encode($servicesByPet);

$breadcrumbs .= fauxLink('Service List', 'document.location.href="service-types.php"', 1, "Staff Only");
require_once "frame.html";
echo "<div class='tiplooks' id='message'></div>";
echoButton('', 'Save Changes', 'submitRequest()');
echo "<p>";
echo "<table><tr>";
echo "<td style='vertical-align:top'>";
$selected = 'boldfont fontSize1_2em';
foreach($pettypes as $i => $pettype) {
	echo "<span id='pettype_$i' class='$selected' onclick='pettypeClicked(this)'>$pettype</span><p>";
	$selected = 'fontSize1_2em';
}
echo "</td>";
echo "<td class= 'fontSize1_1em' style='vertical-align:top'>";
echo "Selected service types: <span id='checkednames' class='tiplooks fontSize1_1em'></span>";
serviceTypeCheckBoxes($allTypesArray);
echo "</td>";
echo "</tr></table>";
?>
<script>
var pettypeindex = 0;
var pettype = '<?= $pettypes[0] ?>';
var payload = <?= $payload ?>;

function pettypeClicked(el) {
	pettype = el.innerHTML;
	$('#pettype_'+pettypeindex).removeClass('boldfont');
	pettypeindex = el.id.split('_');
	pettypeindex = pettypeindex[1];
	$('#pettype_'+pettypeindex).addClass('boldfont');
	updateCheckboxes(pettype);
}

function updateCheckboxes(pettype) {
	$('input:checked').each(function (i, el) { $(el).prop('checked', false); });
	payload[pettype].forEach(function(el, i) { $('#'+el).prop('checked', true); });
	updateNameList();
}

function updateNameList() {
	let slist = [];
	$('input:checked').each(function (i, el) {
		slist.push(el.getAttribute('safeLabel'));
		});
	if(slist.length == 0) slist = "<b>None</b> -- all service types will be offered.";
	else slist = slist.join(', ');
	$('#checkednames').html(slist);
}

function boxClicked() {
	let idlist = [];
	$('input:checked').each(function (i, el) {
		idlist.push(el.id);
		});
	payload[pettype] = idlist;
	updateNameList();
}

function submitRequest() {
	//alert('About to Submit:'+"\n"+JSON.stringify(payload));
	
	$.ajax({
	    url: 'pet-services.php',
	    dataType: 'json', // comment this out to see script errors in the console
	    type: 'post',
	    contentType: 'application/json',
	    data: JSON.stringify(payload),
	    processData: false,
	    success: submitSucceeded,
	    error: <?= 0 && mattOnlyTEST() ? 'submitFailed' : 'submitSucceeded' // until I figure this out...Figured it out! ?>
	    });
}

function submitSucceeded(data, textStatus, jQxhr) {
	let message = "Changes saved.";
	//alert(JSON.stringify(data));
	let debug = data.debug ? data.debug : '';
	$('#message').html(message+debug);
}

function submitFailed(jqXhr, textStatus, errorThrown) {
	let message = 'Error encountered:<br>'
		+<?= mattOnlyTEST() ? 'errorThrown' : '"Please notify support."' ?>;
	$('#finalMessage').html(message);
	$('#finalMessage').show();
	console.log(message );
	<?= mattOnlyTEST() ? 'console.log("jqXhr: "+jqXhr);console.log("textStatus: "+textStatus);' : '' ?>
}




updateCheckboxes(pettype);
</script>
<?
require_once "frame-end.html";

function serviceTypeCheckBoxes($allTypesArray) {
	$allActiveServices = getServiceNamesById();
	$allServices = getAllServiceNamesById();
	// show service types that are active or which appear in
	$serviceTypeIds = array_unique(array_merge(array_keys($allTypesArray), array_keys($allActiveServices)));
	$boxes = array();
//print_r($allServices);	
	foreach($allServices as $id=>$label) {
		if(!in_array($id, $serviceTypeIds)) continue;
		$labelClass = $allActiveServices[$id] ? null : 'warning';
		$title = $allActiveServices[$id] ? '' : 'inactive service type';
		//$boxes[] = labeledCheckbox($label, "servicetype_$id", $value=null, $labelClass, $inputClass=null, $onClick='boxClicked(this)', $boxFirst=true, $noEcho=true, $title);
		$safeLabel = safeValue($label);
		$boxes[] = "<input type='checkbox' name='servicetype[]' id='$id' onclick='boxClicked(this)' safelabel='$safeLabel'>"
								." <label for='$id'> $label</label>";
	}
	$columns = array_fill(0, 2, null);
	$rows = array_chunk($boxes, 2, $preserve_keys=true);
	foreach($rows as $i => $row) $rows[$i] = array_merge($row);
	tableFrom($columns, $rows, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);

}
