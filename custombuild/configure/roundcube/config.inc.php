<?php

$config = [];

$config['imap_host'] = 'localhost:143';
$config['smtp_host'] = 'localhost:587';

$config['login_autocomplete'] = 2;
$config['quota_zero_as_unlimited'] = true;
$config['email_dns_check'] = true;

// List of active plugins (in plugins/ directory)
//
// The 'managesieve' plugin will be configured and enabled here automatically
// by CustomBuild if Dovecot pigeonhole is enabled.
$config['plugins'] = ['password','archive','zipdownload'];

// skin name: folder from skins/
$config['skin'] = 'elastic';
