<? // reports-abbreviated.php
// Edit email prefs for one user at a time
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

//if(mattOnlyTEST()) {}
// Determine access privs
$locked = locked('o-#vr');

$pageTitle = "Reports";

include "frame.html";
?>
<style>
.greybox {padding:8px;background:lightblue;font:normal bold 1.4em arial,sans-serif;width:205px;}
.whitebox {padding:5px;background:white;font:normal bold 0.7em arial,sans-serif;margin-top:7px;}
</style>


<table><tr><td valign=top>

<div class='greybox' >
Leashtime-Only Reports
<div class='whitebox' >
<a href='reports-crm-pipeline.php'>CRM Pipeline</a><p>
<a href='reports-daily-activity.php'>Daily Activity</a><p>
<a href='https://<?= $_SERVER["HTTP_HOST"] ?>/reports-recent-logins.php'>Recent Manager Login Activity</a><p>
<a href='reports-prospect-logins.php'>Prospect Logins (2 most recent months)</a><p>
</div>
</div>
</td>
</tr>

</table>
<img src='art/spacer.gif' width=1 height=300>
<?
// ***************************************************************************
include "frame-end.html";
