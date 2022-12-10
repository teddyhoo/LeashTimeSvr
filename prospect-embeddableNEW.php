<? // prospect-embeddable.php
$bizid = $_REQUEST['bizid'];
if(!$bizid) {
	echo "No bizid supplied.";
	exit;
}

require_once "common/init_session.php";
require_once "common/init_db_common.php";
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid");
if($biz) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);	
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
	$bizname = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	$biznameMention = $bizname ? "<h2>for $bizname</h2>" : '';
}

if($_REQUEST['justJavascript']) {
$SEPARATE_JAVASCRIPT_FILE_CONTENT = <<<SEPARATE_JAVASCRIPT_FILE_CONTENT
/* my_prospect-form-form-javascript.js
* This Javascript file depends on several conditions:
*
* 1. https://{$_SERVER["HTTP_HOST"]}/popcalendar.js must be included before this script
* - - and before https://{$_SERVER["HTTP_HOST"]}/popcalendar.js is included, var pathHome should be defined (usually as './')
* 2. https://{$_SERVER["HTTP_HOST"]}/check-form.js must be included before this script
*
* ... so this script should probably be loaded (in the file header, presumably), as follows:

<script language='javascript'>var pathHome = "./";</script>
<script language='javascript' src='https://{$_SERVER["HTTP_HOST"]}/popcalendar.js'></script>
<script language='javascript' src='https://{$_SERVER["HTTP_HOST"]}/check-form.js'></script>
<script language='javascript' src='my_prospect-form-form-javascript.js'></script>

*/
function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function validEmail(src) {
  var regex = /^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/;  // checks for ' character
  return regex.test(src);

}

function checkAndSend() {
    setPrettynames('meetingdate', 'Meeting Date', 'meetingtime', 'Meeting Time');
    var mincontactmsg, args;
    var args = new Array();   
    $requiredFieldJavaScript
  if(!validEmail(document.prospectinforequest.email.value) &&
     jstrim(document.prospectinforequest.phone.value) == '') {
         args[args.length] = 'Please supply an email address or phone number where we can reach you.';
         args[args.length] = '';
         args[args.length] = 'MESSAGE';
    }
  if(document.getElementById("yesmeet").checked &&
          !(jstrim(document.getElementById("meetingdate1").value)
          || jstrim(document.getElementById("meetingdate2").value)
          || jstrim(document.getElementById("meetingdate3").value))) {
         args[args.length] = 'At least one meeting date is required if you want a meeting.';
         args[args.length] = '';
         args[args.length] = 'MESSAGE';
    }
  if(document.getElementById("yesmeet")) {
        args[args.length] = 'meetingdate1';
        args[args.length] = '';
        args[args.length] = 'isDate';
        args[args.length] = 'meetingdate1';
        args[args.length] = 'NOT';
        args[args.length] = 'isPastDate';
        args[args.length] = 'meetingdate2';
        args[args.length] = '';
        args[args.length] = 'isDate';
        args[args.length] = 'meetingdate2';
        args[args.length] = 'NOT';
        args[args.length] = 'isPastDate';
        args[args.length] = 'meetingdate3';
        args[args.length] = '';
        args[args.length] = 'isDate';
        args[args.length] = 'meetingdate3';
        args[args.length] = 'NOT';
        args[args.length] = 'isPastDate';
        args[args.length] = 'meetingtime1';
        args[args.length] = 'meetingdate1';
        args[args.length] = 'RIFF';
        args[args.length] = 'meetingtime2';
        args[args.length] = 'meetingdate2';
        args[args.length] = 'RIFF';
        args[args.length] = 'meetingtime3';
        args[args.length] = 'meetingdate3';
        args[args.length] = 'RIFF';
    }
  if(MM_validateEnhancedForm(args)) {
		if(document.getElementById('modelnum')) document.getElementById("modelnum").value = "$bizid";
		document.prospectinforequest.submit();
	}
}

function toggleMeeting(el) {
    var rowShow = navigator.appName.indexOf('Internet Explorer') >= 0 ? 'block' : 'table-row';
    var display = el.id == 'yesmeet' ? rowShow : 'none';
    var disabled = el.id == 'nomeet';
    document.getElementById("meetingdatetr0").style.display = display;
    document.getElementById("meetingdatetr1").style.display = display;
    document.getElementById("meetingdatetr2").style.display = display;
    document.getElementById("meetingdatetr3").style.display = display;
    document.getElementById("meetingdate1").disabled = disabled;
    document.getElementById("meetingtime1").disabled = disabled;
    document.getElementById("meetingdate2").disabled = disabled;
    document.getElementById("meetingtime2").disabled = disabled;
    document.getElementById("meetingdate3").disabled = disabled;
    document.getElementById("meetingtime3").disabled = disabled;
}

var localDateFormat = 'mm/dd/yyyy';

function dateButtonAction(ctl, date, month, day, year) {
  var datePosition = getAbsolutePosition(document.getElementById(date.id));
  var offset = addOffsets(getTabPageOffset(date), getContainerOffset(date, 'Sheet'), getContainerOffset(date, 'contentLayout'));
  showCalendar(ctl, date, localDateFormat,"en",1,datePosition.x-offset.x, datePosition.y-offset.y);
}

function getAbsolutePosition(element) {
    var r = { x: element.offsetLeft, y: element.offsetTop };
    if (element.offsetParent) {
      var tmp = getAbsolutePosition(element.offsetParent);
      r.x += tmp.x;
      r.y += tmp.y;
    }
    return r;
}

function addOffsets() {
    var r = {x: 0, y: 0};
    for(var i=0; i < addOffsets.arguments.length; i++) {
        r.x += addOffsets.arguments[i].x;
        r.y += addOffsets.arguments[i].y;
    }
    return r;
}

function getTabPageOffset(element) {
    // since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
    while(element.offsetParent) {
        var parent = element.offsetParent;
    if(parent.id && parent.id.indexOf('tabpage_') == 0) return {x: parent.offsetLeft, y: parent.offsetTop-23}; // 23 for tab height
    element = parent;
    }
    return {x: 0, y: 0};
}

function getContainerOffset(element, containerClassName) {
    // since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
    while(element.offsetParent) {
        var parent = element.offsetParent;
    if(parent.className == containerClassName) return {x: parent.offsetLeft, y: parent.offsetTop};
    element = parent;
    }
    return {x: 0, y: 0};
}

function checkReferral(el) {
	if(el.options[el.selectedIndex].value == -1) {
		el.selectedIndex = 0;
		alert('Please select a more specific option.');
	}
	var referralnote = document.getElementById('x-std-referralnote');
	if(referralnote) referralnote.disabled = el.selectedIndex == 0;
}


window.onload = onWindowLoad;

function onWindowLoad() {
	init();
	if(document.getElementById('x-std-referralnote')) document.getElementById('x-std-referralnote').title = 'A referral option must be chosen to enter a note here.';
	checkReferral(document.getElementById('x-std-referralcode'));
}
SEPARATE_JAVASCRIPT_FILE_CONTENT;
	
	echo $SEPARATE_JAVASCRIPT_FILE_CONTENT;
	exit;
}

