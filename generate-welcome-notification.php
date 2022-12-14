<? // generate-welcome-notification.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "request-fns.php";

// Verify login information here
locked('o-');

global $db;  // ???
echo date('m/d/Y H:i:s')." (local time) Generated Holiday Request for $db\n
<p><a href='event-email-monitors.php?initialize=1'><b>Initialize Event Notification Preferences</b></a>
<p><a href='index.php'>Back</a>";

saveNewSystemNotificationRequest("Welcome to LeashTime!", getMsg());

function getMsg() {
return <<<MSG
<style>
.msg {padding: 20px;padding-top: 0px;font-size: 10pt;}
</style>
<div class='msg'>
<table bgcolor='#FF8A00' width=100%><tr>
<td valign=middle class='h1'><a href='#'><img src='art/LeashtimeLarry.jpg' border=0 style='height:60px;width:60px;display:inline;'></td>
<td valign=bottom><h1>Starting Out With LeashTime</h1></td>
<td valign=bottom><img src='art/printer20.gif' onclick='window.print()' style='cursor:pointer' title='Print this page.'></td>
</tr></table>
<p>
Welcome to LeashTime!
<div style="float: right; margin-right: 15px; margin-left: 5px; padding: 20px; text-align: center; font: bold 1.22em arial,helvetica,sans-serif; color: #e27500; background: #FFDEAD;">
<a style="color: #e27500;" href="http://training.leashtime.com/beta/?vid=PIWpOVPXtp0&list=1" target="_blank">LeashTime<br>
Quick Start<br>
Video
<span style="font-size: .6em;"><br />(Click here)</span>
</a>
</div>
<p>LeashTime works best when it is configured properly to suit your business.  These checklists cover the major items 
that need to be set up before you start scheduling service with Leashtime and before you offer LeashTime to your clients.
<p>
The first checklist below is one that you should complete before you schedule any client services in LeashTime.  
The time you spend going through it may save you much confusion and frustration later.
<p>
Setting your clients loose in LeashTime is a critical step, so we also offer the 
<a href="#clients">BEFORE YOU LET YOUR CLIENTS USE LEASHTIME</a> checklist.  Completing this checklist can help you introduce 
LeashTime to your customers with confidence.
<p><span style="font-size: 16.0pt; color: red; font-weight:
          bold;"><span style="font-size: 16.0pt; font-weight: bold;"><a name="golive"></a>BEFORE YOU GO LIVE WITH LEASHTIME</a></span> (or schedule any client services) </span></p>
<p style="font-size: 1.2em;">Make sure:</p>
<p><input type="checkbox" /> Your Service List is set up with the standard prices and compensation (ADMIN &gt; Service List)</p>
<p><input type="checkbox" /> Your sitters are set up (SITTERS&gt; Sitter List)</p>
<p><input type="checkbox" /> Your sitters' login credentials are set up (Basic Info tab in sitter profile)</p>
<p><input type="checkbox" /> Custom pay rates for each sitter are set up as needed (Pay tab in the Sitter profile)</p>
<p><input type="checkbox" /> Custom prices are set up for each client as needed (Billing tab in the Client profile)</p>
<p><input type="checkbox" /> Custom client fields (extra fields for each client) are set up (ADMIN &gt; Client Management &gt; Custom Client Fields)</p>
<p><input type="checkbox" /> Custom pet fields (extra fields for each pet) are set up (ADMIN &gt; Client Management &gt; Custom Pet Fields)</p>
<p><input type="checkbox" /> Surcharges for holidays and other times are set up (ADMIN &gt; Surcharges)</p>
<p><input type="checkbox" /> The standard tax rate (if any) is set (in ADMIN &gt; Preferences &gt; Billing Preferences)</p>
<p><input type="checkbox" /> You have set up the desired Time Frames (ADMIN &gt; Client Management &gt; Named Time Frame List)</p>
<p><input type="checkbox" /> You have set up the system to email sitters their schedules (ADMIN &gt; Preferences &gt; Sitter Schedule Notifications)</p>
<p><input type="checkbox" /> You have set up the system to allow sitters to use the Mobile Sitter App if desired (ADMIN &gt; Preferences &gt; Sitter User Interface)</p>
<p><input type="checkbox" /> You have set up Event Notification to receive emails when requests are received (ADMIN &gt; Communication Preferences &gt; Event Email Monitors )</p>
<p><input type="checkbox" /> To contact <strong><a href="mailto:support@leashtime.com">support@leashtime.com</a></strong></p>
<p><strong><br /></strong></p>
<p><span style="font-size: 16.0pt; color: red; font-weight:
          bold;"><a name="clients"></a>BEFORE YOU LET YOUR CLIENTS USE LEASHTIME</span></p>
<p style="font-size: 1.2em;">Make sure:</p>
<p><input type="checkbox" /> You have completed the <strong><a href="#golive">BEFORE YOU GO LIVE WITH LEASHTIME</a></strong> Checklist above</p>
<p><input type="checkbox" /> Your Client Service List (the list of services the client sees when she schedules) is set up (ADMIN &gt; Client Management &gt; Client Service List)</p>
<p><input type="checkbox" /> You have credit card preferences set up (if you accept credit cards)</p>
<p style="padding-left: 30px;"><input type="checkbox" /> Your business's Credit Card Merchant Info is set up (in ADMIN &gt; Preferences &gt; Billing Preferences)</p>
<p style="padding-left: 30px;"><input type="checkbox" /> Your Credit Cards Accepted list is set up (in ADMIN &gt; Preferences &gt; Billing Preferences)</p>
<p><input type="checkbox" /> You have your branded client login page set up (contact LeashTime Support)</p>
<p><input type="checkbox" /> You have your Prospective Client page (&ldquo;Contact Us&rdquo;) set up (contact LeashTime Support and set ADMIN &gt; Preferences &gt; General Business &gt; Accept Prospect Requests to "yes")</p>
<p><input type="checkbox" /> You have your Pet Care Service Agreement set up if desired (ADMIN &gt; Client Management &gt; Service Agreement)</p>
<p><input type="checkbox" /> You have set up login credentials for each of your clients (Basic Info tab in client profile, or contact LeashTime Support to set up all at once)</p>
<p><input type="checkbox" /> You have TESTED THE CLIENT LOGIN:</p>
<div style="margin-left: 30px; margin-top: 0px;">
<p><input type="checkbox" /> Set up a test client.</p>
<p><input type="checkbox" /> Login as that client.</p>
<p><input type="checkbox" /> Schedule visits as that client (watch the training video if necessary).</p>
<p><input type="checkbox" /> Enter a credit card as that client (if you accept credit card payments)</p>
<p><input type="checkbox" /> Make sure you understand everything your clients will need to understand.</p>
</div>
<p><input type="checkbox" /> To contact <strong><a href="mailto:support@leashtime.com">support@leashtime.com</a></strong> with any questions you have BEFORE you let your first client use LeashTime</p>
</div>

MSG;
}