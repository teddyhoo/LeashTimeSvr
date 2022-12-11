/* referral-categories.js
depends on nestedListSort.js */

function bodyClick(ev) {
	ev = ev ? ev : window.event;
	var target = 989;
	if(ev && ev != undefined)
		target = ev.target || ev.srcElement;
//alert("EVENT: "+ev+" TARGET: "+target.parentNode+" openEditor: "+openEditor);
	if(openEditor != null && target.parentNode != openEditor.parentNode) endEdit(openEditor);
}

document.body.onclick= bodyClick;

var openEditor = null;

function editLabel(el) {
	if(openEditor && openEditor!=undefined) endEdit(openEditor);
	var parts = el.id.split('_');
	el.style.display='none';
	var editor = document.getElementById("edit_"+parts[1]);
	editor.value=el.innerHTML;
	editor.style.display='inline';
	editor.focus();
	openEditor = editor;
	return false;
}

function endOnEnter(ev) {
	if(ev.keyCode == 13) endEdit(ev.target);
}

function endEdit(el) {
	if(!el || el==undefined) return;
	var parts = el.id.split('_');
	el.style.display='none';
	var label = document.getElementById("span_"+parts[1]);
	if(el.value.length == 0) el.value='*empty*';
	label.innerHTML = el.value;
	label.style.display='inline';
	label.style.color=null;
	openEditor = null;
}

function deleteLine(el) {
	var msg = "Are you sure you want to delete this line";
	if(el.className == "record container") msg += "\n and the lines it contains";
	msg += '?';
//alert(el.parentNode.innerHTML);
	if(confirm(msg)) el.parentNode.parentNode.removeChild(el.parentNode);
}

var newCat = 0;

function addACategoryTo(el, newId, newLabel) {
	var ul = getUL(el);
	var color = '';
	if(!newId) {
		color = 'color:green';
		newCat++;
		newId = "NEW"+newCat;
	}		
	if(!newLabel) newLabel = "New Category "+newCat;
	var newLI = document.createElement("li");
	newLI.setAttribute('id', newId);
	newLI.setAttribute('class', 'record container');
	//newLI.setAttribute('onclick', 'alert(ul.parentNode.className)');
	newLI.innerHTML = 
								"<span class='containerTitle' id='span_"+newId+"' onClick='return editLabel(this)' style='display:inline;"+color+"' title='Category ID: "+newId+"'>"+newLabel+"</span>"
								+"<input size=20 id='edit_"+newId+"' style='display:none' onBlur='endEdit(this)' onKeyUp='endOnEnter(event)'> "
								+"&nbsp;&nbsp;<img src='art/delete.gif' style='cursor:pointer;height:12px;width:12px' border=0 onClick='deleteLine(this)' title='Drop this category group.'>"
								+"&nbsp;&nbsp;<img src='art/newfolder.gif' style='cursor:pointer;height:12px;width:12px' border=0 onClick='addACategoryTo(this)' title='Add a category group under this category.'>"
								+"&nbsp;&nbsp;<img src='art/newitem.gif' style='cursor:pointer;height:14px;width:14px' border=0 onClick='addAnItemTo(this)' title='Add a category under this category.'>";
	var newUL = document.createElement("ul");
	newUL.setAttribute('id', 'UL_'+newId);
		var newULLI = document.createElement("li");
		newULLI.setAttribute('class', 'unmovable');
	newUL.appendChild(newULLI);
	
	newLI.appendChild(newUL);

	ul.appendChild(newLI);
	updateWholeTree();
	return newLI;
}

function getUL(el) {  // under an LI or under the parent LI of an el
	var li = el ? (el.tagName.toUpperCase() == 'LI' ? el : el.parentNode) : null;
	var ul;
	if(li) ul = document.getElementById('UL_'+li.id);
	else ul = document.getElementById("masterList");
	return ul;
}


function addAnItemTo(el, newId, newLabel) {
	var ul = getUL(el);
	var color = '';
	if(!newId) {
		color = 'color:green';
		newCat++;
		newId = "NEW"+newCat;
	}		
	if(!newLabel) newLabel = "New Item "+newCat;
	var newLI = document.createElement("li");
	newLI.setAttribute('id', newId);
	newLI.setAttribute('class', 'record item');
	newLI.innerHTML = 
								"<span class='itemTitle' id='span_"+newId+"' onClick='return editLabel(this)' style='display:inline;"+color+"' title='Category ID: "+newId+"'>"+newLabel+"</span>"
								+"<input size=20 id='edit_"+newId+"' style='display:none' onBlur='endEdit(this)' onKeyUp='endOnEnter(event)'> "
								+"&nbsp;&nbsp;<img src='art/delete.gif' style='cursor:pointer;height:12px;width:12px' border=0 onClick='deleteLine(this)' title='Drop this category.'>";
	ul.appendChild(newLI);
	updateWholeTree();
}
								

