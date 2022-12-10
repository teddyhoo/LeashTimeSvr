<? // native-login-report.php
// The Native Sitter App is essentially sessionless.  Every call requires a login.
/* The user agents look like:
LeashTimeMobileSitter2/1 CFNetwork/711.4.6 Darwin/14.0.0

 SELECT DISTINCT browser
FROM `tbllogin`
WHERE `browser` LIKE CONVERT( _utf8 '%sitt%'
USING latin1 )
COLLATE latin1_swedish_ci
ORDER BY browser
LIMIT 0 , 300 


SELECT o.loginid, bizptr, bizname, count(*) as logins
FROM tbllogin o
LEFT JOIN tbluser u ON u.loginid = o.loginid
LEFT JOIN tblpetbiz ON bizid = u.bizptr
WHERE success = 1 AND o.loginid != '' AND `browser` LIKE '%sitt%'
GROUP BY o.loginid

SELECT o.loginid, bizptr, bizname, count(*) as logins
FROM tbllogin o
LEFT JOIN tbluser u ON u.loginid = o.loginid
LEFT JOIN tblpetbiz ON bizid = u.bizptr
WHERE FailureCause LIKE '%S%' AND o.loginid != '' AND `browser` LIKE '%sitt%'
GROUP BY o.loginid

loginid 	bizptr 	bizname 	logins
000333 	414 	Rock Star Dog Walking & Pet Sitting 	51
ADunn 	553 	Doggie Daytrippers 	3
Amandafarrar 	2 	DoggieWalker.com 	323
Apple5 	196 	Tonka Test 	45
asitter-roxana 	7 	Petaholics 	774
bobpanzenbeck 	2 	DoggieWalker.com 	4678
corithornton 	NULL 	NULL 	38
dlifebri 	3 	Dog's Life 	188875
geoff789 	3 	Dog's Life 	714
gozde.tugce@gmail.com 	71 	The Monster Minders 	1
Jaimeisanerd@gmail.com 	7 	Petaholics 	20
Jayhof 	25 	203 Pet Service 	91667
Jody.tonka 	148 	Lake Minnetonka Pet Sitters 	8702
johnmasters 	2 	DoggieWalker.com 	224
k80king@yahoo.com 	7 	Petaholics 	271
Kbartley 	2 	DoggieWalker.com 	5497
Leticiajulian05@gmail.com 	7 	Petaholics 	8
Lmuller 	2 	DoggieWalker.com 	3727
Madtest 	196 	Tonka Test 	799
Megansylvester 	256 	Best in Pet Services, LLC 	17
Olivia 	2 	DoggieWalker.com 	355
T-holly-D 	3 	Dog's Life 	48
tonyb@aol.com 	3 	Dog's Life 	8173

203 Pet Service, Best in Pet Services, LLC, Doggie Daytrippers, DoggieWalker.com, Dog's Life , Lake Minnetonka Pet Sitters, Petaholics, Rock Star Dog Walking & Pet Sitting, The Monster Minders, Tonka Test 



*/