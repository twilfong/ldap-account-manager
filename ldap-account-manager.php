
<?php

# Author: Tim Wilfong <tim@wilfong.me>



//
// LOAD CONFIGURATION
//
require_once('config.inc');


//
// LOAD FUNCTIONS
//
require_once('functions.inc');


//
// VARIABLE INIT
//
$msg_details = array();
$error = false;



// Ensure the user is connecting with SSL
require_ssl();



// Load authenticated username and password
if(!isset($_SERVER['PHP_AUTH_USER'])){
	force_http_auth();
} else {
	$uid = $_SERVER['PHP_AUTH_USER'];
}
$pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';


//
// Start LDAP Connection
//

// Open LDAP connection, using login credentials to bind.
$ldapconn = ldap_connect($LDAPURL);

// Most LDAP servers only acceppt v3 connections
ldap_set_option($ldapconn,LDAP_OPT_PROTOCOL_VERSION, 3);
$userdn = "uid=$uid,$UIDBASE";

//
// Authenticate to LDAP Server
//

// Check connetion for errors
if (!@ldap_bind($ldapconn, $userdn, $pass)) {
	$ldaperror = ldap_error($ldapconn);
	// If the error string is about credentials then force reauthentication, otherwise display the error
  	if (strstr($ldaperror,"credentials")) {
		// have them retry password
    		force_http_auth("Invalid username and passsword. Try again.");
  	} else {
		// display the ldap error from auth attempt
		$errorMessage="LDAP bind error connecting to '$LDAPURL' as '$userdn': $ldaperror";
		echo $errorMessage;
		error_log($errorMessage);
		exit;
	}
}


//
// Fetch User's LDAP Information
//

// Get the LDAP entry for the authenticated user
$result = ldap_get_entries($ldapconn, ldap_read($ldapconn, $userdn, '(objectclass=*)'));
$user_entry = $result[0];

// fetch user's full name
$fullname = isset($user_entry[$FULL_NAME_ATTR]) ? $user_entry[$FULL_NAME_ATTR][0] : '';

// Form array of all LDAP attributes for this user
foreach ($LDAP_ATTRS as $key => $val) {
	$oldAttributes[$key] = isset($user_entry[$key]) ? $user_entry[$key][0] : '';
}

// Run LDAP Modify Command if Supplied
if (isset($_POST['modify'])) {
	$newAttributes = isset($_POST['attrs']) ? sanitize($_POST['attrs']) : '';
	$newPass = isset($_POST['newPass']) ? sanitize($_POST['newPass']) : '';
	$passConf = isset($_POST['passConf']) ? sanitize($_POST['passConf']) : '';
	modifyUser($ldapconn,$userdn,$oldAttributes,$newAttributes,$newPass,$passConf,$pass);
	$attrs = $_POST['attrs'];
}





include_once('header.inc');

// Display User Message if Present
if(isset($messages)) {
	include_once('showMessage.inc');
}
?>


<!--BEGIN FORM-->
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" name="modUser" method="post">
<table>
<tr><th>Username:</th><td><center><b><?php echo $uid ?></b></center></td></tr>
<tr><th>Full Name:</th><td><center><b><?php echo $fullname ?></b></center></td></tr>
<?php foreach ($attrs as $key => $val) echo "<tr><th>${LDAP_ATTRS[$key]}:</th>
  <td><input name=\"attrs[${key}]\" type=text size=25 value=\"$val\" autocomplete=off/></td></tr>\n";
?>
<tr><td colspan=2 align=center valign=bottom>
  <font size=+1><i>Leave blank to keep existing password.</i></font></td></tr>
<tr><th>New password:</th><td><input name="newPass" size=25 type="password" autocomplete=off/></td></tr>
<tr><th>New password (again):</th><td><input name="passConf" size=25 type="password" autocomplete=off/></td></tr>
<tr><td colspan=2><br></td></tr>
<tr>
  <td align="center"><input name="modify" type="submit" value="Modify Account"/></td>
  <td align="center">
    <input name="Cancel" type="submit" value="Cancel"/> &nbsp;
    <button onclick="javascript:parent.location.href='<?php echo
      'https://x:x@' . $_SERVER["SERVER_NAME"]. $_SERVER["REQUEST_URI"] ?>'; return false;">Logout</button>
  </td>
</tr>
</table>
</form>
<!--END FORM-->



<?php
include_once('footer.inc');
?>
