<?php
declare(strict_types=1);
ini_set("session.use_cookies", "true");

$secureCookie = isset($_SERVER["HTTPS"]);
session_set_cookie_params(0, "/", "", $secureCookie, true);
session_name("SignonSession");
session_start();

$redirect_to = "./";
if (isset($_SESSION["logout_url"])) {
	$redirect_to = $_SESSION["logout_url"];
}

$_SESSION = array();
setcookie(session_name(), "", time()-3600, "/", "", $secureCookie, true);

header("Cache-Control: no-store");
header("Location: $redirect_to");
session_destroy();
?>