$referralBlock = referralBlock();

//echo $referralBlock;exit;

if($referralBlock) 	{
	$referralBlockHTML = <<<REFERRAL
&lt;tr&gt;
&lt;td&gt;How often do you need pet care services?&lt;/td&gt;
&lt;td&gt;
&lt;<span class="start-tag">select</span><span class="attribute-name"> name</span>=<span class="attribute-value">"<font color="#ff0000">x-select-Frequency</font>" </span><span class="attribute-name">class</span>=<span class="attribute-value">"formGrey"</span><span class="attribute-value"></span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Every Day"</span>&gt;Every Day&lt;/<span class="end-tag">option</span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Several Times a week"</span>&gt;Several Times a week&lt;/<span class="end-tag">option</span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Several times a mont"</span>&gt;Several times a month&lt;/<span class="end-tag">option</span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Several times a year"</span>&gt;Several times a year&lt;/<span class="end-tag">option</span>&gt;
&lt;/<span class="end-tag">select</span>&gt;
&lt;/td&gt;
&lt;/tr&gt;
REFERRAL;
	$referralBlockPostHTML = "When you receive a request where \"Frequency\" has been specified, the"
    ." request will include a field with that label, as in:<br>"
    ."<br><b>Frequency: </b>Every Day<br>";
}

