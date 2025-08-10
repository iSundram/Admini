#!/bin/sh

DENY=/etc/cron.deny

deny() {
	if ! grep -q "^$1$" "${DENY}"; then
		echo "$1" >> "${DENY}"
	fi
}

if [ ! -f "${DENY}" ]; then
	touch "${DENY}"
	chmod 640 "${DENY}"
	if [ -e /etc/debian_version ]; then
		chown root:crontab "${DENY}"
	fi
fi

deny apache
deny webapps

exit 0