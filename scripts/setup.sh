#!/bin/bash

###############################################################################
# setup.sh
# DirectAdmin  setup.sh  file  is  the  first  file  to  download  when doing a
# DirectAdmin Install.  If  you  are unable to run this script with
# ./setup.sh  then  you probably need to set it's permissions.  You can do this
# by typing the following:
#
# chmod 755 setup.sh
#
# after this has been done, you can type ./setup.sh to run the script.
#
###############################################################################

color_reset=$(printf '\033[0m')
color_green=$(printf '\033[32m')
color_red=$(printf '\033[31m')

echogreen () {
	echo "[setup.sh] ${color_green}$*${color_reset}"
}

echored () {
	echo "[setup.sh] ${color_red}$*${color_reset}"
}

is_eol() (
	if [ ! -f /etc/os-release ]; then
		return 1
	fi
	source /etc/os-release

	if [ -z "${VERSION_ID//.*}" ]; then
		# ignore if we cannot detect the version
		return 1
	fi

	declare -A min_supported=(
		[debian]=11
		[ubuntu]=20
		[rhel]=8
		[centos]=8
		[cloudlinux]=8
		[rocky]=8
		[almalinux]=8
	)

	if [ -n "${min_supported[${ID}]}" ] && [ "${VERSION_ID//.*}" -lt "${min_supported[${ID}]}" ]; then
		echored "The system ${ID} ${VERSION_ID} is not supported. Please upgrade to ${ID} ${min_supported[${ID}]} or higher. Exiting."
		return 0
	fi
	return 1
)

if is_eol; then
	exit 1
fi

if [ "$(id -u)" != "0" ]; then
	echored "This script needs to be executed as root. Exiting."
	exit 1
fi

#Global variables
DA_CHANNEL=${DA_CHANNEL:="current"}
DA_PATH=/usr/local/directadmin
DACONF=${DA_PATH}/conf/directadmin.conf
DA_SCRIPTS="${DA_PATH}/scripts"

SETUP_TXT="${DA_PATH}/conf/setup.txt"

SYSTEMDDIR=/etc/systemd/system

export DEBIAN_FRONTEND=noninteractive
export DEBCONF_NOWARNINGS=yes

case "${1}" in
	--help|help|\?|-\?|h)
		echo ""
		echo "Usage: $0 <license_key>"
		echo ""
		echo "or"
		echo ""
		echo "Usage: DA_CHANNEL=\"beta\" $0 <license_key>"
		echo ""
		echo "You may use the following environment variables to pre-define the settings:"
		echo "       DA_CHANNEL : Release channel: alpha, beta, current, stable"
		echo "         DA_EMAIL : Default email address"
		echo "    DA_ADMIN_USER : Default admin account user name"
		echo "DA_ADMIN_PASSWORD : Default admin account password"
		echo "      DA_HOSTNAME : Hostname to use for installation"
		echo "       DA_ETH_DEV : Network device"
		echo "           DA_NS1 : pre-defined ns1"
		echo "           DA_NS2 : pre-defined ns2"
		echo ""
		echo "Just set any of these environment variables to non-empty value (for example, DA_SKIP_CSF=true) to:"
		echo "           DA_WEB_INSTALLER : run DirectAdmin installer in interactive web mode"
		echo "                DA_SKIP_CSF : skip installation of CSF firewall"
		echo "      DA_SKIP_MYSQL_INSTALL : skip installation of MySQL/MariaDB"
		echo "         DA_SKIP_SECURE_PHP : skip disabling insecure PHP functions automatically"
		echo "      DA_SKIP_AUTO_TLS_CERT : skip attempt to issue server hostname certificate using ACME protocol"
		echo "        DA_SKIP_CUSTOMBUILD : skip all the CustomBuild actions"
		echo "  DA_FOREGROUND_CUSTOMBUILD : run CustomBuild installation in foreground DA_SKIP_CUSTOMBUILD is unset"
		echo ""
		echo "To customize any CustomBuild options, we suggest using environment variables: https://docs.directadmin.com/getting-started/installation/overview.html#running-the-installation-with-predefined-options"
		echo ""
		exit 0
		;;