function makeIntoACategory(el) {
	//alert(el.className);
	//el.className = "record container";
	var li = el ? (el.tagName == 'li' ? el : el.parentNode) : document.getElementById("masterList");
	var idnum = li.id;
	$('#'+li.id).removeClass("record item");
	$('#'+li.id).addClass("record container");
	var label = li.children[0].innerHTML; 
	var html = "<span class='containerTitle' id='span_"+idnum+"' onClick='return editLabel(this)' style='display:inline'>"+label+"</span>"
							+"<input size=20 id='edit_"+idnum+"' style='display:none' onBlur='endEdit(this)'> "
							+"<img src='art/delete.gif' style='cursor:pointer;height:12px;width:12px' border=0 onClick='deleteLine(this)'>"
							; //+"\n<ul id='UL_"+idnum+"'><li class='unmovable'></li></ul>";
	li.innerHTML = html;	
	$('#'+li.id).append("<ul id='UL_"+idnum+"'><li class='unmovable'></li></ul>");
	
//alert(li.innerHTML);	
	//"<span class='containerTitle'>Technology</span><ul id='UL_"+idnum+"'><li class='unmovable'></li></ul>");

}

//function jstrim(str) {
	//return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
//}


function dumpCategories(referralCategories) {
	document.write("<ul id='masterList' onClick='bodyClick(event)'>");

	if(referralCategories) {
		referralCategories = referralCategories.split("\n");
		for(var i=0;i<referralCategories.length;i++) {
			var line = referralCategories[i].replace(/^\s\s*/, '').replace(/\s\s*$/, '');
			if(line.length == 0) continue;
			line = line.split('|');
			var container;
			if(line[0] == 'top') container = null;
			else container = document.getElementById(""+line[0]);
			if(line.length >= 4 && line[3] == 'branch') addACategoryTo(container, line[1], line[2]);
			else addAnItemTo(container, line[1], line[2]);
		}
	}

	document.write("</ul>");
}

function gatherCategoryIds(catString) {
	var ids = new Array();
	var cats = catString.split("\n");
	for(var i=0;i<cats.length;i++)
		ids[ids.length] = cats[i].split('|')[1];
	return ids;
}

function gatherCategories(li, containerId) {
	var s='', i, ul;
	if(li == undefined) li = null;
	if(containerId == undefined) containerId = null;
	if(!containerId) containerId = 'top';
	if(li && li.id) {
		s = containerId+"|"+li.id+"|"+li.children[0].innerHTML;
		if(li.className=="record container") s += "|branch";
		s += "\n";
		if(li.className=="record container") {
			for(i = 0; i < li.children.length; i++)
				if(li.children[i].tagName.toUpperCase() == 'UL')
					ul = li.children[i];
		}
	}
	else if(!li) {
		ul = document.getElementById("masterList");
	}
	containerId = li ? li.id : null;
	if(ul) for(i = 0; i < ul.children.length; i++)
			s += gatherCategories(ul.children[i], containerId);
	return s;
}

// Functions for selecting referral options

function referralOptions(group, cats) { // return an array of option elements from categories contained in group
	var options = new Array();
	cats = cats.split("\n");
	if(cats.length == 0) return options;
	options[0] = new Option('--Choose One--', 0);
	for(var i=0;i<cats.length;i++) {
		var line = cats[i].replace(/^\s\s*/, '').replace(/\s\s*$/, '');
		if(line.length == 0) continue;
		line = line.split('|');
		if(line[4] == 0) continue;  // inactive option
		var container;
		if(line[0] == group) options[options.length] = new Option(line[2], line[1]);
	}
	return options;
}

function reprovisionReferralSelect(selId, groupCode, cats, selValue) {
	var sel = document.getElementById(selId);
	if(!sel) return;	
	sel.options.length=0;
	// find referral options for groupCode
	var options = referralOptions(groupCode, cats);
//alert("OPTIONS ["+groupCode+"]["+cats+"]: "+options); 	
	for(var i=0; i<options.length; i++) {
		if(options[i].value == selValue) options[i].selected = true;
		sel.options[sel.options.length] = options[i];
	}
	return options.length > 0;
}

// functions for client-edit.php
function openReferralEditor() {
	updateReferralSelects();
	document.getElementById('referralnoteinput').value = document.getElementById('referralnote').value;
	document.getElementById('referraldisplay').style.display = 'none';
	document.getElementById('referraleditor').style.display = 'block';
}
	
function closeReferralEditor(save) {
	var code=0;
	if(save) {
		var sel = null;
		for(var i=1;(sel = document.getElementById('referral_'+i));i++)
			if(sel.parentNode.parentNode.style.display != 'none') {
				code = sel.options[sel.selectedIndex].value;
				document.getElementById('referralcode').value = code;
			}
	
		document.getElementById('referralnote').value = document.getElementById('referralnoteinput').value;
		var referralnotelabel = document.getElementById('referralnoteinput').value;
		var referralnotetitle = null;
		if(referralnotelabel.length > 25) {
			referralnotetitle = referralnotelabel;
			referralnotelabel = truncatedLabel(referralnotelabel, 25);
		}
		document.getElementById('referralnotelabel').innerHTML = referralnotelabel;
		//if(referralnotetitle) document.getElementById('referralnotelabel').title = referralnotetitle;

		var path = getReferralCategoryPath(code, referralCategories).join(' > ');
		document.getElementById('referralcodelabel').innerHTML = path ? path : '--Unspecified--';
		checkAndSubmit("basic");
	}
	else {
		document.getElementById('referraldisplay').style.display = 'block';
		document.getElementById('referraleditor').style.display = 'none';
	}
}