else {
	$referralBlockHTML = <<<REFERRAL
<div style='width:50%;color:darkgreen;'><i>If you had referral categories defined, a
Referral question that could be saved in the client&apos;s profile would be pre-supplied in this form.
The example below will not yield information that can be saved in the client profile&apos;s
Referral fields.</i></div>
&lt;tr&gt;
&lt;td&gt;How did You Hear about Us?&lt;/td&gt;
&lt;td&gt;
&lt;<span class="start-tag">select</span><span class="attribute-name"> name</span>=<span class="attribute-value">"<font color="#ff0000">x-select-Referral</font>" </span><span class="attribute-name">class</span>=<span class="attribute-value">"formGrey"</span><span class="attribute-value"></span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Google Search" </span><span class="attribute-name">selected</span>=<span class="attribute-value">"selected"</span>&gt;Google Search&lt;/<span class="end-tag">option</span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Other Web Search"</span>&gt;Other Web Search&lt;/<span class="end-tag">option</span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Existing Client Referral"</span>&gt;Existing Client Referral&lt;/<span class="end-tag">option</span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Neighborhood Newsletter"</span>&gt;Neighborhood Newsletter&lt;/<span class="end-tag">option</span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Doggywalker.com Team Member"</span>&gt;Doggywalker.com Team Member&lt;/<span class="end-tag">option</span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Community/Dog Park Notice Board"</span>&gt;Community/Dog Park Notice Board&lt;/<span class="end-tag">option</span>&gt;
 &lt;<span class="start-tag">option</span><span class="attribute-name"> value</span>=<span class="attribute-value">"Other"</span>&gt;Other&lt;/<span class="end-tag">option</span>&gt;

&lt;/<span class="end-tag">select</span>&gt;
&lt;/td&gt;
&lt;/tr&gt;
REFERRAL;
	$referralBlock = <<<REFERRAL
	  <tr>

	    <td colspan="2"><select name="x-select-Referral" class="formGrey" id="referral">
	      <option value="Google Search" selected="selected">Google Search</option>
	      <option value="Other Web Search">Other Web Search</option>
	      <option value="Existing Client Referral">Existing Client Referral</option>
	      <option value="Neighborhood Newsletter">Neighborhood Newsletter</option>
	      <option value="Dog's Life Team Member">Dog&apos;s Life Team Member</option>
	      <option value="Community/Dog Park Notice Board">Community/Dog Park Notice Board</option>
	      <option value="Other">Other</option>
	    </select></td>
	  </tr>
REFERRAL;
}

$source = <<<RAWHTML
<div style='background:cornsilk'><h2>Embeddable Prospective Client Form</h2>
$biznameMention
<hr>
		The prospective client form below can be embedded in your website.  It produces a client Request which
		appears on the manager&#39;s Home Page.  This request offers a button for creating a new client with
		information contained in the request.
		<p>
		To customize this form, remove these instructions and then supply a valid URL for the
		<b>goback</b> form element, which is the page to which the user&#39;s browser will be redirected 
		after the form has been submitted.  That page might say, "Thanks for contacting us; we'll get back to you
		shortly" or something of that kind.
		<p>
		<span style='font-size:1.3em'>Standard Fields</span><p>
		Here are the names/ids of form fields that LeashTime will incorporate into a new client if the manager chooses to
		convert Prospect request into a new a client:
