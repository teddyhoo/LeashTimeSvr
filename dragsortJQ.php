<? // dragsortJQ.php -- fns for sorting lists in php using JQuery

function headerInsert($listName) {
return <<<INSERTION

<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="jquery-ui-1.8.20/js/jquery-ui-1.8.20.custom.min.js"></script>
<script type="text/javascript" src="jquery.ui.touch-punch.min.js"></script>
<style>
.sortableList { list-style-type: none; margin: 0; padding: 0;}
.sortableList li { margin: 0 5px 5px 5px; padding: 5px; font-size: 1.0em; height: 1.5em; }
html>body .sortableList li { height: 1.5em; line-height: 1.2em; }
.ui-state-highlight { height: 1.5em; line-height: 1.2em; }
</style>
<script>
$(function() {
	$( ".sortableList" ).sortable({
		placeholder: "ui-state-highlight"
	});
	$( ".sortableList" ).disableSelection();
});

function getListOrder() {
	var list = document.getElementsByTagName("li");

	var result = [];
	for(var i=0;i<list.length;i++) 
		if(list[i].id.indexOf("li_") > -1) 
			result[result.length] = list[i].id.substring(3);
	return result.join(',');
}



function focusClick(el) {
	var list = el.childNodes;
	for(var i=0;i<list.length;i++) {
//alert(list[i]);
		if(list[i].nodeType == document.ELEMENT_NODE && list[i].type && list[i].focus) {
			list[i].focus();
			list[i].disabled = false;
		}
	}
}

function selectClick(el) {
	var list = el.childNodes;
	for(var i=0;i<list.length;i++) 
		if(list[i].nodeType == document.ELEMENT_NODE && list[i].type && list[i].select) {
			list[i].focus();
			list[i].select();
			list[i].disabled = false;
		}
}
	//-->
</script>

</script>
INSERTION;
}

function echoSortList($list, $name, $numbered=false, $extraLIs='', $style='') {
	$style = $style ? $style : 'padding-top:0px;padding-bottom:0px';
	echo "<".($numbered ? 'ol' : 'ul')." id='$name' class='boxy sortableList'>\n";
	foreach($list as $val => $label)
		echo "<li class='ui-state-default' style='$style' onclick='focusClick(this);' ondblclick='selectClick(this);' id='li_$val'>$label</li>";
	if($extraLIs) echo "$extraLIs\n";
	echo "</".($numbered ? 'ol' : 'ul').">\n";
}
