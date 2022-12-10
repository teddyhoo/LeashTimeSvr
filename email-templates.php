<? // email-templates.php
$pageTitle = "Email Templates";
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
require_once "email-template-fns.php";
include "gui-fns.php";
locked('o-');

$max_rows = 999;

$columns = array('label'=>'Template', 'subject'=>'Subject', 'active'=>'Active');
$colKeys = array_keys($columns);
$columnSorts = null;
extract(extractVars('sort,newTemplate,deletedTemplate', $_REQUEST));
if($sort) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  $orderClause = "ORDER BY $sort_key $sort_dir";
}
else $orderClause = 'ORDER BY label asc';

/*
$templates = fetchAssociations("SELECT * FROM tblemailtemplate WHERE targettype = 'clients'");
$data = array();
if($templates) foreach($templates as $template) {
  $datum = $template;
  $datum['label'] = fauxLink($datum['label'], "openConsoleWindow(\"edittemplate\", 'email-template-edit.php?id={$template['templated']}',700,500)')");
  $data[] = $datum;
}*/

// ***************************************************************************
include "frame.html";

	


if(isset($newTemplate)) {
	echo "<span class='pagenote'>Template was successfully added.</span><p>";
}
if(isset($deletedTemplate)) {
	echo "<span class='pagenote'>Template was deleted.</span><p>";
}
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass=null, $columnSorts=null) {
echoButton('', "Add New Template", "openConsoleWindow(\"edittemplate\", \"email-template-edit.php\",700,500)");

//echo "<h3>Client Email Templates</h3>";
echo "<div class='daycalendardaterow' style='margin-top:10px;margin-bottom:10px;font-size:1.2em;'>Client Email Templates</div>";
templateSection('client');

//echo "<h3>Sitter Email Templates</h3>";
echo "<div class='daycalendardaterow' style='margin-top:10px;margin-bottom:10px;font-size:1.2em;'>Sitter Email Templates</div>";

templateSection('provider');

//echo "<h3>Other Templates</h3>";
echo "<div class='daycalendardaterow' style='margin-top:10px;margin-bottom:10px;font-size:1.2em;'>Other Templates</div>";

templateSection('other');

include "refresh.inc";				

?>
<script language='javascript'>

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function update(aspect, value) {
	refresh();
}

</script>
<p><img src='art/spacer.gif' height=300>
<?
include "frame-end.html";


function templateSection($type) {
	global $columns, $columnSorts;
	$templates = ensureStandardTemplates($type);
	if($templates) {
		foreach($templates as $template) {
			$datum = $template;
			$systemPrefix = strpos($template['label'], standardPrefix()) === 0 ? standardPrefix() : (
												strpos($template['label'], undeletablePrefix()) === 0 ? undeletablePrefix() : null);
			
			$baseLabel = strpos($datum['label'], $systemPrefix) === 0 ? '<b>'.substr($datum['label'], strlen($systemPrefix)).'</b>' : $datum['label'];
			$datum['label'] = fauxLink($baseLabel, "openConsoleWindow(\"edittemplate\", \"email-template-edit.php?id={$template['templateid']}\",700,500)')", 1);
			$datum['active'] = $datum['active'] ? 'Yes' : '<font color="red">No</font>';
			$data[] = $datum;
			$rowClass = 'futuretask'.($datum['active'] == 'Yes' ? '' : ' olderappointment');
			$rowClasses[] = $rowClass;
		}
		tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses);
	}
	else echo "No email templates found.";
}