-		<ul>
		<li><b>fname</b> - client First name
		<li><b>lname</b> - client Last name
		<li><b>phone</b> - client Home phone
		<li><b>email</b> - client email address
		<li><b>street1</b> - client address
		<li><b>street2</b> - client address, line 2
		<li><b>city</b> - client city
		<li><b>state</b> - client state
		<li><b>zip</b> - client ZIP / Postal Code
		<li><b>x-std-fname2</b> - Alt First name 
		<li><b>x-std-lname2</b> - Alt Last name  
		<li><b>x-std-mailstreet1</b> - client Mailing Address
		<li><b>x-std-mailstreet2</b> - client Mailing Address, line 2
		<li><b>x-std-mailcity</b> - client Mailing Address City
		<li><b>x-std-mailstate</b> - client Mailing Address State
		<li><b>x-std-mailzip</b> - client Mailing Address ZIP / Postal Code
		<li><b>x-std-cellphone</b> - client cellphone
		<li><b>x-std-workphone</b> - client work phone
		<li><b>x-std-fax</b> - client FAX
		<li><b>x-std-pager</b> - client pager
		<li><b>x-std-email2</b> - client Alt email
		<li><b>x-std-directions</b> - Directions to client home
		<li><b>x-std-alarmcompany</b> - Alarm Company
		<li><b>x-std-birthday</b> - Birthday
		<li><b>x-std-leashloc</b> - Leash Location
		<li><b>x-std-foodloc</b> - Food Location
		<li><b>x-std-parkinginfo</b> - Parking Info
		<li><b>x-std-emergencycarepermission</b> - Emergency Care Permission
		<li><b>x-std-referralcode</b> - Referral category code (use only when you have referral codes set up)
		<li><b>x-std-referralnote</b> - Referral note (use only when you have referral codes set up)
		</ul>
		<b>Note:</b> Form elements for the fields above should use these names all lower case, and each name should be used
		for both the <u>name</u> attribute <b>and</b> the <u>id</u> attribute for the element it is used in.  E.g.,
		<p align=center>
		<code>Cell phone: &lt;input id="x-std-cellphone" name="x-std-cellphone" size="30"&gt;</code>
		<p>
		&nbsp;
    <hr width="100%" size="2"><br>
		<span style='font-size:1.3em'>Custom Fields</span><p>

		Besides the standard LeashTime fields that are automatically incorporated when a prospect is
		converted to a new LeashTime client, this form may be modified to
		contain custom prospect fields, which can be added to your form as described below.
		<p>
    Custom prospect fields are not automatically transferred to the new
    client when the client is created, but are a way for you to collect
    a little extra information in the Prospect form to use or ignore as
    you like.&nbsp; Here's how to set them up:<br>
    <br>
    <b>Field name format:</b><br>
    <br>
    To be noticed by LeashTime, a custom prospect field's name must have
    the following format:<br>
    <br>
    x-<i><font color="#000099">type</font>-<font color="#009900">label</font></i><br>
    <br>
    where&nbsp;<i><font color="#009900">label</font></i> is the label exactly
    as you wish it to appear when you view the request in LeashTime.<br>
    <br>
    and <i><font color="#000099">type</font></i> is one of:<br>
    <br>
    <blockquote><b>select </b>- if the form item is a single-choice
      select element<br>
      <b>oneline </b>- if the form item is a text input element<br>
      <b>radio </b>- for a set of radio input elements sharing the same
      name<br>
      <b>checkbox</b>- if the form item is a checkbox input element<br>
      <b>multiline </b>- if the form item is a textarea element<br>
    </blockquote>
    <br>
    The name is case-sensitive.&nbsp; The "x" and the <i><font
        color="#000099">type</font></i> must be lowercase, and the case
    of the label should be exactly what you want to see when you view
    the request in LeashTime.<br>
    <br>
    For example:<br>
    <br>
    <pre id="line322">
$referralBlockHTML
		</pre>
$referralBlockPostHTML
<p>&nbsp;
<hr>
<p>
<span style='font-size:1.3em'>Formbot (Spam) Handling</span><p>
Annoying programs called formbots identify web pages with forms in them, fill the forms out,
and then submit the forms.  Formbot hackers do this in the hope that <u>someone</u> will read the
contents of the submitted form and then go out and buy whatever the message is selling. This can lead 
to the appearance of spurious Prospects in your LeashTime Request queue.
<p>
To combat this, we include two invisible fields in the Prospect form.
<p>
<b>address3</b> is a text field invisible to humans and with no value, but visible to the formbot.  If 
the formbot fills out this field, LeashTime knows the form was not submitted by a human and marks it 
as spam.
<p>
<b>modelnum</b> is a hidden field with no value.  When a human clicks the submit button, the value of 
this field is filled with your LeashTime business ID.  When LeashTime receives the form, if 
modelnum&apos;s value does not equal your LeashTime business ID, LeashTime knows the form was not 
submitted by a human and marks it as spam.
<p>
LeashTime does not throw away spam.  Instead it characterizes a suspicious request as "Prospect Spam",
and marks it resolved.  So you are not bothered by a spam attempt on your homepage, but you can always 
find it if you review resolved requests.
<hr>
<span style='font-size:1.3em'>Required Fields</span><p>
<p>
By default, this form requires only that the phone field <u>or</u> the email field be filled in, and that the 
email field contain a legal email address (and not gibberish, for example).
<p>
If you do not like this feature, remove the following block from the checkAndSend function:
<p>
  <pre>if(!validEmail(document.prospectinforequest.email.value) &&
     jstrim(document.prospectinforequest.phone.value) == '') {
         args[args.length] = 'Please supply an email address or phone number where we can reach you.';
         args[args.length] = '';
         args[args.length] = 'MESSAGE';
    }</pre>

