<? // time-to-fetch-page.php

/*  This page measures the time it takes to fetch a page from the server to a browser.
	
*/

?>
<form method='POST'>
URL? <input id='url' style='width:600px;'> <input type='button' onclick='goGetTheTime()' value='Go Get The Time'>
</form>
<p>Result:
<div id='result_DIV' style='background:lightgray;padding:10px;'></div>

<script language='javascript' src='ajax_fns.js'></script>

<script language='javascript'>

var url;

function goGetTheTime() {
	// ajaxGetAndCallWith(url, callbackfn, argument)
	var starttime = new Date().getTime();
	url = document.getElementById('url').value;
	ajaxGetAndCallWith(url, handleMyAjaxResponse, starttime);
}

function handleMyAjaxResponse(starttime, response) {
	var endtime = new Date().getTime();
	var duration = endtime - starttime;
	var div = document.getElementById('result_DIV');
	div.innerHTML += 
		new Date().toString()
		+"<br>"+url
		+"<br>run time: "+duration+" ms  for "+response.length+" chars.<hr>";
}

</script>

<p><div style='border:solid black 1px;width:700px;padding:10px;margin-left:30px;'></div>