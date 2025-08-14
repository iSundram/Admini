#!/usr/bin/perl
=for comment
	ASSUME:
		fred           - system username, home at ~/fred
		bob@domain.com - local email account at ~/fred/imap/domain.com/bob
	LIMITS:
		/etc/virtual/limit                - global username limit
		/etc/virtual/user_limit           - global email account limit
		/etc/virtual/limit_fred           - per-username limit for username fred
		/etc/virtual/domain.com/limit/bob - per-E-Mail Account limit for bob@domain.com
	SEND COUNTER:
		/etc/virtual/usage/fred           - username usage file. 1 byte per send. Used for live count.
		/etc/virtual/domain.com/usage/bob - email usage file for bob@domain.com. 1 byte per send. Also for live count.
	LOGGING:
		/etc/virtual/usage/fred.bytes     - one line urlencoded line per delivery, all info. Parsed later for bytes usage.
		/etc/virtual/usage/fred_ids/A/$message_id/$b64rcpt	- to track if this is a retry. 'A' is the $message_id[0] char.
	LOGIC:
		1) The ACLs only block sends if the count is already over the limit. Nothing logged yet.
		2) The routers are the official checks/blocks after it's already in the queue. Over limits will die().
		3) Only router [condition = "${perl{check_limits}}" == 'yes'] will crete the message_id logging file. Indicates retry for next attempt.
		4) Retry mode is only triggered if the sender has either username or e-mail account limit enabled.
		5) Retries (for failed router sends, a 2nd check_limits call) will not count++, hence message_id file only created after count++;

=cut