<p>
You can mark other fields required (meaning that the form will not be sent with those fields empty).  
To do this, give the field the attribute <b>required="1"</b>.  If the form is submitted without this field,
an alert appears saying "Warning - XXX is required".  You can control what "XXX" is with another attribute,
<b>prettyname</b>.
<p>
Example:<p>
    <pre>&lt;input type="text" id="ssn" name="ssn" required="1" prettyname="Your Social Security Number"&gt;</pre>
<p>
You will have to supply your own red asterisks. :-)
<hr>
<p>
<span style='font-size:1.3em'>And Here is the Form</span><p>
<hr>
</div>

<form action="https://{$_SERVER["HTTP_HOST"]}/prospect-request.php" method="post" name="prospectinforequest">
<input type='hidden' id='pbid' name='pbid' value='##BIZID##'>
<input type='hidden' id='goback' name='goback' value='http://YOUR_WEB_SITE.com/a_thank_you_page.htm'> 
<input type='text' style='display:none' id='address3' name='address3' value=''> 
<input type='hidden' id='modelnum' name='modelnum' value=''> 
	<table>
	  <tr>

	    <td><label for='fname' class="bodyText">First Name:</label></td>
	    <td><input name='fname' class="formGrey" id='fname' size="30" maxlength="45" /></td>
	  </tr>
	  <tr>
	    <td><label for='lname' class="bodyText">Last Name:</label></td>
	    <td><input name='lname' class="formGrey" id='lname' size="30" maxlength="45" /></td>
	  </tr>
	  <tr>

	    <td><label for='phone' class="bodyText">Phone:</label></td>
	    <td><input name='phone' class="formGrey" id='phone' size="30" maxlength="45" /></td>
	  </tr>
	  <tr>
	    <td><label for='whentocall' class="bodyText">Best time for us to call:</label></td>
	    <td><input name='whentocall' class="formGrey" id='whentocall' size="30" maxlength="45" /></td>
	  </tr>
	  <tr>

	    <td><label for='email' class="bodyText">Email:</label></td>
	    <td><input name='email' class="formGrey" id='email' size="30" maxlength="60" /></td>
	  </tr>
	  <tr>
	    <td><label for='street1' class="bodyText">Address:</label></td>
	    <td><input name="street1" type="text" class="formGrey" id="street1" value="" size="30" /></td>
	  </tr>
	  <tr>
	    <td><label for='street2' class="bodyText">Address2:</label></td>
	    <td><input name="street2" type="text" class="formGrey" id="street2" value="" size="30" /></td>
	  </tr>
	  <tr>
	    <td><label for='city' class="bodyText">City, State, Zip</label></td>
	    <td><input name="city" type="text" class="formGrey" id="city" value="" size="20" />
