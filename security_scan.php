<?php
/*
Author: Adam Lyons
Source: https://github.com/missilefish/wordpress-security-scanner

The source updates often, be sure to check the project page on Git from time to time. 
*/





$time_start = microtime(true);

$_filename = 'security_scan.php';
$alarms = array();
$interactive = 0;
$force = 0;
$lock = 0;
$unlock = 0;
$perms = 0;

/* if you want to get your own notifications just update $email='your@dot.com' */
// If you detect any new patterns please share on GIT.
$email_encoded = 'YWRhbUBtaXNzaWxlZmlzaC5jb20';
$email = base64_decode(strtr($email_encoded, '-_', '+/'));
$total = 0; $force=0;

// Script example.php
$shortopts  = "";
$shortopts .= "f::";  // Required value
$shortopts .= "p::"; // Optional value
$shortopts .= "l::"; // Optional value
$shortopts .= "r::"; // Optional value
$shortopts .= "u::"; // Optional value
$shortopts .= "h"; // These options do not accept values

$longopts  = array(
    "force::",     // Required value
    "prompt::",    // Optional value
    "lock::",    // Optional value
    "perms::",    // Optional value
    "unlock::",    // Optional value
    "help",        // No value
);
$opts = getopt($shortopts, $longopts);

foreach (array_keys($opts) as $opt) switch ($opt) {
  case 'perms':
    $perms= $opts['perms'];
    break;
  case 'force':
    $force= $opts['force'];
    break;
  case 'lock':
    $lock = $opts['lock'];
    break;
  case 'unlock':
    $unlock = $opts['unlock'];
    break;
  case 'prompt':
    $interactive= $opts['prompt'];
    break;
  case 'h':
    //print_help_message();
    exit(1);
}


if($lock) {
	/* CHANGE THESE TO YOUR PERMISSION/OWNER PREFS FOR LOCKDOWN */
	$uid = 'root';
	$gid = 'root';
	$wpuid = 'nginx';
	$wpgid = 'nginx';
} elseif($unlock) {
	/* CHANGE THESE TO YOUR PERMISSION/OWNER PREFS ALLOWING AUTO-UPDATE*/
	$uid = 'nginx';
	$gid = 'root';
	$wpuid = 'nginx';
	$wpgid = 'nginx';
} else {
	// DEFAULT
	$uid = 'root';
	$gid = 'root';
	$wpuid = 'nginx';
	$wpgid = 'nginx';
}


print "\nUsage: To fix wordpress file permissions, run with --perms=1, run with --force=1 or --prompt=1 to enable an interactive reporting (prompt) option or run with force to disable any detected files without prompt. Default options will only report and email findings, IN ADDITION, the default run will LOCKDOWN the file permissions.\n";
print "\nWarning: This script will change file permissions to the settings above for all files and directories \n         /wp-content and below will be writable by your web process $wpuid:$wpgid and all other files will be owned and only writable by $uid:$gid.\n";
print "\nSETTINGS: -- Interactive: $interactive Force: $force Lockdown: $lock Unlock: $unlock\n\n";

if($interactive) {
	print "Alerts will cause a pause in script execution, to disable run without any arguments\n\n";
} else {
	print "WARNING: This script is NOT running interactive, only an email report will be generated. To enable run with ./$_filename --prompt=1\n\n";
}

if($force) {
	print "WARNING: FORCE ACTIVE - no prompting for CHMOD 0000 operations\n\n\n";
}


$path = getcwd();
print "Getting file list and updating permissions...\n";
if(!$perms) { print "\nNOT UPDATING PERMISSIONS. IF YOU WANT PERMISSION AUDIT/FIX RUN WITH --perms=1\n\n"; }
$files = recursiveDirList($path);
print "\nScanning files...";

