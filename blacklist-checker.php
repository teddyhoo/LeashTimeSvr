<?php // blacklist-checker.php UNFINISHED, UNTESTED 11/21/2013
// Simple DNSBL/RBL PHP function - trust me, it's better than checkdnsrr, fsock, socket_create, Net::DNSBL and Net::DNS
// Here's a [better] way to quickly check if an IP is in a DNSBL / RBL. It works on Windows and Linux,
// assuming nslookup is installed. It also supports timeout in seconds.
 
function ipInDnsBlacklist($ip, $server, $timeout=1) {
$response = array();
$host = implode(".", array_reverse(explode('.', $ip))).'.'.$server.'.';
$cmd = sprintf('nslookup -type=A -timeout=%d %s 2>&1', $timeout, escapeshellarg($host));
@exec($cmd, $response);
// The first 3 lines (0-2) of output are not the DNS response
for ($i=3; $i<count($response); $i++) {
if (strpos(trim($response[$i]), 'Name:') === 0) {
return true;
}
}
return false;
}
 
ipInDnsBlacklist('188.163.68.29', 'dnsbl.tornevall.org'); // true
ipInDnsBlacklist('127.0.0.1', 'dnsbl.tornevall.org'); // false
?>

Please sign in to comment on this gist.
