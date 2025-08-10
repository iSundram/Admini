<?php
//VERSION=0.8
$info = get_token_info();

$user = $info['email'];
$pass = $info['password'];

$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');

$url  = $secure ? 'https' : 'http';
$url .= '://'.$_SERVER['HTTP_HOST'].substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], "direct_login/"));

require_once("RoundcubeAutoLogin.php");
$rc = new RoundcubeAutoLogin($url);
$cookies = $rc->login($user, $pass);
foreach($cookies as $cookie_name => $cookie_value)
{
	setcookie($cookie_name, $cookie_value, 0, '/', '', $secure);
}
$rc->redirect();


function die_error($str)
{
	die($str);
}

function die_rm($str, $file)
{
	unlink($file);
	die_error($str);
}

function get_token_info()
{
	if (!isset($_POST['token']) && !isset($_GET['token']))
		die_error('Missing token');

	if (isset($_POST['token']))
		$token = $_POST['token'];
	else
		$token = $_GET['token'];

	if (!valid_token($token))
		die_error('Invalid token');

	$dir = dirname($_SERVER['SCRIPT_FILENAME']);
	$token_file = $dir.'/tokens/'.$token;
	
	if (!file_exists($token_file))
		die_error("Cannot find token file '$token_file'");

	$file_data = parse_ini_file($token_file, false, INI_SCANNER_RAW);
	if ($file_data === false)
		die_rm("parse_ini_file error", $token_file);

	//ensure you're you.
	if (!filter_var($file_data['ip'], FILTER_VALIDATE_IP))
		die_rm("invalid token IP", $token_file);

	//allow only 10 seconds to ues the token.
	$timeout = 10;
	if (isset($file_data['timeout']) && is_numeric($file_data['timeout']))
		$timeout = (int)$file_data['timeout'];
	if ($file_data['created'] + $timeout < time())
		die_rm("token has expired", $token_file);

	//delete token_file
	unlink($token_file);

	$info = array();
	$info['email'] = base64_decode($file_data['email']);
	$info['password'] = base64_decode($file_data['password']);
	return $info;
}

function valid_token($t)
{
	$len = strlen($t);
	if ($len > 128) return false;
	if ($len < 100) return false;
	return preg_match("/^([a-zA-Z0-9])+$/", $t);
}

?>
