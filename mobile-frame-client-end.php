<? // mobile-frame-client-end.php
if(!$delayPageContent) echo "</div>\n"; 
?>
<script language='javascript'>
<?
		if($_SESSION['user_notice']) {
			$_SESSION['user_notice'] = str_replace("\r", ' ', str_replace("\n", ' ', $_SESSION['user_notice']));
			echo str_replace('XXXX', $_SESSION['user_notice'],
												'$(document).ready(function(){$.fn.colorbox({html:"XXXX", width:"750", height:"470", scrolling: true, opacity: "0.3"});
												});');
			unset($_SESSION['user_notice']);
		}

if($_SESSION['popup_message']) {
			echo "\nalert(\"{$_SESSION['popup_message']}\");";
			unset($_SESSION['popup_message']);
		}
?>
</script>
		
</body>
</html>