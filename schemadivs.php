<head>
<style type="text/css">

.drag{
position:absolute;
cursor:pointer;
z-index: 100;

border: solid gray 6px;width:200;
}

body {font-size:8pt;}
.tablebox {font-size:inherit;border-collapse:collapse;}
.namerow {font-family:Arial;font-weight:bold;border-bottom: solid black 1px;}
.colname {}

</style>

<script type="text/javascript">

/***********************************************
* Drag and Drop Script: © Dynamic Drive (http://www.dynamicdrive.com)
* This notice MUST stay intact for legal use
* Visit http://www.dynamicdrive.com/ for this script and 100s more.
***********************************************/

var dragobject={
z: 0, x: 0, y: 0, offsetx : null, offsety : null, targetobj : null, dragapproved : 0,
initialize:function(){
document.onmousedown=this.drag
document.onmouseup=function(){this.dragapproved=0}
},
drag:function(e){
var evtobj=window.event? window.event : e
this.targetobj=window.event? event.srcElement : e.target
if (this.targetobj.className=="drag"){
this.dragapproved=1
if (isNaN(parseInt(this.targetobj.style.left))){this.targetobj.style.left=0}
if (isNaN(parseInt(this.targetobj.style.top))){this.targetobj.style.top=0}
this.offsetx=parseInt(this.targetobj.style.left)
this.offsety=parseInt(this.targetobj.style.top)
this.x=evtobj.clientX
this.y=evtobj.clientY
if (evtobj.preventDefault)
evtobj.preventDefault()
document.onmousemove=dragobject.moveit
}
},
moveit:function(e){
var evtobj=window.event? window.event : e
if (this.dragapproved==1){
this.targetobj.style.left=this.offsetx+evtobj.clientX-this.x+"px"
this.targetobj.style.top=this.offsety+evtobj.clientY-this.y+"px"
return false
}
}
}

dragobject.initialize()

function reorder() {
	alert('bang');
	for(var i=0;document.getElementById('div_'+i);i++)
	  document.getElementById('div_'+i).style.zIndex = i;
}

//document.ondblclick=reorder;

var topZindex = 200;

function floatMeNow(me) {
	me.style.zIndex=topZindex++;
	return false;
}

</script>

</head>
<body>
<?
extract($_REQUEST);
$file = isset($file) ? $file : 'petbiztables.txt';
$lines = file($file);

$tables = array();

function stripbquotes($s) {
  return substr($s,1,-1);
}

function makeTable($table,$primary,$cols) {
  $s = $table;
  foreach($cols as $nm => $col) {
    $style = $primary == $nm ? "style='color:red;'" : "style='color:black;'";
    $s .= "<tr><td class=colname $style>".stripbquotes($col[0])."</td><td class=coltype>{$col[1]}</td></tr>\n";
  }
  return $s."</table>\n";
}

foreach($lines as $line) {
  $parts = explode(' ',trim($line));
  if(!$line || !$parts) continue;
  if(strpos($parts[0], 'CREATE') === 0) {
    $table = "<table class=tablebox width=100%><tr class=namerow><td colspan=2>".stripbquotes($parts[2])."</td></tr>\n";
    $cols = array();
    $primary = null;
  }
  else if($parts[0] == 'PRIMARY') $primary = substr($parts[3],2,-2);
  else if(substr($parts[0], 0, 1) == '`') 
    $cols[stripbquotes($parts[0])] = $parts;
  else if($parts[0] == ')') $tables[] = makeTable($table,$primary,$cols);
}

$colors = array('lightyellow','lightblue','pink','lightgreen','lightgrey');
$deltaX = 40;
$xpos = 0;
$n=0;
foreach($tables as $table) {
	$color = current($colors);
	if(!$color) {reset($colors); $color = current($colors);}
	$color = "background:$color;";
  echo "<div id='div_$n' class='drag' style='position:absolute;$color;top:0px;left:$xpos' ondblclick='return floatMeNow(this)'>\n$table</div>\n";
  $xpos += $deltaX;
  next($colors);
  $n++;
}
  
?>  
</body>