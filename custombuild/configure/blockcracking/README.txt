#1.12
BlockCracking
Credit to https://github.com/Exim/exim/wiki/BlockCracking for the idea

this version has been modified for use with a DirectAdmin System.
It requires a minimum of exim.conf 4.4.3 and exim.pl 23

====================================
Installation:
** Note CustomBuild 2.0 will do this for you.

cd /etc
wget exim.blockracking.tar.gz
tar xvzf exim.blockcracking-1.4.tar.gz

If it doesn't exist, copy the default list:
cd exim.blockcracking
cp script.denied_paths.defaults.txt script.denied_paths.txt

and ensure you have
/etc/exim.conf 4.4.3+
/etc/exim.pl 23+

====================================
About:

The idea BlockCracking is that spammers typically send masses of emails
and a large number of those emails are invalid or no longer exists (spammers don't confirm them)
The BlockCracking code will keep count of these invalid deliveries and block the
sender of the given type, if the limit is hit, within a period of time.

Sender Types:
- auth: an account who had authenticated with smtp-auth
- script: any script being delivered to exim via /usr/sbin/sendmail|exim commandline, including php mail();


====================================
Files:

-- variables.conf
If you want to customize the file, create your own file:
-- variables.conf.custom, and set only the values in this file as desired, and they'll override the defaults.
BC_LIM = 100		- how many invalid emails can be send withn BC_PERIOD before block
BC_PERIOD = 1h		- Period of time the invalid emails can be send before block
BC_SHELL = /bin/sh	- leave this alone
BC_UNLIMITED_USERNAMES	- usual acounts that should not have script restrictions. you can add extra users if desired.
BC_DENIED_PATHS		- path to the regex for scripts.denied_paths.txt
BC_SKIP_AUTHENTICATED_USERS - Path of list of smtp-auth email addresses to not be scanned by BC. Does not need to exist. /etc/virtual/bc_skip_authenticated_users
BC_SKIP_SENDING_HOSTS 	- path of list of hosts that are allowed to connect and not be scanned by BC.  Does not need to exist. /etc/virtual/bc_skip_sending_hosts
BC_VERIFY_CALLOUT	- adjust the timeouts as needed. Slow client-to-exim is likely caused by the remote smtp verification: https://www.exim.org/exim-html-current/doc/html/spec_html/ch-access_control_lists.html#CALLaddparcall

-- auth.conf
Contains the BlockCracking code to count and block smtp authenticated accounts.
Blocks to the file:

/var/spool/exim/blocked_authenticated_users


-- script.conf
Contains the BlockCracking code to count and block script paths.
Since exim has no way of knowing which script actually sent the message,
this code will track and rate-lmiit based on the script's working path.
This will allow other possibly valid scripts in other paths to continue working.
Blocks to the file:

/var/spool/exim/blocked_script_paths

-- script.recipients.conf
Contains a "recipients" ACL for the scripts.conf to call, because the non-SMTP ACLs
must figure out the recipients one-by-one (Credit to Lena for helping with this)

-- script.denied_paths.txt
Contains a list of nwildlsearch regex values to be compared against the current working directory for a sending script.
Will the cwd does not contain the filename, just the path it's under.

-- /etc/virtual/bc_skip_authenticated_users
Optional file, does not need to exist.
Contains list of smtp-auth email addresses which will be skipped / not scanned by BlockCracking

-- /etc/virtual/bc_skip_sending_hosts
Optional file, does not need to exist.
Contains hostlist of IPs or rDNS host addresses email addresses which will be skipped / not scanned by BlockCracking.
Wildcards may work on rDNS hostnames, but should be listed after any full IPs or 1.2.3.4/24 ranges
