/***********************************************
* Pop-it menu- © Dynamic Drive (www.dynamicdrive.com)
* This notice MUST stay intact for legal use
* Visit http://www.dynamicdrive.com/ for full source code
***********************************************/

var defaultMenuWidth="250px" //set default menu width.

var linkset=new Array()
//SPECIFY MENU SETS AND THEIR LINKS. FOLLOW SYNTAX LAID OUT

linkset[0]='<a href="http://dynamicdrive.com">Dynamic Drive</a>'
linkset[0]+='<hr>' //Optional Separator
linkset[0]+='<a href="http://www.javascriptkit.com">JavaScript Kit</a>'
linkset[0]+='<a href="http://www.codingforums.com">Coding Forums</a>'
linkset[0]+='<a href="http://www.cssdrive.com">CSS Drive</a>'
linkset[0]+='<a href="http://freewarejava.com">Freewarejava</a>'

linkset[1]='<a href="http://msnbc.com">MSNBC</a>'
linkset[1]+='<a href="http://cnn.com">CNN</a>'
linkset[1]+='<a href="http://news.bbc.co.uk">BBC News</a>'
linkset[1]+='<a href="http://www.washingtonpost.com">Washington Post</a>'

////No need to edit beyond here

var ie5=document.all && !window.opera // true for IE6,7,8,9
var ns6=document.getElementById
var mobile=ns6 && !ie5

if (ie5||ns6)
document.write('<div id="popitmenu" onMouseover="clearhidemenu();" onMouseout="dynamichide(event)"></div>')

function iecompattest(){
return (document.compatMode && document.compatMode.indexOf("CSS")!=-1)? document.documentElement : document.body
}

function showmenu(element, which, optWidth){
	if (!document.all&&!document.getElementById)
	return
	clearhidemenu()
	menuobj=ie5? document.all.popitmenu : document.getElementById("popitmenu")
	menuobj.innerHTML=which
	menuobj.style.width=(typeof optWidth!="undefined")? optWidth : defaultMenuWidth
	menuobj.contentwidth=menuobj.offsetWidth
	menuobj.contentheight=menuobj.offsetHeight

	var elheight = element.offsetHeight;

	
	eventX=element.offsetLeft
	eventY=element.offsetTop+elheight
	var parent=element;
	while(parent = parent.offsetParent) {
		//alert(parent+' '+parent.offsetLeft);
		//eventX -= parent.offsetLeft
		eventY -= parent.offsetTop
	}
	
	/*
	var position = $('#'+element.id).offset();
	//alert(position.left+', ',+position.top);
	if(document.getElementById('InnerMostFrame')) {
		position.left = position.left - $('#InnerMostFrame').offset().left;
	}
	eventX=position.left;
	eventY=position.top + menuobj.offsetHeight;
	*/
	
	
	

	//Find out how close the mouse is to the corner of the window
	var windowwidth = !ie5? window.innerWidth : iecompattest().clientWidth;
	var rightedge=windowwidth-eventX
	var bottomedge=!ie5? window.innerHeight-eventY : iecompattest().clientHeight-eventY
	//if the horizontal distance isn't enough to accomodate the width of the context menu
	var pageXOffset = typeof window.pageXOffset == 'undefined' ? 0 : window.pageXOffset;
	if (true || rightedge<menuobj.contentwidth)
		//move the horizontal position of the menu to the left by its width
		menuobj.style.left=!ie5? pageXOffset+eventX+element.offsetWidth-menuobj.contentwidth+"px" : 
													iecompattest().scrollLeft+eventX-menuobj.contentwidth+"px"
	else
		//position the horizontal position of the menu where the mouse was clicked
		menuobj.style.left=!ie5? window.pageXOffset+eventX+"px" : iecompattest().scrollLeft+eventX+"px"
	//same concept with the vertical position
	if (false && bottomedge<menuobj.contentheight)
		menuobj.style.top=!ie5? window.pageYOffset+eventY-menuobj.contentheight+"px" : iecompattest().scrollTop+eventY-menuobj.contentheight+"px"
	else
		menuobj.style.top=!ie5? window.pageYOffset+eventY+"px" : iecompattest().scrollTop+event.clientY+"px"
	menuobj.style.visibility="visible"
//alert('IE5: ['+ie5+'] NS6: ['+ns6+'] LEFT: ['+menuobj.style.left+'] top: ['+menuobj.style.top+']');


	var mobilepattern = /Alcatel|iPhone|iPod|SIE-|BlackBerry|Android|IEMobile|Obigo|Windows CE|LG\/|LG-|CLDC|Nokia|SymbianOS|PalmSource\|Pre\/|Palm webOS|SEC-SGH|SAMSUNG-SGH/i;
	
	if(navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
		menuobj.style.left=(window.pageXOffset-(menuobj.contentwidth/2))+'px';
		menuobj.style.top=window.pageYOffset+'px';
	}
	else if(navigator.userAgent.match(mobilepattern)) {
		menuobj.style.left=(windowwidth-menuobj.contentwidth)+'px';
		menuobj.style.top='0px';
	}
	//$('.InnerMostFrame').bind('click', function() {hidemenu();});
	if(document.getElementById('InnerMostFrame')) {
		document.getElementById('InnerMostFrame').onclick=hidemenu;
	}
	return false
}

function contains_ns6(a, b) {
//Determines if 1 element in contained in another- by Brainjar.com
while (b.parentNode)
if ((b = b.parentNode) == a)
return true;
return false;
}

function hidemenu(){
	if (window.menuobj) {
		menuobj.style.visibility="hidden";
		//if((navigator.userAgent.match(/iPhone/i)) || (navigator.userAgent.match(/iPod/i))) $('.ContentDiv').unbind('click');
	}
	//$('.InnerMostFrame').unbind('click');
	if(document.getElementById('InnerMostFrame')) document.getElementById('InnerMostFrame').onclick=null;

}

function dynamichide(e){
if (ie5&&!menuobj.contains(e.toElement))
hidemenu()
else if (ns6&&e.currentTarget!= e.relatedTarget&& !contains_ns6(e.currentTarget, e.relatedTarget))
hidemenu()
}

function delayhidemenu(){
delayhide=setTimeout("hidemenu()",2000)
}

function clearhidemenu(){
if (window.delayhide)
clearTimeout(delayhide)
}

if (ie5||ns6)
document.onclick=hidemenu