<select name="state"> 
<option value="" selected="selected">Select a State</option> 
<option value="AL">Alabama</option> 
<option value="AK">Alaska</option> 
<option value="AZ">Arizona</option> 
<option value="AR">Arkansas</option> 
<option value="CA">California</option> 
<option value="CO">Colorado</option> 
<option value="CT">Connecticut</option> 
<option value="DE">Delaware</option> 
<option value="DC">District Of Columbia</option> 
<option value="FL">Florida</option> 
<option value="GA">Georgia</option> 
<option value="HI">Hawaii</option> 
<option value="ID">Idaho</option> 
<option value="IL">Illinois</option> 
<option value="IN">Indiana</option> 
<option value="IA">Iowa</option> 
<option value="KS">Kansas</option> 
<option value="KY">Kentucky</option> 
<option value="LA">Louisiana</option> 
<option value="ME">Maine</option> 
<option value="MD">Maryland</option> 
<option value="MA">Massachusetts</option> 
<option value="MI">Michigan</option> 
<option value="MN">Minnesota</option> 
<option value="MS">Mississippi</option> 
<option value="MO">Missouri</option> 
<option value="MT">Montana</option> 
<option value="NE">Nebraska</option> 
<option value="NV">Nevada</option> 
<option value="NH">New Hampshire</option> 
<option value="NJ">New Jersey</option> 
<option value="NM">New Mexico</option> 
<option value="NY">New York</option> 
<option value="NC">North Carolina</option> 
<option value="ND">North Dakota</option> 
<option value="OH">Ohio</option> 
<option value="OK">Oklahoma</option> 
<option value="OR">Oregon</option> 
<option value="PA">Pennsylvania</option> 
<option value="RI">Rhode Island</option> 
<option value="SC">South Carolina</option> 
<option value="SD">South Dakota</option> 
<option value="TN">Tennessee</option> 
<option value="TX">Texas</option> 
<option value="UT">Utah</option> 
<option value="VT">Vermont</option> 
<option value="VA">Virginia</option> 
<option value="WA">Washington</option> 
<option value="WV">West Virginia</option> 
<option value="WI">Wisconsin</option> 
<option value="WY">Wyoming</option>
</select>	    
	      <input name="zip" type="text" class="formGrey" id="zip" value="" size="10" /></td>
	  </tr>

	  <tr>
	    <td colspan="2"><label for='pets' class="bodyText">Tell us about your pets (names, kind):</label></td>
	  </tr>
	  <tr>
	    <td colspan="2"><textarea name='pets' cols="50" rows="3" class="formGrey" id='pets'></textarea></td>
	  </tr>
	  <tr>
	    <td colspan="2"><label for='note' class="bodyText">How can we help you?</label></td>

	  </tr>
	  <tr>
	    <td colspan="2"><textarea name='note' cols="50" rows="4" class="formGrey" id='note'></textarea></td>
	  </tr>
	  $referralBlock
	  <tr>
	    <td colspan="2"><label for='note' class="bodyText">Three favorite movies?</label></td>

	  </tr>
	  <tr>
	    <td colspan="2"><textarea name='x-multiline-Favorite Movies' cols="50" rows="4" class="formGrey" id='x-multiline-Favorite Movies'></textarea></td>
	  </tr>
	  <tr>
	    <td colspan="2"><label for='note' class="bodyText">Underwear?</label></td>

	  </tr>
	  <tr>
	    <td colspan="2">
	    	<input type="radio" name="x-radio-Underwear" value="briefs" /> briefs
	    	<input type="radio" name="x-radio-Underwear" value="boxers" /> boxers
	    	<input type="radio" name="x-radio-Underwear" value="bloomers" /> bloomers
	    </td>
	  </tr>
	  <tr>
	    <td colspan="2"><label for='note' class="bodyText">Birthday?</label></td>

	  </tr>
	  <tr>
	    <td colspan="2">
	    	<input name="x-oneline-Birthday"  size="30" maxlength="60"/>
	  	</td>
	  	
		<tr><td colspan=2>Would you like to schedule a meeting with us? <input name='meet' id='yesmeet' type='radio' onChange='toggleMeeting(this)'> <label for='yesmeet'>yes</label> <input id='nomeet' name='meet' type='radio' onclick='toggleMeeting(this)' CHECKED> <label for='nomeet'>no</label></td></tr>

		<tr id='meetingdatetr0' style='display:none'><td colspan=2>Please tell us when is convenient for you:</td></tr>
		<tr id='meetingdatetr1' style='display:none'>
		<td>
		Date: <input DISABLED class="dateInput" id="meetingdate1" name="x-oneline-meetingdate1" autocomplete="off" size=12 
							value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
					<img src="https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif" 
							onclick='dateButtonAction(this,document.getElementById("meetingdate1"),"1","15","2005")'>
		</td>
		<td style='padding-left:5px;'>
		 at what time? <input DISABLED id="meetingtime1" name="x-oneline-meetingtime1" size=10>
		</td>
		</tr>
		<tr id='meetingdatetr2' style='display:none'>
		<td>
		Date: <input DISABLED class="dateInput" id="meetingdate2" name="x-oneline-meetingdate2" autocomplete="off" size=12 
							value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
					<img src="https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif" 
							onclick='dateButtonAction(this,document.getElementById("meetingdate2"),"1","15","2005")'>

		</td>
		<td style='padding-left:5px;'>
		 at what time? <input DISABLED id="meetingtime2" name="x-oneline-meetingtime2" size=10>
		</td>
		</tr>
		<tr id='meetingdatetr3' style='display:none'>
		<td>
		Date: <input DISABLED class="dateInput" id="meetingdate3" name="x-oneline-meetingdate3" autocomplete="off" size=12 
							value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
					<img src="https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif" 
							onclick='dateButtonAction(this,document.getElementById("meetingdate3"),"1","15","2005")'>
		</td>
		<td style='padding-left:5px;'>
		 at what time? <input DISABLED id="meetingtime3" name="x-oneline-meetingtime3" size=10>

		</td>
		</tr>
	  
	  <tr>
	  <td colspan="2">
			<noscript>
			<span style='font-size:2em;font-weight:bold;color:red;'>This form will not work unless you enable Javascript on this device!</span>
			<p>Here&apos;s how: <ul>
			<li>on the <a target=help href='http://timeread.hubpages.com/hub/How-to-disable-or-enable-JavaScript-on-the-iPad-iPhone-or-any-iOS-device'>iPad</a>
			<li>on the <a target=help href='http://timeread.hubpages.com/hub/How-to-disable-or-enable-JavaScript-on-the-iPad-iPhone-or-any-iOS-device'>iPhone</a>
			<li>on an <a target=help href='http://timeread.hubpages.com/hub/How-to-enable-disable-JavaScript-on-the-Droid-Android-phone'>Android Phone</a>
			<li>in <a target=help href='http://enable-javascript.com/'>Internet Explorer</a>
			<li>in <a target=help href='http://enable-javascript.com/'>Google Chrome</a>
			<li>in <a target=help href='http://enable-javascript.com/'>Firefox</a>
			</ul>
			</noscript>
	    <input type="button" value='Send Request' onclick='checkAndSend()' /></td>
	  </tr>
	</table>                        
