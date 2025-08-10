#!/bin/sh

if echo "$1" | grep -m1 -q "/.htaccess$" && [ -e /usr/local/lsws/bin/lshttpd ]; then
	if [ "$(readlink /usr/local/lsws/bin/lshttpd)" = "/usr/local/lsws/bin/openlitespeed" ] && [ -e /tmp/lshttpd/lshttpd.pid ]; then
		/usr/local/lsws/bin/lswsctrl restart
	fi
elif [ -x /usr/bin/clamdscan ]; then
	/usr/bin/clamdscan --remove --stream --no-summary --quiet "$1"
fi
exit 0
