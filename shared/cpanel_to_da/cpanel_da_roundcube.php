<?php
namespace PHPSQLParser;
spl_autoload_register(function ($class) {
	require "phar://" . dirname(__FILE__) . '/PHPSQLParser.phar/' . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
});

if(!isset($argv[1]) || !isset($argv[2]) || !isset($argv[3]) || !isset($argv[4]))
{
	echo "Usage: php cpanel_da_roundcube.php input.sql domain is_maindomain=1|0 directadmin_backup_directory\n";
	echo "Example: php cpanel_da_roundcube.php /home/backup/user/mysql/roundcube.sql domain.tld is_maindomain=1 /home/admin/admin_backups/user/backup\n";
	exit();
}

$sqldump = $argv[1];
$domain = $argv[2];
$is_maindomain = explode('=', $argv[3])[1];
$directadmin_backup_directory = $argv[4];
$xml_path = $directadmin_backup_directory . '/' . $domain.'/email/data';
$xml_file = $xml_path .'/roundcube.xml';

function stripQuotes($text) {
  $unquoted = preg_replace('/^(\'(.*)\'|"(.*)")$/', '$2$3', $text);
  return $unquoted;
} 

if (!file_exists($sqldump)) {
    exit($sqldump . " does not exist!\n");
}

if (!file_exists($xml_path)) {
    mkdir($xml_path, 0700, true);
}

echo 'Generating ' . $xml_file . '...\n';

if ($file = fopen($sqldump, "r")) {
    while(!feof($file)) {
        $line = fgets($file);
        // Parse users
        if (preg_match("/INSERT INTO `users`/i", $line, $match))
        {
        	$parser = new PHPSQLParser();
        	$parsed = $parser->parse($line);
        	foreach ($parsed['VALUES'] as $values) {
        		$users[stripQuotes($values['data'][0]['base_expr'])] = array(
        							'username' => stripQuotes($values['data'][1]['base_expr']), 
        							'mail_host' => stripQuotes($values['data'][2]['base_expr']), 
        							'created' => stripQuotes($values['data'][3]['base_expr']), 
        							'last_login' => stripQuotes($values['data'][4]['base_expr']), 
        							'language' => stripQuotes($values['data'][5]['base_expr']), 
        							'preferences' => stripQuotes($values['data'][6]['base_expr'])
        							);
        	}
        }
        // Parse identities
        if (preg_match("/INSERT INTO `identities`/i", $line, $match))
        {
          	$parser = new PHPSQLParser();
        	$parsed = $parser->parse($line);
        	foreach ($parsed['VALUES'] as $values) {
        		$identities[stripQuotes($values['data'][1]['base_expr'])][stripQuotes($values['data'][0]['base_expr'])] = array(
        											'user_id' => stripQuotes($values['data'][1]['base_expr']), 
        											'changed' => stripQuotes($values['data'][2]['base_expr']), 
        											'del' => stripQuotes($values['data'][3]['base_expr']), 
        											'standard' => stripQuotes($values['data'][4]['base_expr']), 
        											'name' => stripQuotes($values['data'][5]['base_expr']), 
        											'organization' => stripQuotes($values['data'][6]['base_expr']), 
        											'email' => stripQuotes($values['data'][7]['base_expr']),
        											'reply-to' => stripQuotes($values['data'][8]['base_expr']),
        											'bcc' => stripQuotes($values['data'][9]['base_expr']),
        											'signature' => stripQuotes($values['data'][10]['base_expr']),
        											'html_signature' => stripQuotes($values['data'][11]['base_expr'])
        										);
        	}      	
        }
        // Parse contacts
        if (preg_match("/INSERT INTO `contacts`/i", $line, $match))
        {
          	$parser = new PHPSQLParser();
        	$parsed = $parser->parse($line);
        	foreach ($parsed['VALUES'] as $values) {
        		$contacts[stripQuotes($values['data'][9]['base_expr'])][stripQuotes($values['data'][0]['base_expr'])] = array(
        											'changed' => stripQuotes($values['data'][1]['base_expr']),
        											'del' => stripQuotes($values['data'][2]['base_expr']),
        											'name' => stripQuotes($values['data'][3]['base_expr']),
        											'email' => stripQuotes($values['data'][4]['base_expr']),
        											'firstname' => stripQuotes($values['data'][5]['base_expr']),
        											'surname' => stripQuotes($values['data'][6]['base_expr']),
        											'vcard' => stripQuotes($values['data'][7]['base_expr']),
        											'words' => stripQuotes($values['data'][8]['base_expr'])
        										);
        	}
        }
        // Parse contactgroups
        if (preg_match("/INSERT INTO `contactgroups`/i", $line, $match))
        {
          	$parser = new PHPSQLParser();
        	$parsed = $parser->parse($line);
        	foreach ($parsed['VALUES'] as $values) {
				$contactgroups[stripQuotes($values['data'][0]['base_expr'])] = array(
													'name' => stripQuotes($values['data'][4]['base_expr']),
													'changed' => stripQuotes($values['data'][2]['base_expr'])
												);        		
        	}        	

        }
        // Parse contactgroupmembers
        if (preg_match("/INSERT INTO `contactgroupmembers`/i", $line, $match))
        {
          	$parser = new PHPSQLParser();
        	$parsed = $parser->parse($line);
        	foreach ($parsed['VALUES'] as $values) {
				$contactgroupmembers[stripQuotes($values['data'][0]['base_expr'])][stripQuotes($values['data'][1]['base_expr'])] = stripQuotes($values['data'][2]['base_expr']);        		
        	}        	

        }
    }
    fclose($file);
}

