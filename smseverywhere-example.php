<? smseverywhere-example.php
#PHP EXAMPLE api.smseverywhere.com
if ($submit): //SEND THE MESSAGE WHEN THE HTML FORM IS SUBMITTED

$user = "demo";
$smskey = "[sms][demo][20040523120917]8c6ed09bbcd13a043ce7c01e6c2393d9841caef037d43c485fc56559d511a099d0e44197be4de607";

$url = 'http://74.208.71.73/api/v2/http/?'
	. 'smsuser='. urlencode ($user)
	. '&smskey='. urlencode ($smskey)
	. '&smsfrom='. urlencode ('demo@beSMS.com')
	. '&smsmsg='. urlencode ($message)
	. '&smsphone=' . $recipients;	//THIS IS THE URL WITH YOUR MESSAGE
$reply = file_get_contents ($url);	//SUBMIT MESSAGE (GET URL);
$result = explode(":",$reply);		//separate reply into an array for easy output
//GENERATE OUTPUT:
echo $reply;
$output = <<<END
<script>
alert(
"Message status:\\t\\t $result[1]\\n\\n"
+ "Messages sent today:\\t\\t $result[2]\\n"
+ "Messages sent this month:\\t\\t $result[3]\\n"
+ "Messages sent total:\\t\\t $result[4]\\n"
)
</script>
END;

endif;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>Send message to a phone</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<style type="text/css">
<!--
.style2 {	font-family: Arial, Helvetica, sans-serif;
	font-size: 12px;
}
-->
</style>
<? echo $output; ?>
</head>

<body>
<form name="form1" method="post" action="<? echo $REQUEST_URI; ?>">
 <table width="172" border="0" cellspacing="1" class="sms-table">
<tr bgcolor="#00CCFF">
<td height="17" colspan="2" align="center">
<div align="center">
<font color="#000000" size="2" face="Arial, Helvetica, sans-serif">
<strong>MESSAGE TO  PHONE</strong>
</font></div></td>
</tr>
<tr bgcolor="#00FF66">
<td colspan="2">
<div align="center">
<strong>
<font color="#000000" size="2" face="Arial, Helvetica, sans-serif">
Cell Phone Number:
</font>
</strong>
<br>
<input name="recipients" type="text" class="sms-input" style="width: 90%;" value="<? echo $recipients; ?>" maxlength="10">
</div>
</td>
</tr>
<tr bgcolor="#00FF66">
<td height="45" colspan="2">
<div align="center">
<strong>
<font color="#000000" size="2" face="Arial, Helvetica, sans-serif">
Message: 
</font>
</strong>
<font color="#000000" size="1" face="Arial, Helvetica, sans-serif">(up to 140 chars)</font>
<br>
<textarea name="message" cols="25" rows="3" class="sms-input" style="width: 95%;"><? echo stripslashes($message); ?></textarea>
</div>
</td>
</tr>
<tr align="center" bgcolor="#00FF66">
<td colspan="2">
<strong>
<font color="#000000" size="2" face="Arial, Helvetica, sans-serif">
Reply Email:
</font>
</strong>
<br>
<input name="reply" type="text" class="sms-input" style="width: 90%;" value="demo@beSMS.com" size="30" maxlength="30" disabled></td>
</tr>
<tr>
<td width="40%">
<div align="center">
<font size="1" face="Arial, Helvetica, sans-serif">If you agree to the <a href="http://www.smseverywhere.com/termsofuse.htm?frm" target="_blank">terms</a> </font> </div></td>
<td width="60%" align="center">
<input name="submit" type="submit" value=" Send Now "></td>
</tr>
<tr align="center" bgcolor="#FFCC66">
<td height="18" colspan="2"><font color="#000000" size="1" face="Arial, Helvetica, sans-serif">Powered by <a href="http://www.smseverywhere.com/?msnger" target="_blank">SMS Everywhere</a></font></td>
</tr>
</table>
</form>
</body>
</html>