foreach ($files as $filename) {
	print ".";
	$total++;
	$line_number = 0;
	#print "processing: $path/$filename\n";
	$break = explode('/', $filename);
	$c_filename = $break[count($break) - 1]; 
	if($c_filename !==  $_filename) {
		$handle = fopen("$path/$filename", "r");
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				// process the line read.
				$line_number++;
				$patterns = array("source=base64_decode", 
					"eval.*base64_decode", 
					"POST.*execgate",
					"touch\(\"wp-optionstmp.php\"",
					"file_put_contents.*wp-options",
					"touch.*wp-options\.php",
					//"@move_uploaded_file\(",
					//"if\ (@move_uploaded_file($files['tmp_name'][$i], $path))
					"code_inject_sape",
					"xmlrpc.php\".*mktime\(",
					"jquery.php\".*mktime\(",
					"exec\(\"find\ ",
					"exec\(\'find\ ",
					"assert\((\"|\')e(\"|\')\.(\"|\')v(\"|\')",
					"\(gzinflate\(str_rot13\(base64_decode",
					"preg_replace\((\"|\')\/\.\*\/e(\"|\')\,(\"|\')",
					"\\\x62\\\\x61\\\\x73\\\\x65\\\\x36\\\\x34\\\\x5f\\\\x64\\\\x65\\\\x63\\\\x6f\\\\x64\\\\x65",
					"\\\\x65\\\\x76\\\\x61\\\\x6C\\\\x28\\\\x67\\\\x7A\\\\x69\\\\x6E\\\\x66\\\\x6C\\\\x61\\\\x74\\\\x65\\\\x28\\\\x62\\\\x61\\\\x73\\\\x65\\\\x36\\\\x34\\\\x5F\\\\x64\\\\x65\\\\x63\\\\x6F\\\\x64\\\\x65\\\\x28"
				); 


				$regex = '/(' .implode('|', $patterns) .')/i'; 
				if (preg_match($regex, $line, $matches)) {  
					interact($line, $path, $filename, $line_number, $matches);
				}

				preg_match_all('/(\'[a-z0-9]\')\=>(\'[a-z0-9]\')/i', $line, $foo) . "\n";

				//if($foo[1] == $foo[2]) {
				if(isset($foo[1][0])) {
					if($foo[1][0] != $foo[2][0]) {
						if(count($foo[1]) > 5) {
							//print_r($foo);
							print "Detected ROT13 Suspect\n" . "instances: " . count($foo[1]) . "\n";
							interact($line, $path, $filename, $line_number, null);
						}
					}
				}

			}
			fclose($handle);
		} else {
			// error opening the file.
			print "OPEN FAIL: $path/$filename\n\n";
		} 
	}
}

print "\n";

$time_end = microtime(true);
$execution_time = ($time_end - $time_start)/60;

$msg = 'Total Execution Time:'.$execution_time.' Mins';

$msg .= "\nScan complete ($total Files)\n\n\n";

print "$msg";

$date = date('l jS \of F Y h:i:s A');
if($alarms) {
	#print "The following alarms occured:\n";
	#print_r($alarms);
	$body = "$msg\n\nAlarms detected on $date\n\n" . print_r($alarms, true);
	$to = $email; $subject = "Wordpress Security Scanner ($_filename) Security Report";  
	if (mail($to, $subject, $body)) {   echo("Email successfully sent!\n$body\n");  } else {   echo("Email delivery failed.\n");  }	
} else {
	#$body = "$msg\n\nNo alarms detected: $date";
}

