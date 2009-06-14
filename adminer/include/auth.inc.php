<?php
$ignore = array("server", "username", "password");
$session_name = session_name();
if (ini_get("session.use_trans_sid") && isset($_POST[$session_name])) {
	$ignore[] = $session_name;
}
if (isset($_POST["server"])) {
	if (isset($_COOKIE[$session_name]) || isset($_POST[$session_name])) {
		session_regenerate_id();
		$_SESSION["usernames"][$_POST["server"]] = $_POST["username"];
		$_SESSION["passwords"][$_POST["server"]] = $_POST["password"];
		$_SESSION["tokens"][$_POST["server"]] = rand(1, 1e6);
		if (count($_POST) == count($ignore)) {
			$location = ((string) $_GET["server"] === $_POST["server"] ? remove_from_uri() : preg_replace('~^[^?]*/([^?]*).*~', '\\1', $_SERVER["REQUEST_URI"]) . (strlen($_POST["server"]) ? '?server=' . urlencode($_POST["server"]) : ''));
			if (!isset($_COOKIE[$session_name])) {
				$location .= (strpos($location, "?") === false ? "?" : "&") . SID;
			}
			header("Location: " . (strlen($location) ? $location : "."));
			exit;
		}
		if ($_POST["token"]) {
			$_POST["token"] = $_SESSION["tokens"][$_POST["server"]];
		}
	}
	$_GET["server"] = $_POST["server"];
} elseif (isset($_POST["logout"])) {
	if ($_POST["token"] != $_SESSION["tokens"][$_GET["server"]]) {
		page_header(lang('Logout'), lang('Invalid CSRF token. Send the form again.'));
		page_footer("db");
		exit;
	} else {
		unset($_SESSION["usernames"][$_GET["server"]]);
		unset($_SESSION["passwords"][$_GET["server"]]);
		unset($_SESSION["databases"][$_GET["server"]]);
		unset($_SESSION["tokens"][$_GET["server"]]);
		unset($_SESSION["history"][$_GET["server"]]);
		redirect(substr($SELF, 0, -1), lang('Logout successful.'));
	}
}

function auth_error($exception = null) {
	global $ignore, $dbh;
	$username = $_SESSION["usernames"][$_GET["server"]];
	unset($_SESSION["usernames"][$_GET["server"]]);
	page_header(lang('Login'), (isset($username) ? htmlspecialchars($exception ? $exception->getMessage() : ($dbh ? $dbh : lang('Invalid credentials.'))) : (isset($_POST["server"]) ? lang('Sessions must be enabled.') : ($_POST ? lang('Session expired, please login again.') : ""))), null);
	?>
	<form action="" method="post">
	<table cellspacing="0">
	<tr><th><?php echo lang('Server'); ?></th><td><input name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>" /></td></tr>
	<tr><th><?php echo lang('Username'); ?></th><td><input name="username" value="<?php echo htmlspecialchars($username); ?>" /></td></tr>
	<tr><th><?php echo lang('Password'); ?></th><td><input type="password" name="password" /></td></tr>
	</table>
	<p>
<?php
	hidden_fields($_POST, $ignore); // expired session
	foreach ($_FILES as $key => $val) {
		echo '<input type="hidden" name="files[' . htmlspecialchars($key) . ']" value="' . ($val["error"] ? $val["error"] : base64_encode(file_get_contents($val["tmp_name"]))) . '" />';
	}
	?>
	<input type="submit" value="<?php echo lang('Login'); ?>" />
	</p>
	</form>
<?php
	page_footer("auth");
}

$username = &$_SESSION["usernames"][$_GET["server"]];
if (!isset($username)) {
	$username = $_GET["username"];
}
$dbh = (isset($username) ? connect() : '');
unset($username);
if (is_string($dbh)) {
	auth_error();
	exit;
}