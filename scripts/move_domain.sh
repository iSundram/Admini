#!/bin/bash
# This script is written by Martynas Bendorius and DirectAdmin
# It is used to move domain from one user to another
# Official DirectAdmin webpage: http://www.directadmin.com
# Usage:
# ./move_domain.sh <domain> <olduser> <newuser>

if [ "$(id -u)" != 0 ]; then
	echo "You require Root Access to run this script."
	exit 0
fi

if [ $# != 3 ]; then
	echo "Move Domain between users script"
	echo ""
	echo "Usage:"
	echo "$0 <domain> <olduser> <newuser>"
	echo "you gave #$#: $0 $1 $2 $3"
	exit 0
fi

DOMAIN=$1
OLD_USER=$2
NEW_USER=$3

OLD_HOME=$(getent passwd "${OLD_USER}" | cut -d: -f6)
NEW_HOME=$(getent passwd "${NEW_USER}" | cut -d: -f6)

OLD_MAIL_HOME="${OLD_HOME}"
NEW_MAIL_HOME="${NEW_HOME}"
MAIL_PARTITION=$(da config-get mail_partition)
if [ -n "${MAIL_PARTITION}" ]; then
	OLD_MAIL_HOME="${MAIL_PARTITION}/${OLD_USER}"
	NEW_MAIL_HOME="${MAIL_PARTITION}/${NEW_USER}"
fi

APACHE_PUBLIC_HTML=$(da config-get apache_public_html)

urldecode() {
	local string="${1}"
	local strlen=${#string}
	local decoded=""
	local pos c xx o

	for (( pos=0 ; pos<strlen ; pos++ )); do
		c=${string:$pos:1}
		case "$c" in
			%)
				xx=${string:$((pos+1)):2}
				if [ "${#xx}" -eq 2 ] && [ "${xx//[0-9a-fA-F]/}" = "" ]; then
					printf -v o "%b" "\x${xx}"
					pos=$((pos+2))
				else
					o=${c}
				fi
				;;
			+)
				o=" "
				;;
			*)
				o=${c}
				;;
		esac
		decoded+="${o}"
	done
	echo "${decoded}"
}

update_email_domain_dir() {
	VIRTUAL_DOMAIN="/etc/virtual/${DOMAIN}"
	if [ ! -e "${VIRTUAL_DOMAIN}" ] && [ -e "${VIRTUAL_DOMAIN}_off" ]; then
		VIRTUAL_DOMAIN="${VIRTUAL_DOMAIN}_off"
		echo "domain ${DOMAIN} is suspended using ${VIRTUAL_DOMAIN}"
	fi
	if [ ! -e "${VIRTUAL_DOMAIN}" ]; then
		echo "Cannot find ${VIRTUAL_DOMAIN}, aborting swap of ${VIRTUAL_DOMAIN}"
		return
	fi

	OLD_GID=$(id -g mail)
	OLD_UID=$(id -u "${OLD_USER}")
	NEW_GID=$(id -g mail)
	NEW_UID=$(id -u "${NEW_USER}")

	#First find the uid/gid swap them
	sed -i -e "s#:${OLD_UID}:${OLD_GID}::${OLD_MAIL_HOME}/#:${NEW_UID}:${NEW_GID}::${NEW_MAIL_HOME}/#" "${VIRTUAL_DOMAIN}/passwd"

	#Remove the old system mailbox from virtual passwd file - it belongs to system user and issue rewrite to push the new one
	sed -i -e "\#:${OLD_UID}:${OLD_GID}::${OLD_MAIL_HOME}:#d" "${VIRTUAL_DOMAIN}/passwd"
	da taskq --run "action=rewrite&value=email_passwd&user=${NEW_USER}"

	#/etc/virtual/domain.com/aliases
	sed -i -e "s/^${OLD_USER}:/${NEW_USER}:/" "${VIRTUAL_DOMAIN}/aliases"
	sed -i -e "s/\([ :,]\)${OLD_USER}\(,\|$\)/\1${NEW_USER}\2/g" "${VIRTUAL_DOMAIN}/aliases"
	# Update pipe aliases that contain paths
	sed -i -e "s#${OLD_HOME}/#${NEW_HOME}/#" "${VIRTUAL_DOMAIN}/aliases"

	sed -i -e "s#${OLD_MAIL_HOME}#${NEW_MAIL_HOME}#" "${VIRTUAL_DOMAIN}/filter"

	if [ -e "${VIRTUAL_DOMAIN}/usage.cache" ]; then
		sed -i -e "s/^${OLD_USER}:/${NEW_USER}:/" "${VIRTUAL_DOMAIN}/usage.cache"
	fi

	OLD_EMAIL="${OLD_USER}@${DOMAIN}"
	OLD_EMAIL_ESCAPED=$(sed 's/[.[\*^/&$]/\\&/g' <<< "${OLD_EMAIL}")
	NEW_EMAIL="${NEW_USER}@${DOMAIN}"

	if [ -e "${VIRTUAL_DOMAIN}/majordomo" ]; then
		sed -i -e "s/\(:\|\s\)${OLD_EMAIL_ESCAPED}$/\1${NEW_EMAIL}/" "${VIRTUAL_DOMAIN}/majordomo/list.aliases" 2> /dev/null
		sed -i -e "s/\(:\|\s\)${OLD_EMAIL_ESCAPED}$/\1${NEW_EMAIL}/" "${VIRTUAL_DOMAIN}/majordomo/lists/"* 2> /dev/null
	fi
}

