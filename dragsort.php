<? // dragsort.php -- fns for sorting lists in php

function headerInsert($listName) {
return <<<INSERTION
<link rel="stylesheet" type="text/css" href="tool-man/lists.css"/>
<script language="JavaScript" type="text/javascript" src="tool-man/core.js"></script>
<script language="JavaScript" type="text/javascript" src="tool-man/events.js"></script>
<script language="JavaScript" type="text/javascript" src="tool-man/css.js"></script>
<script language="JavaScript" type="text/javascript" src="tool-man/coordinates.js"></script>
<script language="JavaScript" type="text/javascript" src="tool-man/drag.js"></script>
<script language="JavaScript" type="text/javascript" src="tool-man/dragsort.js"></script>
<script language="JavaScript" type="text/javascript" src="tool-man/cookies.js"></script>

<script language="JavaScript" type="text/javascript"><!--

	var dragsort = ToolMan.dragsort()

	var junkdrawer = ToolMan.junkdrawer()

	window.onload = function() {
		//junkdrawer.restoreListOrder("$listName")

		dragsort.makeListSortable(document.getElementById("$listName"),
				verticalOnly, saveOrder)

	}

	function verticalOnly(item) {
		item.toolManDragGroup.verticalOnly()
	}

	function speak(id, what) {
		var element = document.getElementById(id);
		element.innerHTML = 'Clicked ' + what;
	}

	function saveOrder(item) {
		var group = item.toolManDragGroup
		var list = group.element.parentNode
		var id = list.getAttribute("id")
		if (id == null) return
		group.register('dragend', function() {
			ToolMan.cookies().set("list-" + id, 
					junkdrawer.serializeList(list), 365)
		})
	}
	
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
		for(var i=0;i<list.length;i++) 
			if(list[i].nodeType == document.ELEMENT_NODE && list[i].type && list[i].focus) 
				list[i].focus();
	}

	function selectClick(el) {
		var list = el.childNodes;
		for(var i=0;i<list.length;i++) 
			if(list[i].nodeType == document.ELEMENT_NODE && list[i].type && list[i].select) {
				list[i].focus();
				list[i].select();
			}
	}
	//-->
</script>
INSERTION;
}

function echoSortList($list, $name, $numbered=false, $extraLIs='') {
	echo "<".($numbered ? 'ol' : 'ul')." id='$name' class='boxy'>\n";
	foreach($list as $val => $label)
		echo "<li ondblclick='selectClick(this);' onclick='focusClick(this);' id='li_$val'>$label</li>\n";
	if($extraLIs) echo "$extraLIs\n";
	echo "</".($numbered ? 'ol' : 'ul').">\n";
}
/*
echo "<head>".headerInsert('fruit')."</head>\n<body>\n";
echoSortList(array(100=>'apples',200=>'blueberries',300=>'cherries'), 'fruit', $numbered=true);
? >

<input type=button value='showall' onclick='alert(getListOrder())'>

*/