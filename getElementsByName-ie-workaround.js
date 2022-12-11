// getElementsByName-ie-workaround.js

if (navigator.appName == "Microsoft Internet Explorer")
	document.getElementsByNameArray = 

	function(name){
		var out = []; 
		function getElementsByNameDelegate(elem, eName, results) {
		 if(elem.name && elem.name == eName) results.push(elem);
		 for(var i=0; i<elem.childNodes.length;i++) 
			getElementsByNameDelegate(elem.childNodes[i], eName, results);
		}
		getElementsByNameDelegate(document, name, out);
		return out;
	}
else document.getElementsByNameArray = 
	function(name){
		var out = [];
		els = document.getElementsByName(name);
		for(var i=0;i<els.length;i++)
			out.push(els.item(i));
		return out;
	};