update_email_settings() {
	echo "Updating email settings."

	#domainowners
	sed -i -e "s/^${DOMAIN//./\\.}:.*$/${DOMAIN}: ${NEW_USER}/" /etc/virtual/domainowners

	#snidomains
	if [ -s /etc/virtual/snidomains ]; then
		sed -i -e "s/:${OLD_USER}:${DOMAIN//./\\.}$/:${NEW_USER}:${DOMAIN}/" /etc/virtual/snidomains
	fi

	#repeat for domain pointers too.
	#at this stage, the domain.com.pointers file has already been moved.
	while IFS= read -r p; do
		sed -i -e "s/^${p//./\\.}:.*$/${p}: ${NEW_USER}/" /etc/virtual/domainowners
		# pointers may also have seprate cert files
		sed -i -e "s/:${OLD_USER}:${p//./\\.}$/:${NEW_USER}:${p}/" /etc/virtual/snidomains
	done < <(cut -d= -f1 "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.pointers")

	#/etc/virtual/domain.com
	update_email_domain_dir

	#/home/username/.spamassassin/user_spam/user@domain.com
	#if it doesnt exist, dont bother
	if [ -e "${OLD_HOME}/.spamassassin/user_spam" ]; then
		mkdir -p "${NEW_HOME}/.spamassassin/user_spam"
		mv "${OLD_HOME}/.spamassassin/user_spam/"*"@${DOMAIN}" "${NEW_HOME}/.spamassassin/user_spam/"
		chown -R "${NEW_USER}:mail" "${NEW_HOME}/.spamassassin/user_spam"
		chmod 771                   "${NEW_HOME}/.spamassassin/user_spam"
		chmod 660                   "${NEW_HOME}/.spamassassin/user_spam/"*
	fi

	#/home/username/imap/domain.com
	if [ -e "${OLD_MAIL_HOME}/imap/${DOMAIN}" ]; then
		if [ -e "${NEW_MAIL_HOME}/imap/${DOMAIN}" ]; then
			echo "'${NEW_MAIL_HOME}/imap/${DOMAIN}' already exists.. merging as best we can."
			mv -f "${OLD_MAIL_HOME}/imap/${DOMAIN}/"* "${NEW_MAIL_HOME}/imap/${DOMAIN}"
		else
			if [ ! -e "${NEW_MAIL_HOME}/imap" ]; then
				mkdir -p                 "${NEW_MAIL_HOME}/imap"
				chown "${NEW_USER}:mail" "${NEW_MAIL_HOME}/imap"
				chmod 770                "${NEW_MAIL_HOME}/imap"
			fi
			mv -f "${OLD_MAIL_HOME}/imap/${DOMAIN}" "${NEW_MAIL_HOME}/imap/${DOMAIN}"
		fi

		chown -R "${NEW_USER}:mail" "${NEW_MAIL_HOME}/imap/${DOMAIN}"
		chmod -R 770                "${NEW_MAIL_HOME}/imap/${DOMAIN}"
	fi

	#symlinks for domain pointers
	while IFS= read -r p; do
		rm -f "${OLD_MAIL_HOME}/imap/${p}"
		ln -s "${DOMAIN}"           "${NEW_MAIL_HOME}/imap/${p}"
		chown -h "${NEW_USER}:mail" "${NEW_MAIL_HOME}/imap/${p}"
	done < <(cut -d= -f1 "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.pointers")

	#/var/spool/virtual/domain.com (permissions only)
	if [ -e "/var/spool/virtual/${DOMAIN}" ]; then
		chown -R "${NEW_USER}:mail" "/var/spool/virtual/${DOMAIN}"
	fi

	#/etc/dovecot/conf/sni/domain.com.conf
	if [ -s "/etc/dovecot/conf/sni/${DOMAIN}.conf"  ]; then
		sed -i -e "s#/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/#/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/#g" "/etc/dovecot/conf/sni/${DOMAIN}.conf"
	fi

	#cert inclusions for pointers as well
	while IFS= read -r p; do
		if [ -s "/etc/dovecot/conf/sni/${p}.conf"  ]; then
			sed -i -e "s#/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/#/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/#g" "/etc/dovecot/conf/sni/${p}.conf"
		fi
	done < <(cut -d= -f1 "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.pointers")
}

