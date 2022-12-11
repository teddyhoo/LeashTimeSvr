// mobile-emergency.js

function addEmergencyPage() {
	document.write(
		"<div id='emergencytab' style='display:none;background-color:white;'><p style='text-align:center'><span class=''>If you need to contact the Office</span><table align='center'><tr>"
		+(emergencyTel != undefined && emergencyTel != ''
			? "<td align='center'><a href='tel:"+emergencyTel+"'><img border=0 src='art/mobile-call-phone.png'></a><br>Call</td>"
			: "")
		+(emergencySMS != undefined && emergencySMS != '' 
				? "<td align='center'><a href='sms:"+emergencySMS+"'><img border=0 src='art/mobile-text.png'></a><br>Text Message</td>" 
			: '')
		+"</tr></table>"
		+(offerFindAVet 
				? "<p style='text-align:center'><a href='mobile-nearby-vets.php'><img border=0 src='art/vet-button.gif'></a><br>Find a Vet Nearby"
				:'')
		+"<p style='text-align:center'><span onclick='this.parentNode.parentNode.style.display=\"none\"'>(Close)</span></div>");
}

function showEmergencyPage() {document.getElementById('emergencytab').style.display='inline';}