//Make contacts groups array
if(!empty($contactgroupmembers) && !empty($contactgroups)){
	foreach ($contactgroupmembers as $group_id => $group_contacts) {
		foreach ($group_contacts as $contact_id => $created) {
			$groups[$contact_id][$group_id] = array('name' => $contactgroups[$group_id]['name'], 'changed' => $contactgroups[$group_id]['changed'], 'created' => $created);
		}
	}
}



$top_depth = 0;
if(!is_dir($xml_path))
{
	if(!mkdir($xml_path, 0755, true))
		die("Unable to create XML path: $xml_path. Unable to backup RoundCube Data.");
}

if(!isset($users) || empty($users)){ exit; }

$fp = @fopen($xml_file, 'w');
if (!$fp)
{
	die("Unable to open $xml_file for writing. Unable to backup RoundCube Data.");
}

xml_open("ROUNDCUBE", $top_depth);

foreach ($users as $user_id => $user) 
{
	$email_domain = explode('@', $user['username']);
	if((isset($email_domain[1]) && strcmp($email_domain[1], $domain) != 0) || (!isset($email_domain[1]) && $is_maindomain == 0)) { continue; }

	$email_depth = $top_depth + 1;
	$email_item_depth = $email_depth + 1;

	xml_open("EMAIL", $email_depth);

	xml_item("USERNAME", $user['username'], $email_item_depth);
	xml_item("LANGUAGE", $user['language'], $email_item_depth);
	xml_item("PREFERENCES", $user['preferences'], $email_item_depth);
	xml_item("CREATED", $user['created'], $email_item_depth);
	xml_item("LAST_LOGIN", $user['last_login'], $email_item_depth);


	xml_open("INDENTITIES", $email_item_depth);
	if(!empty($identities[$user_id]))
	{
		foreach ($identities[$user_id] as $identity_id => $identity) 
		{
			$identity_depth = $email_item_depth + 1;
			$identity_item_depth = $identity_depth + 1;

			xml_open("INDENTITY", $identity_depth);

			xml_item("EMAIL", $identity['email'], $identity_item_depth);
			xml_item("STANDARD", $identity['standard'], $identity_item_depth);
			xml_item("NAME", $identity['name'], $identity_item_depth);
			xml_item("CHANGED", $identity['changed'], $identity_item_depth);
			xml_item("ORGANIZATION", $identity['organization'], $identity_item_depth);
			xml_item("REPLY-TO", $identity['reply-to'], $identity_item_depth);
			xml_item("BCC", $identity['bcc'], $identity_item_depth);
			xml_item("SIGNATURE", $identity['signature'], $identity_item_depth);
			xml_item("HTML_SIGNATURE", $identity['html_signature'], $identity_item_depth);

			xml_close("INDENTITY", $identity_depth);
		}
	}
	xml_close("INDENTITIES", $email_item_depth);


	xml_open("CONTACTS", $email_item_depth);
	if(!empty($contacts[$user_id]))
	{
		foreach ($contacts[$user_id] as $contact_id => $contact) 
		{

			$contact_depth = $email_item_depth + 1;
			$contact_item_depth = $contact_depth + 1;

			xml_open("CONTACT", $contact_depth);

			xml_item('EMAIL', $contact['email'], $contact_item_depth);
			xml_item('NAME', $contact['name'], $contact_item_depth);
			xml_item('CHANGED', $contact['changed'], $contact_item_depth);
			xml_item('FIRSTNAME', $contact['firstname'], $contact_item_depth);
			xml_item('SURNAME', $contact['surname'], $contact_item_depth);
			xml_item2('VCARD', $contact['vcard'], $contact_item_depth);
			xml_item('WORDS', $contact['words'], $contact_item_depth);

			xml_open("GROUPS", $contact_item_depth);
			if(!empty($groups[$contact_id]))
			{
				foreach ($groups[$contact_id] as $group_id => $group)
				{
					xml_open("GROUP", $contact_item_depth+1);

					xml_item("NAME", $group['name'], $contact_item_depth+2);
					xml_item("CHANGED", $group['changed'], $contact_item_depth+2);
					xml_item("CREATED", $group['created'], $contact_item_depth+2);

					xml_close("GROUP", $contact_item_depth+1);
				}
			}
			xml_close("GROUPS", $contact_item_depth);

			xml_close("CONTACT", $contact_depth);
		}
	}
	xml_close("CONTACTS", $email_item_depth);

	xml_close("EMAIL", 1);
}

xml_close("ROUNDCUBE", $top_depth);
//**********************************************************************

function xml_item($name, $value, $tabs)
{
	global $fp;

	for ($i=0; $i<$tabs; $i++)
		fwrite($fp, "\t");

	fwrite($fp, "<".$name.">");
	fwrite($fp, urlencode($value));
	fwrite($fp, "</".$name.">\n");
}

function xml_item2($name, $value, $tabs)
{
	global $fp;
    $value = 
    $value = str_replace('%5Cr%5Cn', '%0A', urlencode($value));
	for ($i=0; $i<$tabs; $i++)
		fwrite($fp, "\t");

	fwrite($fp, "<".$name.">");
	fwrite($fp, $value);
	fwrite($fp, "</".$name.">\n");
}

function xml_open($name, $tabs)
{
	global $fp;

	for ($i=0; $i<$tabs; $i++)
		fwrite($fp, "\t");

	fwrite($fp, "<".$name.">\n");
}
function xml_close($name, $tabs)
{
	global $fp;

	for ($i=0; $i<$tabs; $i++)
		fwrite($fp, "\t");

	fwrite($fp, "</".$name.">\n");
}
?>