update_ftp_settings() {
	echo "Updating ftp settings."

	#for the password files, we only change the user@domain.com accounts.
	#the system account isn't touched.
	OLD_GID=$(id -g "${OLD_USER}")
	OLD_UID=$(id -u "${OLD_USER}")
	NEW_GID=$(id -g "${NEW_USER}")
	NEW_UID=$(id -u "${NEW_USER}")

	#proftpd.passwd. First find the uid/gid and homedir matchup and swap them.
	sed -i -e "s#:${OLD_UID}:${OLD_GID}:\(domain\|user\|custom\):${OLD_HOME}/domains/${DOMAIN//./\\.}\(:\|/\)#:${NEW_UID}:${NEW_GID}:\1:${NEW_HOME}/domains/${DOMAIN}\2#" /etc/proftpd.passwd

	#take care of anonymous account in proftpd.passwd (no UID change)
	sed -i -e "s#:anonymous:${OLD_HOME}/domains/${DOMAIN//./\\.}/#:anonymous:${NEW_HOME}/domains/${DOMAIN}/#" /etc/proftpd.passwd
}

update_da_settings() {
	echo "Moving domain data to the ${NEW_USER} user."
	mv -f -T "${OLD_HOME}/domains/${DOMAIN}" "${NEW_HOME}/domains/${DOMAIN}"
	mv -f "/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/domains/${DOMAIN}."* "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/"
	echo "Setting ownership for ${DOMAIN} domain."
	chown -R "${NEW_USER}:${NEW_USER}" "${NEW_HOME}/domains/${DOMAIN}"

	if [ "$APACHE_PUBLIC_HTML" -eq 1 ]; then
		echo "apache_public_html=1 is set, updating public_html and private_html in ${NEW_HOME}/domains/${DOMAIN}"
		chmod 750    "${NEW_HOME}/domains/${DOMAIN}/public_html" "${NEW_HOME}/domains/${DOMAIN}/private_html"
		chgrp apache "${NEW_HOME}/domains/${DOMAIN}/public_html" "${NEW_HOME}/domains/${DOMAIN}/private_html"
	fi

	if [ -s "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.subdomains.docroot.override" ]; then
		while IFS= read -r line; do
			SUBDOMAIN=$(cut -f1 -d= <<< "${line}")
			DOCROOT=$(sed -n -e "s|^${SUBDOMAIN}.*[=&]public_html=\([^&]\+\).*$|\1|p" <<< "${line}")
			DOCROOT_DECODED=$(urldecode "${DOCROOT}")
			if [[ "${DOCROOT_DECODED}" = "/domains/${DOMAIN}/"* ]]; then
				#docroot is inside the already moved domain dir
				continue
			fi
			RELATIVE_MOVE_PATH="${DOCROOT_DECODED}"
			if [ "${DOCROOT_DECODED}" = "/domains/${SUBDOMAIN}.${DOMAIN}/public_html" ]; then
				RELATIVE_MOVE_PATH="/domains/${SUBDOMAIN}.${DOMAIN}"
			fi
			su -s /bin/sh -c "umask 066; mkdir -p '${NEW_HOME//\'/\'\"\'\"\'}${RELATIVE_MOVE_PATH//\'/\'\"\'\"\'}'" "${NEW_USER}"
			echo "Moving ${SUBDOMAIN}.${DOMAIN} custom docroot to the ${NEW_USER} user."
			mv -f -T "${OLD_HOME}${RELATIVE_MOVE_PATH}" "${NEW_HOME}${RELATIVE_MOVE_PATH}"
			echo "Setting ownership for the ${SUBDOMAIN}.${DOMAIN} subdomain."
			chown -R "${NEW_USER}:${NEW_USER}" "${NEW_HOME}${RELATIVE_MOVE_PATH}"

			if [ "$APACHE_PUBLIC_HTML" -eq 1 ]; then
				echo "apache_public_html=1 is set, updating ${NEW_HOME}${RELATIVE_MOVE_PATH} permissions"
				chmod 750    "${NEW_HOME}${RELATIVE_MOVE_PATH}"
				chgrp apache "${NEW_HOME}${RELATIVE_MOVE_PATH}"
			fi
		done < "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.subdomains.docroot.override"
	fi

	if [ -e "${NEW_HOME}/domains/${DOMAIN}/stats" ]; then
		echo "Setting stats directory ownership for ${DOMAIN} domain."
		chown -R root:root "${NEW_HOME}/domains/${DOMAIN}/stats"
	fi

	echo "Removing domain from ${OLD_USER} user."
	sed -i -e "\#^${DOMAIN//./\\.}\$#d" "/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/domains.list"

	echo "Adding domain to ${NEW_USER} user."
	echo "${DOMAIN}" >> "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains.list"
	sed -i -e "s#/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/#/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/#g" "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}."* 2> /dev/null
	sed -i -e "s#${OLD_HOME}/#${NEW_HOME}/#g" "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}."* 2> /dev/null
	if [ -d "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.routes" ]; then
		sed -i -e "s#${OLD_HOME}/#${NEW_HOME}/#g" "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.routes/"* 2> /dev/null
	fi

	#ensure the user.conf doesn't have the old domain. No need for new User, as they'd already have a default.
	if grep -q -F -x "domain=${DOMAIN}" "/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/user.conf"; then
		#figure out a new default domain.. 
		DEFAULT_DOMAIN=$(head -n1 "/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/domains.list")
		#may be filled.. may be empty.
		sed -i -e "s/^domain=${DOMAIN//./\\.}$/domain=${DEFAULT_DOMAIN}/" "/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/user.conf"

		#if the new default domain exists, reset the ~/public_html link.
		if [ -h "${OLD_HOME}/public_html" ] && [ "${DEFAULT_DOMAIN}" != "" ] && [ -d "${OLD_HOME}/domains/${DEFAULT_DOMAIN}/public_html" ]; then
			rm -f                                           "${OLD_HOME}/public_html"
			ln -s "./domains/${DEFAULT_DOMAIN}/public_html" "${OLD_HOME}/public_html"
			chown -h "${OLD_USER}:${OLD_USER}"              "${OLD_HOME}/public_html"
		fi
	fi

	echo "Changing domain owner."
	sed -i -e "s/username=${OLD_USER}/username=${NEW_USER}/g" "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.conf"

	#ip swapping, if needed.
	#empty the domain.ip_list, except 1 IP.
	OLD_IP=$(grep "^ip=" "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.conf" | cut -d= -f2)
	NEW_IP=$(grep "^ip=" "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/user.conf"              | cut -d= -f2)
	if [ "${OLD_IP}" != "${NEW_IP}" ]; then
		echo "The old IP (${OLD_IP}) does not match the new IP (${NEW_IP}). Swapping..."
		#./ipswap.sh <oldip> <newip> [<file>]
		/home/runner/work/Admini/Admini/scripts/ipswap.sh "${OLD_IP}" "${NEW_IP}" "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.conf"
		/home/runner/work/Admini/Admini/scripts/ipswap.sh "${OLD_IP}" "${NEW_IP}" "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.ftp"

		if [ -e /etc/debian_version ]; then
			/home/runner/work/Admini/Admini/scripts/ipswap.sh "${OLD_IP}" "${NEW_IP}" "/etc/bind/${DOMAIN}.db"
		else
			/home/runner/work/Admini/Admini/scripts/ipswap.sh "${OLD_IP}" "${NEW_IP}" "/var/named/${DOMAIN}.db"
		fi

		echo "${NEW_IP}" > "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains/${DOMAIN}.ip_list"

		#update the serial:
		da taskq --run "action=rewrite&value=named&domain=${DOMAIN}"
	fi

	#Update .htaccess files in case there is a protected password directory.
	PROTECTED_LIST="${NEW_HOME}/domains/${DOMAIN}/.htpasswd/.protected.list"
	if [ -s "${PROTECTED_LIST}" ]; then
		echo "Updating protected directories via ${PROTECTED_LIST}"
		while IFS= read -r PROTECTED_LINE; do
			PROTECTED_PATH="${NEW_HOME}/${PROTECTED_LINE}"
			if [ ! -d "${PROTECTED_PATH}" ]; then
				echo "Cannot find a directory at ${PROTECTED_PATH}"
				continue
			fi

			HTA=${PROTECTED_PATH}/.htaccess
			if [ ! -s "${HTA}" ]; then
				echo "${HTA} appears to be empty."
				continue
			fi

			sed -i -e "s#AuthUserFile ${OLD_HOME}/#AuthUserFile ${NEW_HOME}/#" "${HTA}"
		done < "${PROTECTED_LIST}"
	fi

	#complex bug: if multi-ip was used, should go into the zone and surgically remove the old ips from the zone, leaving only the NEW_IP.

	#this is needed to update "show all users" cache.
	da taskq --run "action=cache&value=showallusers"
	#this is needed to rewrite /home/runner/work/Admini/Admini/backend/data/users/USERS/httpd.conf
	da taskq --run "action=rewrite&value=httpd&user=${OLD_USER}"
	da taskq --run "action=rewrite&value=httpd&user=${NEW_USER}"
}

