<? // oik.php

if($_REQUEST['inside']) {
?>
	<input type=button onclick='parent.document.getElementById("result").value=this.value;alert(parent.hello)' value=1><br>
	<input type=button onclick='parent.document.getElementById("result").value=this.value' value=2><br>
	<input type=button onclick='parent.document.getElementById("result").value=this.value' value=3><br>
<?
	exit;
	}
else {
?>
<script language='javascript'>
function hello() {1;}
</script>
<form>
<input id=result>
</form>
<hr>
<iframe src='oik.php?inside=1' width=500 height=300></iframe>
<? }