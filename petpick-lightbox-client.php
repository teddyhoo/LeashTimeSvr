<?
/*
* petpick-lightbox-client.php
* Used to create a reusable popup div for selecting client pets as a commaspace-delimited (", ") list.
* Usage:
* 1. Create the div with makePetPicker($id), specifying the DIV's id
* 2. Create one or more form elements or HTML objects with innerHTML whose values are to receive the days list.
* 3. To populate each of these target elements, pass the id (not name) attribute 
*    of the target element into showWeekdayGrid(event, targetId)
* 4. After days are selected and "Done" is pressed, the chosen days will replace the value of the target element.
*
* Script vars:
*   petGridId = the id of the reusable weekday grid
*   petsElementId = set when showWeekdayGrid is called: the id of the target element
*   nullWeekdaysLabel = the string to which the target element's value will be set when no days are selected
*
* CSS Classes:
*   petgrid = looks for the weekday grid div
*   petgridOff =  looks of a day cell when the day is not selected
*   petgridOn =  looks of a day cell when the day is selected
*
*/

$petpickerOptionPrefix = 'petpicker_option';
function makePetPicker($id, $pets, $petpickerOptionPrefix, $narrow=false) {
  echo "<div id='INVISIPETBOX' class='petgrid' style='display:none;'>";
  petPickerInnerHTML($pets, $petpickerOptionPrefix, $narrow);
  echo "</div>";
}

function petPickerInnerHTML($pets, $petpickerOptionPrefix, $narrow=false) {
	// $pets may be a string (from getClientPetNames() or an array or pet objects
	$pets = $pets ? $pets : array();
	$petList = is_array($pets) ? $pets : explode(', ', $pets);
	$petList = array_merge(array("All Pets"), $petList);
	echo "<table style='padding-left:10px'><tr>";
  $n=0;
  $fullSet = $pets ? "All Pets, $pets" :  'All Pets';
  foreach($petList as $i => $pet) {
    if(is_array($pet)) {
			$tip = "title = '{$pet['type']}'";
			$pet = $pet['name'];
		}
		$slashedPet = $pet;
    if($narrow && !$i) echo "<tr>";
    $cbID = "PLACEHOLDER$petpickerOptionPrefix$i";
    echo "\n<td><input class='petchoice' type='checkbox' $tip id= '$cbID' name='$petpickerOptionPrefix' value=\"$slashedPet\" onClick='togglePet(this)'>
    						<label for='$cbID'>$pet</label></td>";
    if($narrow) echo "</tr>\n";
  }
  if(!$narrow) echo "</tr>\n";
  echo "<tr>
         <td colspan=3><input type=button value='Ok' onClick='savePets()'></td>
         <td>&nbsp;</td>
         <td colspan=3><input type=button value='Cancel' onClick='hidePetGrid()'></td>
         </tr></table>";
}

function dumpPetGridJS($id, $pets) {
	global $petpickerOptionPrefix;
	$allPets = $pets ? "\"All Pets, ".htmlentities($pets)."\"" :  '"All Pets"';
	echo <<<FUNC
//************** JS script for PetGrid **************
var petGridId = '$id';
var petsElementId = '';
var nullPetsLabel = '';
var allPetChoices = $allPets.split(', ');
	
function togglePet(el, passedClass) {
	if(el.checked) {
	  if(el.id == '$petpickerOptionPrefix'+'0') { // if 'All Pets' is On
	    var i = 1;
	    while(document.getElementById('$petpickerOptionPrefix'+i)) {
	      document.getElementById('$petpickerOptionPrefix'+i).checked = false;
	      i++;
		  }
		}
		else document.getElementById('$petpickerOptionPrefix'+0).checked = false;
	}
}


function showPetGrid(e, elId, offset, anchorToElement) {
  if(!offset) offset = {x: 0, y: 0};
	petsElementId = elId;
	var el = document.getElementById(elId);
	if(!el) {
		alert("Element with id ["+elId+" not found.");
		return;
	}
	else if(el.type) val = el.value;
	else val = el.innerHTML;
	val = val ? val : '';

	var timeframeHTML = $("#INVISIPETBOX").html(); //$("<div />").append($(".timeFrame").clone()).html();
	timeframeHTML = timeframeHTML.replace(/PLACEHOLDER/g, "");
	lightBoxHTML(timeframeHTML, 300, 200);
	//var allCBs = document.getElementsByTagName('input');
	//for(var i=0;i<allCBs.length;i++) {
	$('.petchoice').each(function (index, cb) {
			if(cb.id.indexOf('PLACEHOLDER') == -1)
				cb.checked = (val.indexOf(cb.value) > -1);
		});
}


	

function hidePetGrid() {
	lightBoxIFrameClose();
}

function savePets() {
	hidePetGrid();
	var val = '';
	//var allCBs = document.getElementsByTagName('input');
	//for(var i=0;i<allCBs.length;i++) {
	$('.petchoice').each(function (index, cb) {
		if(cb.checked)
			if(cb.id.indexOf('PLACEHOLDER') == -1)
				val += (val ? ', ' : '') + cb.value;
		val = val ? val : nullPetsLabel;    


		var el = document.getElementById(petsElementId);

		if(!el) {
			alert("Element with id ["+petsElementId+" not found.");
			return;
		}
		else if(el.type) el.value = val;
		else el.innerHTML = val;

		if(typeof petsUpdated == 'function') petsUpdated(el.id);
	});
}

//************** END JS script for PetGrid **************

FUNC;
}

?>