function truncatedLabel(str, length) {
	if(str.length <= length) return str;
	return str.substr(0, length-3)+'...';
}


function updateReferralSelects(sel) {
	var code = sel ? sel.options[sel.selectedIndex].value : document.getElementById('referralcode').value;
	var selNum = parseInt(sel ? sel.id.split('_')[1] : 1)+1;
	var path = getReferralCategoryPath(code, referralCategories, true);
	var groupCode = 'top';
	var cats = categoryLookupArray(referralCategories);
	var groups = categoryGroupsLookup(referralCategories);

	if(!sel) reprovisionReferralSelect("referral_1", groupCode, referralCategories, path[0]);

	var displayVal = navigator.userAgent.toLowerCase().indexOf("msie") != -1 ? "block" : "table-row";
	var parentSelect = document.getElementById('referral_1');
	for(var i = 2; document.getElementById('referral_'+i); i++) {
		// if the level above has been selected and it is a group, display this level...
		displayVal = parentSelect.selectedIndex > 0 && groups[parentSelect.options[parentSelect.selectedIndex].value] ? displayVal : 'none';
		parentSelect = document.getElementById('referral_'+i);
		document.getElementById('referral_'+i).parentNode.parentNode.style.display = displayVal;
	}

	for(var i = selNum; i <= path.length+1; i++) {
		groupCode = path[i-2];
//alert(groupCode);		
		reprovisionReferralSelect("referral_"+i, groupCode, referralCategories, path[i-1]);
	}
}

function categoryLookupArray(referralCategories) {
	var cats = {};
	if(referralCategories) {
		referralCategories = referralCategories.split("\n");
		for(var i=0;i<referralCategories.length;i++) {
			var line = referralCategories[i].replace(/^\s\s*/, '').replace(/\s\s*$/, '');
			if(line.length == 0) continue;
			line = line.split('|');
			//if(idsOnly) cats[cats.length] = line[1];
			//else 
//alert('cat id: '+line[1]+' > '+line);
			cats[line[1]] = line;
		}
	}
	return cats;
}

function categoryGroupsLookup(referralCategories) {  // return groupId=>1
	var groups = {};
	if(referralCategories) {
		referralCategories = referralCategories.split("\n");
		for(var i=0;i<referralCategories.length;i++) {
			var line = referralCategories[i].replace(/^\s\s*/, '').replace(/\s\s*$/, '');
			if(line.length == 0) continue;
			line = line.split('|');
			groups[line[0]] = 1;
		}
	}
	return groups;
}

/*function getReferralCategoryPath(cat, referralCategories, idsOnly) {
	var cats =categoryLookupArray(referralCategories);
//alert("CATS: "+cats);	
	var path = new Array();
	if(idsOnly) path = new Array();
	else path = {};
	if(cat) for(var id = cat; id != 'top'; id = cats[id][0]) {
		if(idsOnly) path[path.length]  = cats[id][1];
		else path[cats[id][1]] = cats[id][2];
	}
	if(idsOnly) path.reverse();
	return path;
}*/

function getReferralCategoryPath(cat, referralCategories, idsOnly) {
	var cats =categoryLookupArray(referralCategories);
//alert("CAT: "+cat);	
	var path = new Array();
	path = new Array();
	if(cat != 0) for(var id = cat; id != 'top'; id = cats[id][0]) {
		if(idsOnly) path[path.length]  = cats[id][1];
		else path[path.length] = cats[id][2];
	}
	path.reverse();
	return path;
}

function optionalReferralIsIncomplete() {
	return referralIsIncomplete(true);
}

function mandatoryReferralIsIncomplete() {
	return referralIsIncomplete(false);
}

function referralIsIncomplete(allowNull) {
	var cat = document.getElementById('referralcode').value;
	if(cat != 0) return null;
	if(cat == 0 && !allowNull) return "Referral must be supplied.";
	var sels = new Array();
	var sel = null;
	for(var i=1;(sel = document.getElementById('referral_'+i)) && sel.options && sel.options.length > 0;i++)
		if(sel.parentNode.parentNode.style.display != 'none') 
			sels[sels.length] = sel.options[sel.selectedIndex].value;
	if(sels.length > 1 && sels[sels.length-1] == 0)
		return "Referral, if supplied, must be complete (a choice made in each pull-down).";
}	
		

/*
var referralCategories = 
"top|55|Petco|branch\n"+
"55|87|Marketing|branch\n"+
"87|12|flier|leaf\n"+
"87|19|circular|leaf\n"+
"87|4|Website|leaf\n"+
"55|8|Store|branch\n"+
"8|45|Employee|leaf\n"+
"8|65|Sign|leaf\n";
*/