<?
/*
* petpick-grid.php
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
  echo "<div id='$id' class='petgrid' style='visibility:hidden;position:absolute;'>";
  petPickerInnerHTML($pets, $petpickerOptionPrefix, $narrow);
  echo "</div>";
}

function petPickerInnerHTML($pets, $petpickerOptionPrefix, $narrow=false) {
	// $pets may be a string (from getClientPetNames() or an array or pet objects
	$pets = $pets ? $pets : array();
	$petList = is_array($pets) ? $pets : explode(', ', $pets);
	$petList = array_merge(array("All Pets"), $petList);
	echo "<table><tr>";
  $n=0;
  $fullSet = $pets ? "All Pets, $pets" :  'All Pets';
  foreach($petList as $pet) {
    if(is_array($pet)) {
			$tip = "title = '{$pet['type']}'";
			$pet = $pet['name'];
		}
    $class = 'petgridOff';
    if($narrow) echo "<tr>";
			
    echo "\n<td id='$petpickerOptionPrefix".$n++."' class='$class' onClick='togglePet(this)' $tip ondblclick='togglePet(this,\"petgridOn\");savePets();' >$pet</td>";
  }
  echo "</tr>\n<tr>
         <td colspan=3><input type=button value='Done' onClick='savePets()'></td>
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
	
/*function togglePet(el) {
	var cl = el.className;
	cl = (cl == 'petgridOn') ? 'petgridOff' : 'petgridOn';
	el.className = cl;
}*/

function togglePet(el, passedClass) {
	var cl = el.className;
	cl = (cl == 'petgridOn') ? 'petgridOff' : 'petgridOn';
	if(passedClass) cl = passedClass;
	el.className = cl;
	if(cl == 'petgridOn') {
	  if(el.id == '$petpickerOptionPrefix'+'0') { // if 'All Pets' is On
	    var i = 1;
	    while(document.getElementById('$petpickerOptionPrefix'+i)) {
	      document.getElementById('$petpickerOptionPrefix'+i).className = 'petgridOff';
	      i++;
		  }
		}
		else document.getElementById('$petpickerOptionPrefix'+0).className = 'petgridOff';
  }

}


function showPetGridInContentDiv(e, elId) {
	showPetGrid(e, elId, getAbsolutePosition(document.getElementById('ContentDiv')));
}
	
function showPetGrid(e, elId, offset) {
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
	//val = document.getElementById(elId).value;
	//for(var i=0;i<allPetChoices.length;i++)
	//  document.getElementById('$petpickerOptionPrefix'+i).className = 
	//    (val.indexOf(allPetChoices[i]) > -1) ? 'petgridOn' : 'petgridOff';
	var allTDs = document.getElementsByTagName('td');
	for(var i=0;i<allTDs.length;i++)
		if(allTDs[i].id.indexOf('$petpickerOptionPrefix') == 0) {
			allTDs[i].className = 
	        (val.indexOf(allTDs[i].innerHTML) > -1) ? 'petgridOn' : 'petgridOff';
		}

	var coords = mouseCoords(e || window.event);
	coords.x -= offset.x;
	coords.y -= offset.y;
	document.getElementById(petGridId).style.left=coords.x-16+'px';
	document.getElementById(petGridId).style.top=coords.y-20+'px';
	document.getElementById(petGridId).style.visibility = 'visible';
}


	

function hidePetGrid() {
	document.getElementById(petGridId).style.visibility = 'hidden';
}

function savePets() {
	hidePetGrid();
	var val = '';
	var allPets = false;
	var tips = new Array();
	var allTDs = document.getElementsByTagName('td');
	for(var i=0;i<allTDs.length;i++) {
	  if(allTDs[i].id.indexOf('$petpickerOptionPrefix') == 0) {
			if(allTDs[i].className == 'petgridOn') {
	      val += (val ? ', ' : '') + allTDs[i].innerHTML;
	      if(allTDs[i].innerHTML == 'All Pets') {
					allPets = true;
					continue;
				}
			}
			if(allPets || allTDs[i].className == 'petgridOn')
		    tips[tips.length] =  allTDs[i].innerHTML+': '+ (allTDs[i].title ? allTDs[i].title : '?');
		}
	}
	val = val ? val : nullPetsLabel;
	tips = tips.join(', ');
	
	
	var el = document.getElementById(petsElementId);

	if(!el) {
		alert("Element with id ["+petsElementId+" not found.");
		return;
	}
	else if(el.type) el.value = val;
	else el.innerHTML = val;
	if(el) el.title = tips;
	
	if(typeof petsUpdated == 'function') petsUpdated(el.id);
}

function getAbsolutePosition(element) {
    var r = { x: element.offsetLeft, y: element.offsetTop };
    if (element.offsetParent) {
      var tmp = getAbsolutePosition(element.offsetParent);
      r.x += tmp.x;
      r.y += tmp.y;
    }
    return r;
};
//************** END JS script for PetGrid **************

FUNC;
}

?>
