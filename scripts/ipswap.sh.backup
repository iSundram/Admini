#!/bin/bash

#script to change ips on a DA server.
#usage:
# $0 <oldip> <newip>

LOG=/var/log/directadmin/ipswap.log

MYUID=`/usr/bin/id -u`
if [ "$MYUID" != 0 ]; then
	echo "You require Root Access to run this script";
	exit 0;
fi

if [ $# != 2 ] && [ $# != 3 ]; then
	echo "Usage:";
	echo "$0 <oldip> <newip> [<file>]";
	echo "you gave #$#: $0 $1 $2 $3";
	exit 0;
fi

OLD_IP=$1
NEW_IP=$2

DIRECTADMIN=/usr/local/directadmin/directadmin

HAVE_DOVECOT_PROXY=0
if [ -s ${DIRECTADMIN} ]; then
	if [ "`${DIRECTADMIN} c | grep ^dovecot_proxy= | cut -d= -f2`" -eq 1 ]; then
		HAVE_DOVECOT_PROXY=1
	fi
fi

valid_ip()
{
	#very very.. very basic validation. very basic.	
	VIP=$1
	
	if [ "$VIP" = "" ]; then
		echo 0;
		return;
	fi
	
	#do not allow short-form IPv6 addresses.
	IPV6_SHORT=`echo "$VIP" | grep -c ::`
	if [ "${IPV6_SHORT}" -gt 0 ]; then
		echo 0
	else
		echo 1
	fi
}

die_if_bad_ip()
{
	IP=$1
	V=`valid_ip "$IP"`
	if [ "$V" = "0" ]; then
		echo "IP '$IP' is not valid.";
		exit 1;
	fi
}

die_if_bad_ip "${OLD_IP}"
die_if_bad_ip "${NEW_IP}"

log()
{
	echo -e "$1";
	echo -e "$1" >> $LOG;
}

swapfile()
{
	if [ ! -e "$1" ]; then
		log "Cannot Find $1 to change the IPs. Skipping...";
		return;
	fi
	
	OLD_IP_ESCAPED="${OLD_IP//./\\.}"
	perl -pi -e "s/(^|[\s.=\/:])${OLD_IP_ESCAPED}([\s.>:;])/\${1}${NEW_IP}\${2}/g" "$1"

	log "$1\t: $OLD_IP -> $NEW_IP";
}

if [ $# = 3 ]; then

	swapfile $3;
	exit 0;
fi

IPFILE_OLD=/usr/local/directadmin/data/admin/ips/$OLD_IP
IPFILE_NEW=/usr/local/directadmin/data/admin/ips/$NEW_IP

NEW_IS_ALREADY_SERVER=0
if [ -s ${IPFILE_NEW} ]; then
	echo "${IPFILE_NEW} already exists.";
	
	NEW_IS_ALREADY_SERVER=`grep -c status=server ${IPFILE_NEW}`
	if [ "${NEW_IS_ALREADY_SERVER}" -gt 0 ]; then
		echo "it's also the server IP, so we're not going to overwrite it if we continue.";
		echo -n "Do you want to continue swapping all instances of $OLD_IP with $NEW_IP, knowing we're not going to swap the actual IP file? (y/n) : ";

		read YESNO;
		if [ "$YESNO" != "y" ]; then
			exit 0;
		fi
	fi
fi

if [ ! -e $IPFILE_OLD ]; then
	echo -n "$IPFILE_OLD does not exist.  Do you want to continue anyway? (y/n) : ";
	read YESNO;
	if [ "$YESNO" != "y" ]; then
		exit 0;
	fi
else
	if [ "${NEW_IS_ALREADY_SERVER}" -gt 0 ]; then
		#do not touch the new file, but get rid of the old one.
		rm -f $IPFILE_OLD
	else
		mv -f $IPFILE_OLD $IPFILE_NEW
	fi
fi

swapfile /etc/proftpd.conf
swapfile /etc/hosts
swapfile /usr/local/directadmin/conf/setup.txt
swapfile /usr/local/directadmin/data/admin/ip.list
swapfile /usr/local/directadmin/data/admin/show_all_users.cache
swapfile /etc/virtual/domainips
swapfile /etc/virtual/helo_data

ULDDU=/usr/local/directadmin/data/users

for i in `ls $ULDDU`; do
{

	if [ ! -d $ULDDU/$i ]; then
		continue;
	fi
	
	swapfile $ULDDU/$i/user.conf
	if [ -e $ULDDU/$i/ip.list ]; then
		swapfile $ULDDU/$i/ip.list
	fi

	swapfile $ULDDU/$i/user_ip.list	

	for j in `ls $ULDDU/$i/domains/*.conf; ls $ULDDU/$i/domains/*.ftp; ls $ULDDU/$i/domains/*.ip_list`; do
	{
		swapfile $j
	};
	done;
};
done;

if [ -e /etc/debian_version ]; then
	DB_PATH=/etc/bind
else
	DB_PATH=/var/named
fi

for i in `ls $DB_PATH/*.db`; do
{
	swapfile $i
};
done;

if [ "${HAVE_DOVECOT_PROXY}" = "1" ]; then
	#swap all /etc/virtual/*/passwd files proxy_maybe=y host=1.2.3.4
	echo "action=rewrite&value=email_passwd" >> /usr/local/directadmin/data/task.queue
fi

echo "Updating Linked IPs"
/usr/local/directadmin/directadmin taskq --debug 100 --run "action=ipswap&value=linked_ips&old=$OLD_IP&new=$NEW_IP"

/usr/local/directadmin/scripts/startips
#this is needed to update the serial in the db files.
echo "action=rewrite&value=named" >> /usr/local/directadmin/data/task.queue
echo "action=cache&value=showallusers" >> /usr/local/directadmin/data/task.queue
da build rewrite_confs
log "\n*** Done swapping $OLD_IP to $NEW_IP ***\n";