function check_perm($file) {
	global $uid;
	global $gid;
	global $wpuid;
	global $wpgid;
	global $perms;
	if(!$perms) { return; }

	if(substr(sprintf('%o', fileperms($file)), -4) !== '0000') {
		if(is_dir($file)) {
			if(preg_match('/wp\-content\/uploads|cache/', $file)) {
				check_perm_owner($file, $wpuid);
				check_perm_grp($file, $wpgid);
				check_perm_bits($file, '0755'); 
			} else {
				check_perm_owner($file, $uid);
				check_perm_grp($file, $gid);
				check_perm_bits($file, '0755'); 
			}
		} elseif(is_file($file)) {
			if(preg_match('/wp\-content\/uploads|cache/', $file)) {
				check_perm_owner($file, $wpuid);
				check_perm_grp($file, $wpgid);
				check_perm_bits($file, '0664'); 
			} elseif(preg_match('/wp-config.php$/', $file)) { 
				check_perm_owner($file, $uid);
				check_perm_grp($file, $wpgid);
				check_perm_bits($file, '0640'); 
			} else {
				check_perm_owner($file, $uid);
				check_perm_grp($file, $gid);
				check_perm_bits($file, '0644'); 
			}
		} else {
			print "WARNING: UNHANDLED FILE TYPE: $file\n\n\n";
		}
	} else {
		print "DETECTED DISABLED FILE ($file), NOT RESETTING PERMS\n";
	}

}

function check_perm_grp($file, $gid) {
	global $alarms;
	clearstatcache();
	$own = (posix_getgrgid(filegroup($file)));
	if($own['name'] !== $gid) {
		print "Incorrect permission group detected: $file != $gid -- updating\n";
		$alarms["$file"]['perm']['gid']['old'] =  $own['name'];
		$alarms["$file"]['perm']['gid']['new'] =  $gid;
		chgrp($file, $gid);	
	}

}
function check_perm_owner($file, $uid) {
	global $alarms;
	clearstatcache();

	$own = (posix_getpwuid(fileowner($file)));
	if($own['name'] !== $uid) {
		print "Incorrect permission ownership detected: $file != $uid -- updating\n";
		$alarms["$file"]['perm']['uid']['old'] =  $own['name'];
		$alarms["$file"]['perm']['uid']['new'] =  $uid;
		chown($file, $uid); 
	}

}
function check_perm_bits($file, $perm) {

	global $alarms;	
	clearstatcache();

	if(substr(sprintf('%o', fileperms($file)), -4) !== "$perm") {
		//print "\nPERM: " . substr(sprintf('%o', fileperms($file)), -4) . "\n";
		print "Incorrect permission bits detected: $file != $perm -- updating\n";
		$alarms["$file"]['perm']['mod']['old'] =  substr(sprintf('%o', fileperms($file)), -4);
		$alarms["$file"]['perm']['mod']['new'] =  "$perm";
		chmod($file, octdec($perm));
	}
}

function interact($line, $path, $filename, $line_number, $matches) {
	global $interactive;
	global $force;
	global $alarms;
	$_line = substr($line, 0, 50);
	$_matches = print_r($matches, true);


	print <<<ALERT

####################################################################################################################################
#           ALERT               ALERT                      ALERT                  ALERT                                            #
$_matches
$path/$filename
>> $_line
####################################################################################################################################

ALERT;
	$alarms["$path/$filename"]["$filename"][$line_number] = $line;

	if($interactive) {
		echo "Disable file by CHMOD? (y/n)\n";
		$_handle = fopen ("php://stdin","r");
		$input = fgets($_handle);
		if(trim($input) == 'y'){
			chmod("$path/$filename", 0000);
		}
		echo "Thank you, continuing...\n";
		fclose($_handle);
	}
	if($force) {
		chmod("$path/$filename", 0000);
		echo "$path/$filename updated to 0000, continuing...\n";
	}

}


function recursiveDirList($dir, $prefix = '') {
	$dir = rtrim($dir, '/');
	$result = array();

	foreach (glob("$dir/*", GLOB_MARK) as $f) {
		#print "\nDEBUG: $f\n";
		check_perm($f);
		if (substr($f, -1) === '/') {
			$result = array_merge($result, recursiveDirList($f, $prefix . basename($f) . '/'));
		} else {
			$patterns = array("php$", "js$"); 
			$regex = '/(' .implode('|', $patterns) .')/i'; 
			if(preg_match($regex,$f)) {
				if (substr(decoct(fileperms($f)), -3) !== '000') {
					$result[] = $prefix . basename($f);
					#print ".";
				}
			}
		}
	}
	return $result;
}

?>