esac

if ! command -v curl > /dev/null; then
	echogreen "Installing dependencies..."
	if [ -e /etc/debian_version ]; then
		apt-get --quiet --yes update
		apt-get --quiet --quiet --yes install curl
	else
		dnf --quiet --assumeyes install curl
	fi
fi

if ! command -v curl > /dev/null; then
	echored "Please make sure 'curl' tool is available on your system and try again."
	exit 1
fi

HOST=""
if [ -n "${DA_HOSTNAME}" ]; then
	HOST="${DA_HOSTNAME}"
elif [ -s "/root/.use_hostname" ]; then
	HOST="$(head -n 1 < /root/.use_hostname)"
fi

ADMIN_USER=""
if [ -n "${DA_ADMIN_USER}" ]; then
	ADMIN_USER="${DA_ADMIN_USER}"
fi

ADMIN_PASS=""
if [ -n "${DA_ADMIN_PASSWORD}" ]; then
	ADMIN_PASS="${DA_ADMIN_PASSWORD}"
fi

EMAIL=""
if [ -n "${DA_EMAIL}" ]; then
	EMAIL="${DA_EMAIL}"
elif [ -s /root/.email.txt ]; then
	EMAIL=$(head -n 1 < /root/.email.txt)
fi

NS1=""
if [ -n "${DA_NS1}" ]; then
	NS1="${DA_NS1}"
elif [ -s /root/.ns1.txt ]; then
	NS1=$(head -n1 < /root/.ns1.txt)
fi

NS2=""
if [ -n "${DA_NS2}" ]; then
	NS2="${DA_NS2}"
elif [ -s /root/.ns2.txt ]; then
	NS2=$(head -n1 < /root/.ns2.txt)
fi