sub deny_auth_sender {
	my $auth_user = lc(Exim::expand_string('${local_part:$authenticated_id}') // '');
	my $auth_domain = lc(Exim::expand_string('${domain:$authenticated_id}') // '');
	my $sender_user = lc(Exim::expand_string('$sender_address_local_part') // '');
	# extra lookup makes the sender domain safe to use in FS paths
	my $sender_domain = lc(Exim::expand_string('${lookup {$sender_address_domain} lsearch,ret=key {/etc/virtual/domainowners}}') // '');

	my $domain_dir = "/etc/virtual/" . $sender_domain;
	my $domain_aliases = "/etc/virtual/" . $sender_domain . "/aliases";
	my $domain_passwd = "/etc/virtual/" . $sender_domain . "/passwd";

	# Allow same local part from alias domain
	return "no" if ($auth_user eq $sender_user && -l $domain_dir && $auth_domain eq readlink($domain_dir));

	if (-e $domain_aliases) {
		# Allow sender address if it has a forwarder to an authenticated address
		return "no" if Exim::expand_string('${if inlisti {$authenticated_id} {<, ${lookup {$sender_address_local_part} lsearch {\N'.$domain_aliases.'\N}}} }');

		# Allow sender if catch-all is configured for authenticated address, but:
		# - Block it if normal mailbox with this name exists
		# - Block it if forwarder for this name exists
		my $has_wildcard = Exim::expand_string('${if inlisti {$authenticated_id} {<, ${lookup {*} lsearch {\N'.$domain_aliases.'\N}}} }');
		if ($has_wildcard && -e $domain_passwd) {
			my $no_mailbox = Exim::expand_string('${if bool{${lookup {$sender_address_local_part} lsearch {\N'.$domain_passwd.'\N} {no}{yes}}} }');
			my $no_forwarder = Exim::expand_string('${if bool{${lookup {$sender_address_local_part} lsearch {\N'.$domain_aliases.'\N} {no}{yes}}} }');
			return "no" if ($no_mailbox && $no_forwarder);
		}
	}
	return "yes";
}

sub get_domain_owner
{
	my ($domain) = @_;
	my $username="";
	open(DOMAINOWNERS,"/etc/virtual/domainowners");
	while (<DOMAINOWNERS>)
	{
		$_ =~ s/\n//;
		my ($dmn,$usr) = split(/: /, $_);
		if ($dmn eq $domain)
		{
			close(DOMAINOWNERS);
			return $usr;
		}
	}
	close(DOMAINOWNERS);

	return -1;
}

sub safe_name
{
	my ($name) =  @_;

	if ($name =~ /\//)
	{
		return 0;
	}

	if ($name =~ /\\/)
	{
		return 0;
	}
	
	if ($name =~ /\|/)
	{
		return 0;
	}

	if ($name =~ /\.\./)
	{
		return 0;
	}

	return 1;
}


sub get_username_limit {
	my($username) = @_;
	if (open(my $f, "<", "/etc/virtual/limit_$username")) {
		return int(<$f>);
	}
	if (open (my $f, "<", "/etc/virtual/limit")) {
		return int(<$f>);
	}
	return 0;
}

sub get_email_account_limit {
	my($user, $domain) = @_;
	if ($domain eq "") {
		return -1;
	}
	if (open(my $f, "<", "/etc/virtual/$domain/limit/$user")) {
		return int(<$f>);
	}
	if (open (my $f, "<", "/etc/virtual/user_limit")) {
		return int(<$f>);
	}
	return 0;
}

# hit_limit_user
# checks to see if a username has hit the send limit.
# returns:
#	-1 for "there is no limit"
#	0  for "still under the limit"
#	1  for "at the limit"
#	2  for "over the limit"

sub hit_limit_user
{
	my($username, $recipients_count) = @_;

	my $count = 0;

	if (!safe_name($username)) { return 2; }
	
	my $email_limit = get_username_limit($username);

	if ($email_limit > 0) {
		#check this users limit
		$count = (stat("/etc/virtual/usage/$username"))[7] + $recipients_count;

		#this is their last email.
		if ($count == $email_limit) { return 1;	}
		if ($count > $email_limit) { return 2; }

		return 0;
	}

	return -1;
}

# hit_limit_email
# same idea as hit_limit_user, except we check the limits (if any) for per-email accounts.

sub hit_limit_email
{
	my($user,$domain,$recipients_count) = @_;
	#recipients_count will be 1 or higher.

	if (!safe_name($user) || !safe_name($domain))
	{
		return 2;
	}
	
	my $user_email_limit = get_email_account_limit($user, $domain);

	if ($user_email_limit > 0)
	{
		my $count = 0;
		$count = (stat("/etc/virtual/$domain/usage/$user"))[7] + $recipients_count;
		if ($count == $user_email_limit)
		{
			return 1;
		}
		if ($count > $user_email_limit)
		{
			return 2;
		}
		return 0;
	}

	return -1;
}

#smtpauth
#called by exim to verify if an smtp user is allowed to
#send email through the server
#possible success:
# user is in /etc/virtual/domain.com/passwd and password matches
# user is in /etc/passwd and password matches in /etc/shadow

sub smtpauth
{
	my ( $username, $password ) = @_;
	$domain		= "";
	$unixuser	= 1;

	if (!safe_name($username))
	{
		Exim::log_write("SMTPAuth: Invalid username: $username");
		return "no";
	}
	
	if ($username =~ /\@/)
	{
		$unixuser = 0;
		($username,$domain) = split(/\@/, $username);
		if ($domain eq "") { return "no"; }
	}

	if ($unixuser == 1)
	{
			#the username passed doesn't have a domain, so its a system account
			$homepath = (getpwnam($username))[7];
			if ($homepath eq "") { return 0; }
			if (open(PASSFILE, "< $homepath/.shadow")) {
					$crypted_pass = <PASSFILE>;
					close PASSFILE;

					if ($crypted_pass eq crypt($password, $crypted_pass))
					{
							return "yes";
					}
			}

			#jailed shell auth
			if (open(USERVARIABLES,"/etc/exim.jail/$username.conf"))
			{
					while ($line = <USERVARIABLES>)
					{
							if ($line =~ m/^password /)
							{
									my $jail_password = (split / /, $line)[1];
									$jail_password =~ s/\n//;
									if ($jail_password eq $password) {
											close(USERVARIABLES);
											return "yes";
									}
							}
					}
					close(USERVARIABLES);
			}

	}
	else
	{
		#the username contain a domain, which is now in $domain.
		#this is a pure virtual pop account.

		open(PASSFILE, "< /etc/virtual/$domain/passwd") || return "no";
		while (<PASSFILE>)
		{
			($test_user,$test_pass) = split(/:/,$_);
			$test_pass =~ s/\n//g; #snip out the newline at the end
			if ($test_user eq $username)
			{
				close PASSFILE;
				if ($test_pass eq crypt($password, $test_pass))
				{
					return "yes";
				}

				#right user in passwd
				#wrong password in passwd

				#unless, they have a passwd_alt file, lets try that
				open(PASSFILE_ALT, "< /etc/virtual/$domain/passwd_alt") || return "no";
				while (<PASSFILE_ALT>)
				{
					($test_user,$test_pass) = split(/:/,$_);
					$test_pass =~ s/\n//g; #snip out the newline at the end
					if ($test_user eq $username)
					{
						close PASSFILE_ALT;
						if ($test_pass eq crypt($password, $test_pass))
						{
							return "yes";
						}
						return "no";	#wrong password vs passwd_alt
					}
				}
				close PASSFILE_ALT;
				return "no";	#not found in passwd_alt
			}
		}
		close PASSFILE;
		return "no";	#not found in passwd
	}

	return "no";
}

sub auth_hit_limit_acl
{
	my $authenticated_id	= Exim::expand_string('$authenticated_id');
	#number of already accepted RCPT accounts. 0 for first RCPT.
	#bump+ by one to include this message. Increases by 1 for each accepted RCPT call.
	#don't allow #count+$recipients_count > $limit.
	my $recipients_count	= Exim::expand_string('$recipients_count') + 1;

	$username	= $authenticated_id;
	$domain		= "";
	$unixuser	= 1;

	if (!safe_name($username))
	{
		Exim::log_write("auth_hit_limit_acl: Invalid username: $username");
		return "yes";
	}

	if ($username =~ /\@/)
	{
		$unixuser = 0;
		($username,$domain) = split(/\@/, $username);
		if ($domain eq "") { return "no"; }
	}
	
	if ($unixuser == 1)
	{
		my $limit_check = hit_limit_user($username, $recipients_count);
		if ($limit_check > 1)
		{
			return "yes";
		}
	}
	else
	{
		my $domain_owner = get_domain_owner($domain);
		if ($domain_owner != -1)
		{
			my $limit_check = hit_limit_user($domain_owner, $recipients_count);
			if ($limit_check > 1)
			{
				return "yes";
			}

			$limit_check = hit_limit_email($username, $domain, $recipients_count);
			if ($limit_check > 1)
			{
				return "yes";
			}
		}
	}

	return "no";
}

sub find_uid_apache
{
	my ($work_path) = @_;
	my @pw;
	
	# $pwd will probably look like '/home/username/domains/domain.com/public_html'
	# it may or may not use /home though. others are /usr/home, but it's ultimately
	# specified in the /etc/passwd file.  We *could* parse through it, but for efficiency
	# reasons, we'll only check /home and /usr/home ..   if they change it, they can
	# manually adjust if needed.

	@dirs = split(/\//, $work_path);
	foreach $dir (@dirs)
	{
		# check the dir name for a valid user
		# get the home dir for that user
		# compare it with the first part of the work_path

		if ( (@pw = getpwnam($dir))  )
		{
			if ($work_path =~/^$pw[7]/)
			{
				return $pw[2];
			}
		}
	}
	return -1;
}

sub find_uid_auth_id
{
	# this will be passwed either
	# 'username' or 'user@domain.com'

	my ($auth_id) = @_;
	my $unixuser = 1;
	my $domain = "";
	my $user = "";
	my $username = $auth_id;
	my @pw;

	if (!safe_name($username))
	{
		Exim::log_write("find_uid_auth_id: Invalid username: $username");
		return "-1";
	}
	
	if ($auth_id =~ /\@/)
	{
		$unixuser = 0;
		($user,$domain) = split(/\@/, $auth_id);
		if ($domain eq "") { return "-1"; }
        }

	if (!$unixuser)
	{
		# we need to take $domain and get the user from /etc/virtual/domainowners
		# once we find it, set $username
		my $u = get_domain_owner($domain);;
		if ($u != -1)
		{
			$username = $u;
		}
	}

	#log_str("username found from $auth_id: $username:\n");

	if ( (@pw = getpwnam($username))  )
	{
		return $pw[2];
	}

	return -1;
}

sub find_uid_sender
{
	my $sender_address = Exim::expand_string('$sender_address');

	my ($user,$domain) = split(/\@/, $sender_address);

	my $primary_hostname = Exim::expand_string('$primary_hostname');
	if ( $domain eq $primary_hostname )
	{
		@pw = getpwnam($user);
		return $pw[2];
	}

	my $username = get_domain_owner($domain);

	if ( (@pw = getpwnam($username))  )
	{
		return $pw[2];
	}

	return -1;
}

sub get_username
{
	my ($uid) = @_;
	if ($uid == -1) { return "unknown"; }
	my $username = getpwuid($uid);
	if (!defined($username)) { return "unknown"; }
	return $username;
}

sub find_script_path
{
	my $work_path = $ENV{'PWD'};
	return $work_path;
}

sub get_env
{
	my ($envvar) = @_;
	if ($envvar eq "" ) { return ""; };
	return $ENV{$envvar};
}

sub find_uid
{
        my $uid = Exim::expand_string('$originator_uid');
	my $username = getpwuid($uid);
        my $auth_id = Exim::expand_string('$authenticated_id');
        my $work_path = $ENV{'PWD'};

	if ($username eq "apache" || $username eq "nobody" || $username eq "webapps")
	{
		$apache_uid = find_uid_apache($work_path);
		if ($apache_uid != -1) { return $apache_uid; }
	}

	if ($username ne "" && -d "/home/runner/work/Admini/Admini/backend/data/users/$username" )
	{
		return $uid;
	}
	
	$auth_uid = find_uid_auth_id($auth_id);
	if ($auth_uid != -1) { return $auth_uid; }

	# we don't want to rely on this, but it's all thats left.
	return find_uid_sender;
}

sub uid_exempt
{
        my ($uid) = @_;
        if ($uid == 0) { return 1; }

        my $name = getpwuid($uid);
        if ($name eq "root") { return 1; }
        if ($name eq "diradmin") { return 1; }

        return 0;
}

sub get_usage_name_mid_path {
	my ( $username, $mid, $local_part, $domain ) = @_;
	if ($mid eq "") { return ""; }
	my $mid_char = substr($mid, 0, 1);
	my $dest_str = get_b64_string("$local_part-$domain");
	return "/etc/virtual/usage/${username}_ids/$mid_char/$mid/$dest_str";
}

sub has_mid_file {
	my ( $username, $mid, $local_part, $domain ) = @_;
	my $mid_file = get_usage_name_mid_path($username, $mid, $local_part, $domain);
	if ($mid_file eq "") { return 0; }

	if (-f $mid_file) { return 1; }

	return 0;
}

sub touch_usage_name_mid {
	my ( $username, $mid, $local_part, $domain ) = @_;
	my $timestamp		= time();

	if ($mid eq "") { return; }

	if (! -d "/etc/virtual/usage/${username}_ids") {
		mkdir("/etc/virtual/usage/${username}_ids", 0770);
	}
	my $mid_char = substr($mid, 0, 1);
	if (! -d "/etc/virtual/usage/${username}_ids/$mid_char") {
		mkdir("/etc/virtual/usage/${username}_ids/$mid_char", 0770);
	}
	if (! -d "/etc/virtual/usage/${username}_ids/$mid_char/$mid") {
		mkdir("/etc/virtual/usage/${username}_ids/$mid_char/$mid", 0770);
	}

	my $id_file = get_usage_name_mid_path($username, $mid, $local_part, $domain);

	if (-f $id_file) { return; }

	open(IDF, ">>$id_file");
	print IDF "log_time=$timestamp\n";
	close(IDF);
	chmod (0660, $id_file);
}

#check_limits
#used to enforce limits for the number of emails sent
#by a user.  It also logs the bandwidth of the data
#for received mail.

sub check_limits
{
	#find the curent user
	$uid = find_uid();

	#log_str("Found uid: $uid\n");

	if (uid_exempt($uid)) { return "yes"; }

	#check this users limit
	my $username = get_username($uid); #fred or unknown

	my $sender_address 	= Exim::expand_string('$sender_address');
	my $authenticated_id	= Exim::expand_string('$authenticated_id');
	my $sender_host_address	= Exim::expand_string('$sender_host_address');
	my $mid 		= Exim::expand_string('$message_id');
	my $message_size	= Exim::expand_string('$message_size');
	my $local_part		= Exim::expand_string('$local_part');
	my $domain		= Exim::expand_string('$domain');
	my $timestamp		= time();

	if ($mid eq "") {
		return "yes";
	}

	#-f /etc/virtual/usage/fred_ids/1/$mid/$b64rcpt=
	my $is_retry = has_mid_file($username, $mid, $local_part, $domain);

	my $count = 0;
	#-1 nothing, 0 set no limit, >0 numerical limit.
	my $username_limit = get_username_limit($username);
	my $email_account_limit = -1;

	if ($username_limit > 0)
	{
		#check this users limit
		$count = (stat("/etc/virtual/usage/$username"))[7] + 1;
		if ($count > $username_limit) {
			die("You ($username) have reached your daily email limit of $username_limit emails\n");
		}

		#if its already in the queue and being retried, it's already been logged and cleared to send via router
		# but it could still fail delivery there, don't hold that against them.
		# For script sends, there's a verify recipient that calls this with a blank sender_address. Don't do anyting for that case.
		if (!$is_retry && $sender_address ne "") {
			#this is their last email.
			if ($count == $username_limit) {
				#taddle on the dataskq
				#note that the sender_address here is only the person who sent the last email
				#it doesnt meant that they have sent all the spam
				#this action=limit will trigger a check on usage/user.bytes, and DA will try and figure it out.
				open(TQ, ">>/etc/virtual/mail_task.queue");
				print TQ "action=limit&username=$username&count=$count&limit=$username_limit&email=$sender_address&authenticated_id=$authenticated_id&sender_host_address=$sender_host_address&log_time=$timestamp\n";
				close(TQ);
				chmod (0660, "/etc/virtual/mail_task.queue");
			}

			open(USAGE, ">>/etc/virtual/usage/$username");
			print USAGE "1";
			close(USAGE);
			chmod (0660, "/etc/virtual/usage/$username");
		}
	}

	#only authenticated bob@domain.com needs to check the per-Email account send limit.
	if ( ($authenticated_id ne "")) {
		my $auth_user="";
		my $auth_domain="";
		($auth_user, $auth_domain) = (split(/@/, $authenticated_id));

		if (!safe_name($authenticated_id)) {
			Exim::log_write("check_limits: Invalid username: $authenticated_id");
			return "no";
		}

		#returns -1 for domain='' or no limits set.
		$email_account_limit = get_email_account_limit($auth_user, $auth_domain);

		if ($email_account_limit > 0) {
			$count = (stat("/etc/virtual/$auth_domain/usage/$auth_user"))[7] + 1;

			if ($count > $email_account_limit) {
				die("Your E-Mail ($authenticated_id) has reached it's daily email limit of $email_account_limit emails\n");
			}

			if (!$is_retry) {
				if ($count == $email_account_limit) {
					open(TQ, ">>/etc/virtual/mail_task.queue");
					print TQ "action=userlimit&username=$username&count=$count&limit=$email_account_limit&email=$sender_address&authenticated_id=$authenticated_id&sender_host_address=$sender_host_address&log_time=$timestamp\n";
					close(TQ);
					chmod (0660, "/etc/virtual/mail_task.queue");
				}

				if (! -d "/etc/virtual/$auth_domain/usage") {
					mkdir("/etc/virtual/$auth_domain/usage", 0770);
				}

				if (-d "/etc/virtual/$auth_domain/usage") {
					open(USAGE, ">>/etc/virtual/$auth_domain/usage/$auth_user");
					print USAGE "1";
					close(USAGE);
					chmod (0660, "/etc/virtual/$auth_domain/usage/$auth_user");
				}
			}
		}
	}

	#script based sends, this case happens for 'verify recipient'. Don't do anything for this.
	#it will be called again with the proper sender set.
	if ($sender_address eq "") {
		return "yes";
	}

	#touch the mid-rcpt file if username or email account limits, else is_retry will never be true.
	if (!$is_retry &&
		($username_limit > 0 || $email_account_limit > 0)) {
		touch_usage_name_mid($username, $mid, $local_part, $domain);
	}

	log_bandwidth($uid,"type=email&email=$sender_address&method=outgoing&id=$mid&authenticated_id=$authenticated_id&sender_host_address=$sender_host_address&log_time=$timestamp&message_size=$message_size&local_part=$local_part&domain=$domain");

	return "yes";
}

sub block_cracking_notify
{
	my($bc_type) = @_;

	my $sender_host_address = Exim::expand_string('$sender_host_address');
	my $authenticated_id    = Exim::expand_string('$authenticated_id');
	my $script_path		= "";
	my $mid                 = Exim::expand_string('$message_id');
	my $timestamp           = time();

	if ($bc_type eq "script" || $bc_type eq "denied_path") { $script_path = Exim::expand_string('$acl_m_script_path'); }

	open(TQ, ">>/etc/virtual/mail_task.queue");
	print TQ "action=block_cracking&type=$bc_type&authenticated_id=$authenticated_id&script_path=$script_path&sender_host_address=$sender_host_address&log_time=$timestamp\n";
	close(TQ);
	chmod (0660, "/etc/virtual/mail_task.queue");
}

sub log_email
{
	my($lp,$dmn,$sender) = @_;

	#log_str("logging $lp\@$dmn\n");
	my $user = get_domain_owner($dmn);
	if ($user == -1) { return "no"; }

	my $mid = Exim::expand_string('$message_id');
	my $timestamp           = time();

	if ($mid eq "")
	{
		return "yes";
	}

	if ( (@pw = getpwnam($user))  )
	{
		log_bandwidth($pw[2],"type=email&email=$lp\@$dmn&method=incoming&log_time=$timestamp&id=$mid&sender=$sender");
	}

	return "yes";
}

sub save_virtual_user
{
	my $dmn = Exim::expand_string('$domain');
	my $lp  = Exim::expand_string('$local_part');
	my $sender = Exim::expand_string('$sender_address');
	my $usr = "";
	my $pss = "";
	my $entry = "";

	if (!safe_name($dmn) || !safe_name($lp))
	{
		Exim::log_write("save_virtual_user: Invalid username: $lp or domain: $dmn");
		return "no";
	}
	
	open (PASSWD, "/etc/virtual/$dmn/passwd") || return "no";

	while ($entry = <PASSWD>) {
		($usr,$pss) = split(/:/,$entry);
		if ($usr eq $lp) {
			close(PASSWD);
			log_email($lp, $dmn, $sender);
			return "yes";
		}
	}
	close (PASSWD);

	return "no";
}

sub log_bandwidth
{
	my ($uid,$data) = @_;
	my $username = getpwuid($uid);

	if (uid_exempt($uid)) { return; }

	if ($username eq "") { $username = "unknown"; }

	my $bytes = Exim::expand_string('$message_size');

	if ($bytes == -1) { return; }

	my $work_path = $ENV{'PWD'};

	open (BYTES, ">>/etc/virtual/usage/$username.bytes");
	print BYTES "$bytes=$data&path=$work_path\n";
	close(BYTES);
	chmod (0660, "/etc/virtual/usage/$username.bytes");
}

sub is_integer
{
	return $_[0] =~ /^\d+$/
}
sub is_float
{
	return $_[0] =~ /^\d+\.?\d*$/
}

sub get_spam_high_score_drop
{
	my $domain = Exim::expand_string('$acl_m_spam_domain');

	#/etc/virtual/domain.com/filter.conf
	#high_score=7
	#high_score_block=yes

	my $high_score = 1000;
	my $block = "no";

	if (!safe_name($domain))
	{
		Exim::log_write("get_spam_high_score_drop: Invalid domain: $domain");
		return 1000;
	}
	
	if (open (FILTER_CONF, "/etc/virtual/$domain/filter.conf"))
	{
		while ($line = <FILTER_CONF>)
		{
			if ($line =~ m/^high_score=/)
			{
				$line =~ s/\n//;
				my $hs = 1000;
				($dontcare,$hs) = split(/=/, $line);
				if (is_integer($hs) || is_float($hs))
				{
					$high_score = $hs * 10;
				}
			}
			if ($line =~ m/^high_score_block=/)
			{
				$line =~ s/\n//;
				my $b = "no";
				($dontcare,$b) = split(/=/, $line);
				if ($b eq "no")
				{
					#simplest way to not block without having exim.conf changes, is to score unreasonably high
					$high_score = 500000;
					break;
				}
				if ($b eq "yes")
				{
					$block = "yes";
				}
				
			}
		}
		close(FILTER_CONF);
	}

	return $high_score;
}

sub get_spam_subject
{
	my $username = Exim::expand_string('$acl_m_spam_user');
	my $subject = "*****SPAM***** ";

	if ($username eq "nobody") { return $subject; }

	$subject = "";

	#find rewrite_header subject *****SPAM*****
	#if there is no rewrite_header, then don't touch the subject.

	$homepath = (getpwnam($username))[7];
	if ($homepath eq "") {
		return $subject;
	}
	if (open (USER_PREFS, "$homepath/.spamassassin/user_prefs"))
	{
		$subject = "";    #no rewrite_header subject, they dont want it touched.
		while ($line = <USER_PREFS>)
		{
			if ($line =~ m/^rewrite_header subject /)
			{
				$line =~ s/^rewrite_header subject (.*)\n/$1 /;
				$subject = $line;
				break;
			}
		}
		close(USER_PREFS);
	}

	return $subject;
}

sub get_b64_string
{
	my ($str) = @_;
	
	eval
	{
		require MIME::Base64;
		MIME::Base64->import();
	};

	unless($@)
	{
		my $enc = MIME::Base64::encode_base64($str);
		# an evil newline is added. get rid of it.
		$enc =~ s/\n//;
		return $enc;
	}

	return $str;
}

sub append_record
{
	my $file = shift;
	my ($record) = @_; # Do not allow record splitting.
	$record =~ s/[\n:]//g;
	open(my $fh, '>>', $file) or return "false";
	print $fh "$record:" . time() . "\n";
	close $fh;
	return "true";
}


sub log_str
{
	my ($str) = @_;

	open (LOG, ">> /tmp/test.txt");

	print LOG $str;

	close(LOG);
}

if ( -e "/etc/exim.custom.pl" ) {
	do '/etc/exim.custom.pl';
}
