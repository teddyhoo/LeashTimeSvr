/* select-builder.js - code for dynamically rebuilding a select element */

function getSelectOptionData(rawdata) { // rawdata is lines of pipe-delimited text: "value|selected|label"  lines are broken with ## strings
  var options = new Array();
  var lines = rawdata.split("##");
  for(var i = 0; i < lines.length; i++) {
		var line = lines[i].replace(/^\s+|\s+$/g,""); // trim whitespace
    options[i] = line.split('|');
	}
  return options;
}  

function rebuildSelectOptions(selectElementId, rawdata, selectedValue) {	
	if(rawdata == 'nochange') return;
	var sel = document.getElementById(selectElementId);
	var options = getSelectOptionData(rawdata);
	
	var originalSelectionIndex = sel.selectedIndex;
	var originalSelection = originalSelectionIndex == -1 ? '' : sel.options[originalSelectionIndex].value;
	sel.options.length=0;
	var selectedIndex = -1;
	for(var i=0; i < options.length; i++) {
		var optionDescr = options[i];
		var checked = optionDescr[1] != ''; 
		if(checked) selectedIndex = i;  // added this line for IE6, which ended up selecting the wrong element otherwise
	  sel.options[i]= new Option(optionDescr[2], optionDescr[0], checked, checked);
	}
	if(selectedIndex != -1) 
		sel.options[selectedIndex].selected = true;  // added this line for IE6, which ended up selecting the wrong element otherwise
	else if(selectedValue) {  // restore original selection if none of the new options was selected
	  for(var i=0;i < sel.options.length; i++) {
			if(sel.options[i].value == selectedValue) {
				sel.options[i].selected = true;
				break;
			}
		}
	}
	else if(originalSelectionIndex != -1) {  // restore original selection if none of the new options was selected
	  for(var i=0;i < sel.options.length; i++) {
			if(sel.options[i].value == originalSelection) {
				sel.options[i].selected = true;
				break;
			}
		}
	}
}

function pickSelectOptionWithValue(selectElementId, selectedValue) {
	var sel = document.getElementById(selectElementId);
	for(var i=0;i < sel.options.length; i++) {
		if(sel.options[i].value == selectedValue) {
			sel.options[i].selected = true;
			break;
		}
	}
}


function openWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=yes, location=no, directories=no, status=no, resizable=yes, menubar=yes, scrollbars=yes, width='+wide+', height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}


function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