autoLicensekey(){
	local license_key
	license_key=$(curl --silent --location https://www.directadmin.com/clients/my_license_info.php | grep -m1 '^license_key=' | cut -d= -f2,3)
	if [ -z "${license_key}" ]; then
		for ip_address in $(ip -o addr | awk '!/^[0-9]*: ?lo|link\/ether/ {print $4}' | cut -d/ -f1 | grep -v ^fe80); do {
			license_key=$(curl --silent --connect-timeout 20 --interface "${ip_address}" --location https://www.directadmin.com/clients/my_license_info.php | grep -m1 '^license_key=' | cut -d= -f2,3)
			if [ -n "${license_key}" ]; then
				break
			fi
		};
		done
	fi
	echo "${license_key}"
}

ask_and_export() {
	local key=$1
	local default=$2
	local values=$3
	local question=$4

	local answer
	while true; do
		echo -n "${question} (${values// /\/}, default: ${default}): "
		read -r answer

		if [ -z "${answer}" ]; then
			export "${key}=${default}"
			return
		fi
		local v
		for v in ${values}; do
			if [ "${v}" = "${answer}" ]; then
				export "${key}=${answer}"
				return
			fi
		done
		echored "Invalid selection, please enter the selection again."
	done
}


export_cb_options() {
	ask_and_export webserver apache "apache nginx nginx_apache litespeed openlitespeed" "Please select webserver you would like to use"

	ask_and_export mysql_inst mariadb "mysql mariadb no" "Please select MySQL database server you would like to use"
	if [ "${mysql_inst}" = "mysql" ]; then
		ask_and_export mysql 8.0 "5.7 8.0 8.4" "Please select MySQL version you would like to use"
	fi
	if [ "${mysql_inst}" = "mariadb" ]; then
		ask_and_export mariadb 10.6 "10.3 10.4 10.5 10.6 10.11 11.4" "Please select MariaDB version you would like to use"
	fi
	ask_and_export ftpd pureftpd "proftpd pureftpd no" "Please select FTP server you would like to use"

	ask_and_export php1_release 8.3 "5.6 7.0 7.1 7.2 7.3 7.4 8.0 8.1 8.2 8.3 8.4" "Please select default PHP version you would like to use"
	ask_and_export php1_mode php-fpm "php-fpm fastcgi lsphp" "Please select default PHP mode you would like to use"

	local i var_name
	for (( i = 2 ; i < 10; i++ )); do
		local answer="x"
		until [ -z "${answer}" ] || [ "${answer}" = "yes" ] || [ "${answer}" = "no" ]; do
			echo -n "Would you like to have a additional instance of PHP installed? (yes/no, default: no): "
			read -r answer
		done
		if [ -z "${answer}" ] || [ "${answer}" = "no" ]; then
			break
		fi
		var_name="php${i}_release"
		ask_and_export "${var_name}" no "5.6 7.0 7.1 7.2 7.3 7.4 8.0 8.1 8.2 8.3 8.4 no" "Please select additional PHP version you would like to use"
		if [ "${!var_name}" = "no" ]; then
			break
		fi
	done

	ask_and_export php_ioncube no "yes no" "Please select if you would like to use ionCube loader PHP extension"
	ask_and_export php_opcache no "yes no" "Please select if you would like to use opCache PHP extension"

	ask_and_export exim yes "yes no" "Please select if you would like CustomBuild to manage Exim installation"
	ask_and_export dovecot yes "yes no" "Please select if you would like CustomBuild to manage Dovecot installation"
	ask_and_export phpmyadmin yes "yes no" "Please select if you would like CustomBuild to manage phpMyAdmin installation"
	ask_and_export roundcube yes "yes no" "Please select if you would like CustomBuild to manage RoundCube installation"
}

if [ $# -eq 0 ]; then
	LK=""
	until [ "${#LK}" -eq 44 ]; do
		printf "Please enter your License Key: "
		read -r LK
	done
	export_cb_options
elif [ "$1" = "auto" ] || [ $# -ge 4 ]; then
	if [ ! -e /root/.skip_get_license ]; then
		LK=$(autoLicensekey)
		if [ -z "${LK}" ]; then
			echo "Unable to detect your license key, please re-run setup.sh with LK provided as the argument."
			exit 1
		fi
	fi

	case "$2" in
		alpha|beta|current|stable)
			DA_CHANNEL="$2"
	esac

	if [ $# -ge 4 ]; then
		HOST=$3
	fi
else
	LK="$1"
fi

###############################################################################
set -e

echo ""
echogreen "Welcome to DirectAdmin installer!"
echo ""
echogreen "Using these parameters for the installation:"
echo "                License Key: ${LK}"
echo "                 DA_CHANNEL: ${DA_CHANNEL}"
echo "                   DA_EMAIL: ${EMAIL}"
echo "              DA_ADMIN_USER: ${ADMIN_USER}"
echo "          DA_ADMIN_PASSWORD: ${ADMIN_PASS}"
echo "                DA_HOSTNAME: ${HOST}"
echo "                 DA_ETH_DEV: ${DA_ETH_DEV}"
echo "                     DA_NS1: ${NS1}"
echo "                     DA_NS2: ${NS2}"
echo "           DA_WEB_INSTALLER: ${DA_WEB_INSTALLER:-no}"
echo "                DA_SKIP_CSF: ${DA_SKIP_CSF:-no}"
echo "      DA_SKIP_MYSQL_INSTALL: ${DA_SKIP_MYSQL_INSTALL:-no}"
echo "         DA_SKIP_SECURE_PHP: ${DA_SKIP_SECURE_PHP:-no}"
echo "      DA_SKIP_AUTO_TLS_CERT: ${DA_SKIP_AUTO_TLS_CERT:-no}"
echo "        DA_SKIP_CUSTOMBUILD: ${DA_SKIP_CUSTOMBUILD:-no}"
echo "  DA_FOREGROUND_CUSTOMBUILD: ${DA_FOREGROUND_CUSTOMBUILD:-no}"
echo ""

echogreen "Starting installation..."

if [ -e ${DACONF} ]; then
	echo ""
	echo ""
	echo "*** DirectAdmin already exists ***"
	echo "    Press Ctrl-C within the next 10 seconds to cancel the install"
	echo "    Else, wait, and the install will continue, but will destroy existing data"
	echo ""
	echo ""
	sleep 10
fi

if [ -e /usr/local/cpanel ]; then
        echo ""
        echo ""
        echo "*** CPanel exists on this system ***"
        echo "    Press Ctrl-C within the next 10 seconds to cancel the install"
        echo "    Else, wait, and the install will continue overtop (as best it can)"
        echo ""
        echo ""
        sleep 10
fi

echo "* Installing pre-install packages ....";
if [ -e "/etc/debian_version" ]; then
	apt-get --quiet --yes update || true

	apt-get --quiet --yes install \
		patch diffutils perl tar zip unzip curl \
		openssl quota logrotate rsyslog zstd git \
		procps file e2fsprogs xfsprogs hostname \
		iproute2 cron ca-certificates bind9-dnsutils \
		gettext `# required for /usr/bin/msgfmt at DA runtime` \
		media-types `# provides /etc/mime.types, used by DA web service` \
		python3 debianutils python3-apt || \
	apt-get --quiet --yes install \
		patch diffutils perl tar zip unzip curl \
		openssl quota logrotate rsyslog zstd git \
		procps file e2fsprogs xfsprogs hostname \
		iproute2 cron ca-certificates bind9-dnsutils \
		gettext `# required for /usr/bin/msgfmt at DA runtime` \
		mime-support `# provides /etc/mime.types, used by DA web service` \
		python3 debianutils python3-apt
else
	dnf --quiet --assumeyes install \
		patch diffutils perl tar zip unzip curl \
		openssl quota logrotate rsyslog zstd git \
		procps-ng file e2fsprogs xfsprogs hostname \
		iproute cronie ca-certificates bind-utils \
		gettext `# required for /usr/bin/msgfmt at DA runtime` \
		mailcap `# provides /etc/mime.types, used by DA web service` \
		dnf-plugins-core `# required for CB (dnf config-manager)` \
		python3 which
fi
echo "*";
echo "*****************************************************";
echo "";

###############################################################################
###############################################################################


# Helper function to detect static network configs without DNS servers, Hetzner
# installer is known to create such configurations
fix_static_network_without_dns() {
	if ! command -v nmcli >/dev/null; then
		return
	fi

	local conn
	conn=$(nmcli -f NAME -m tabular -t connection show --active || true)
	if [ "$(wc -l <<< "${conn}")" -ne 1 ]; then
		# we do not support multi-iface configurations
		return
	fi
	if [ "$(nmcli -f ipv4.method -m tabular -t connection show "${conn}")" != "manual" ]; then
		# DNS will be received via DHCP
		return
	fi
	if [ -n "$(nmcli -f ipv4.dns -m tabular -t connection show "${conn}")" ]; then
		# Static DNS servers are configured we are good
		return
	fi

	# We know server has one network interface with static network
	# configuration and without any DNS servers configured. It might be
	# working now because /etc/resolv.conf is not yet touched by
	# NetowrkManager but as soon as NM reconfigures the interfaces (for
	# example afer reboot) server will become semi-non functional because
	# there are not DNS servers configured. We pro actively set Google and
	# CloudFlare DNS as a fallback.
	nmcli connection modify "${conn}" +ipv4.dns 8.8.8.8,1.1.1.1 || true
}


if mount | grep -m1 -q '^/var'; then
	echo "*** You have /var partition.  The databases, emails and logs will use this partition. *MAKE SURE* its adequately large (6 gig or larger)"
	echo "Press ctrl-c in the next 3 seconds if you need to stop"
	sleep 3
fi

if [ -e /etc/logrotate.d ]; then
	cp $DA_SCRIPTS/directadmin.rotate /etc/logrotate.d/directadmin
	chmod 644 /etc/logrotate.d/directadmin
fi

mkdir -p /var/log/httpd/domains
chmod 710 /var/log/httpd/domains
chmod 710 /var/log/httpd

ULTMP_HC=/usr/lib/tmpfiles.d/home.conf
if [ -s ${ULTMP_HC} ]; then
	#Q /home 0755 - - -
	if grep -m1 -q '^Q /home 0755 ' ${ULTMP_HC}; then
		perl -pi -e 's#^Q /home 0755 #Q /home 0711 #' ${ULTMP_HC};
	fi
fi

mkdir -p /var/www/html
chmod 755 /var/www/html

cp -f ${DA_SCRIPTS}/directadmin.service ${SYSTEMDDIR}/
cp -f ${DA_SCRIPTS}/directadmin-userd@.service ${SYSTEMDDIR}/
cp -f ${DA_SCRIPTS}/directadmin-userd@.socket ${SYSTEMDDIR}/

cp -f     ${DA_SCRIPTS}/startips.service ${SYSTEMDDIR}/
chmod 644 ${SYSTEMDDIR}/startips.service

systemctl daemon-reload
systemctl enable --quiet directadmin.service
systemctl enable --quiet startips.service

${DA_SCRIPTS}/startips-installer.sh
${DA_SCRIPTS}/fstab.sh
${DA_SCRIPTS}/cron_deny.sh

fix_static_network_without_dns

cp -f ${DA_SCRIPTS}/redirect.php /var/www/html/redirect.php

OLD_ADMIN=$(grep -m 1 '^adminname=' ${SETUP_TXT} 2> /dev/null | cut -d= -f2)
if [ -n "${OLD_ADMIN}" ]; then
	if getent passwd "${OLD_ADMIN}" > /dev/null 2>&1; then
		userdel -r "${OLD_ADMIN}" 2>/dev/null
	fi
	rm -rf "${DA_PATH}/data/users/${OLD_ADMIN}"
fi

#moved here march 7, 2011
mkdir -p /etc/cron.d
cp -f ${DA_SCRIPTS}/directadmin_cron /etc/cron.d/
chmod 600 /etc/cron.d/directadmin_cron
chown root /etc/cron.d/directadmin_cron
		
#CentOS/RHEL bits
if [ ! -s /etc/debian_version ]; then
	systemctl daemon-reload
	systemctl enable crond.service
	systemctl restart crond.service
fi

[ "${DA_SKIP_CSF:-no}"           != "no" ] && export csf=no
[ "${DA_SKIP_MYSQL_INSTALL:-no}" != "no" ] && export mysql_inst=no
[ "${DA_SKIP_SECURE_PHP:-no}"    != "no" ] && export secure_php=no
[ -e /root/.skip_csf ]            && export csf=no
[ -e /root/.skip_mysql_install ]  && export mysql_inst=no
[ -e /root/.skip_mysql_install ]  && export phpmyadmin=no
[ -e /root/.skip_mysql_install ]  && export roundcube=no


if [ "${DA_WEB_INSTALLER:-no}" = "no" ]; then
	install_args=(
		"install"
		"--adminname=${ADMIN_USER}"
		"--adminpass=${ADMIN_PASS}"
		"--update-channel=${DA_CHANNEL}"
		"--email=${EMAIL}"
		"--hostname=${HOST}"
		"--network-dev=${DA_ETH_DEV}"
		"--ns1=${NS1}"
		"--ns2=${NS2}"
		"--license-key=${LK}"
	)
	if [ "${DA_SKIP_AUTO_TLS_CERT:-no}" = "no" ]; then
		install_args+=("--auto-cert")
	fi

	if [ "${DA_SKIP_CUSTOMBUILD:-no}" = "no" ]; then
		install_args+=("--build-all")
	fi

	if [ "${DA_FOREGROUND_CUSTOMBUILD:-no}" != "no" ]; then
		install_args+=("--wait-for-cb")
	fi
else
	install_args=(
		"web-install"
		"--license-key=${LK}"
	)
fi

${DA_PATH}/directadmin "${install_args[@]}" || exit 1

printf \\a
sleep 1
printf \\a
sleep 1
printf \\a

exit 0