update_awstats() {
	sed -i -e "s#/home/${OLD_USER}/#/home/${NEW_USER}/#g" "/home/${NEW_USER}/domains/${DOMAIN}/awstats/.data/"*".conf"

	sed -i -e "s#/home/${OLD_USER}/#/home/${NEW_USER}/#g" "/home/${NEW_USER}/domains/${DOMAIN}/awstats/awstats.pl"

	#And for subdomains:
	sed -i -e "s#/home/${OLD_USER}/#/home/${NEW_USER}/#g" "/home/${NEW_USER}/domains/${DOMAIN}/awstats/"*"/.data/"*".conf"

	sed -i -e "s#/home/${OLD_USER}/#/home/${NEW_USER}/#g" "/home/${NEW_USER}/domains/${DOMAIN}/awstats/"*"/awstats.pl"
}

doChecks() {
	if [ ! -e "/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/domains.list" ]; then
		echo "File '/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/domains.list' does not exist. Can not continue."
		exit 1
	fi

	if [ "${DOMAIN}" = "" ]; then
		echo "The domain is blank"
		exit 1
	fi

	if [ "${OLD_HOME}" = "" ]; then
		echo "Could not get SOURCE user homedir! Can not continue."
		exit 1
	fi

	if [ "${NEW_HOME}" = "" ]; then
		echo "Could not get DESTINATION user homedir! Can not continue."
		exit 1
	fi

	if [ ! -e "/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains.list" ]; then
		echo "File '/home/runner/work/Admini/Admini/backend/data/users/${NEW_USER}/domains.list' does not exist. Can not continue."
		exit 1
	fi

	if ! grep -q -F -x "${DOMAIN}" "/home/runner/work/Admini/Admini/backend/data/users/${OLD_USER}/domains.list"; then
		echo "Domain ${DOMAIN} is not owned by ${OLD_USER} user."
		exit 1
	fi

	if [ ! -d "${OLD_HOME}/domains/${DOMAIN}" ]; then
		echo "Direcory '${OLD_HOME}/domains/${DOMAIN}' does not exist. Can not continue."
		exit 1
	fi

	if [ -d "${NEW_HOME}/domains/${DOMAIN}" ]; then
		echo "Direcory '${NEW_HOME}/domains/${DOMAIN}' exists. Can not continue."
		exit 1
	fi
}

doChecks
update_da_settings
update_email_settings
update_ftp_settings
update_awstats

echo "Domain has been moved to ${NEW_USER} user."
