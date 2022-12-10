#!/usr/bin/php
<?// cron-all-tasks.php
// use crontab to schedule the job every half hour
set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
require_once "cron-2-fns.php";

runCrons();