</form>

<script language='javascript'>
var pathHome = "./";
</script>
<script language='javascript' src='https://{$_SERVER["HTTP_HOST"]}/popcalendar.js'></script>
<script language='javascript' src='https://{$_SERVER["HTTP_HOST"]}/check-form.js'></script>

<script language='javascript'>
function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function validEmail(src) {
  var regex = /^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/;  // checks for ' character
  return regex.test(src);

}

function checkAndSend() {
    setPrettynames('meetingdate', 'Meeting Date', 'meetingtime', 'Meeting Time');
    var mincontactmsg, args;
    var args = new Array();   
    $requiredFieldJavaScript
  if(!validEmail(document.prospectinforequest.email.value) &&
     jstrim(document.prospectinforequest.phone.value) == '') {
         args[args.length] = 'Please supply an email address or phone number where we can reach you.';
         args[args.length] = '';
         args[args.length] = 'MESSAGE';
    }
  if(document.getElementById("yesmeet").checked &&
          !(jstrim(document.getElementById("meetingdate1").value)
          || jstrim(document.getElementById("meetingdate2").value)
          || jstrim(document.getElementById("meetingdate3").value))) {
         args[args.length] = 'At least one meeting date is required if you want a meeting.';
         args[args.length] = '';
         args[args.length] = 'MESSAGE';
    }
  if(document.getElementById("yesmeet")) {
        args[args.length] = 'meetingdate1';
        args[args.length] = '';
        args[args.length] = 'isDate';
        args[args.length] = 'meetingdate1';
        args[args.length] = 'NOT';
        args[args.length] = 'isPastDate';
        args[args.length] = 'meetingdate2';
        args[args.length] = '';
        args[args.length] = 'isDate';
        args[args.length] = 'meetingdate2';
        args[args.length] = 'NOT';
        args[args.length] = 'isPastDate';
        args[args.length] = 'meetingdate3';
        args[args.length] = '';
        args[args.length] = 'isDate';
        args[args.length] = 'meetingdate3';
        args[args.length] = 'NOT';
        args[args.length] = 'isPastDate';
        args[args.length] = 'meetingtime1';
        args[args.length] = 'meetingdate1';
        args[args.length] = 'RIFF';
        args[args.length] = 'meetingtime2';
        args[args.length] = 'meetingdate2';
        args[args.length] = 'RIFF';
        args[args.length] = 'meetingtime3';
        args[args.length] = 'meetingdate3';
        args[args.length] = 'RIFF';
    }
  if(MM_validateEnhancedForm(args)) {
		if(document.getElementById('modelnum')) document.getElementById("modelnum").value = "$bizid";
		document.prospectinforequest.submit();
	}
}

function toggleMeeting(el) {
    var rowShow = navigator.appName.indexOf('Internet Explorer') >= 0 ? 'block' : 'table-row';
    var display = el.id == 'yesmeet' ? rowShow : 'none';
    var disabled = el.id == 'nomeet';
    document.getElementById("meetingdatetr0").style.display = display;
    document.getElementById("meetingdatetr1").style.display = display;
    document.getElementById("meetingdatetr2").style.display = display;
    document.getElementById("meetingdatetr3").style.display = display;
    document.getElementById("meetingdate1").disabled = disabled;
    document.getElementById("meetingtime1").disabled = disabled;
    document.getElementById("meetingdate2").disabled = disabled;
    document.getElementById("meetingtime2").disabled = disabled;
    document.getElementById("meetingdate3").disabled = disabled;
    document.getElementById("meetingtime3").disabled = disabled;
}

var localDateFormat = 'mm/dd/yyyy';

function dateButtonAction(ctl, date, month, day, year) {
  var datePosition = getAbsolutePosition(document.getElementById(date.id));
  var offset = addOffsets(getTabPageOffset(date), getContainerOffset(date, 'Sheet'), getContainerOffset(date, 'contentLayout'));
  showCalendar(ctl, date, localDateFormat,"en",1,datePosition.x-offset.x, datePosition.y-offset.y);
}

function getAbsolutePosition(element) {
    var r = { x: element.offsetLeft, y: element.offsetTop };
    if (element.offsetParent) {
      var tmp = getAbsolutePosition(element.offsetParent);
      r.x += tmp.x;
      r.y += tmp.y;
    }
    return r;
}

function addOffsets() {
    var r = {x: 0, y: 0};
    for(var i=0; i < addOffsets.arguments.length; i++) {
        r.x += addOffsets.arguments[i].x;
        r.y += addOffsets.arguments[i].y;
    }
    return r;
}

function getTabPageOffset(element) {
    // since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
    while(element.offsetParent) {
        var parent = element.offsetParent;
    if(parent.id && parent.id.indexOf('tabpage_') == 0) return {x: parent.offsetLeft, y: parent.offsetTop-23}; // 23 for tab height
    element = parent;
    }
    return {x: 0, y: 0};
}

function getContainerOffset(element, containerClassName) {
    // since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
    while(element.offsetParent) {
        var parent = element.offsetParent;
    if(parent.className == containerClassName) return {x: parent.offsetLeft, y: parent.offsetTop};
    element = parent;
    }
    return {x: 0, y: 0};
}

function checkReferral(el) {
	if(el.options[el.selectedIndex].value == -1) {
		el.selectedIndex = 0;
		alert('Please select a more specific option.');
	}
	var referralnote = document.getElementById('x-std-referralnote');
	if(referralnote) referralnote.disabled = el.selectedIndex == 0;
}


window.onload = onWindowLoad;

function onWindowLoad() {
	init();
	if(document.getElementById('x-std-referralnote')) document.getElementById('x-std-referralnote').title = 'A referral option must be chosen to enter a note here.';
	checkReferral(document.getElementById('x-std-referralcode'));
}
</script> 
RAWHTML;

$source = str_replace('##BIZID##', $bizid, $source);

if(!$_GET['preview']) {
	header("Content-Type: text/plain");
	$d = date('Y.m.d-H.i');
	$r = $_REQUEST['recent'] ? 'recent-'.$_REQUEST['recent'] : 'all';
	$disp = $_GET['save'] ? 'attachment' : 'inline';
	header("Content-Disposition: $disp; filename=client-prospect-form.htm ");
}
echo $source;

function referralOptions($cats, $level=0) {
	global $referralOptions;
	if($level==0) {
		$referralOptions = "\n<option value=''>Choose One</option>";
		$cats = referralCategoryTree($cats);
	}
	foreach($cats as $cat => $subcats) {
		$label = explode('|', $cat);
		$dashes = '';
		for($i=0;$i<$level;$i++) $dashes .= "&nbsp;&nbsp;&nbsp;";
		$style = $subcats ? 'style="color:#555555;font-style:italic;"' : 'style="color:black"';
		$val = $subcats ? "-1" : $label[0];
		$referralOptions .= "\n<option $style value='$val'>$dashes{$label[1]}</option>";
//print_r($referralOptions);
//echo "BANG! ".print_r($subcats, 1);exit;	
		referralOptions($subcats, $level+1);
	}
}

function referralBlock() {
	require_once "referral-fns.php";
	require_once "gui-fns.php";
	$cats = getReferralCategories();
//print_r($cats);	
//echo "BANG!";exit;	
	if(!$cats) return;
	referralOptions($cats);
	global $referralOptions;
	ob_start();
	ob_implicit_flush(0);
	selectRow('How did you hear about us?', "x-std-referralcode", null, $referralOptions, $onChange="checkReferral(this)", $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null);
	inputRow('Referral note:', 'x-std-referralnote', $value=null, $labelClass=null, $inputClass=null);
	$block = ob_get_contents();
	ob_end_clean();
	return $block;
	
}
if($dbhost1) reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
