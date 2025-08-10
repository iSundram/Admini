#!/bin/bash

append_and_remove() {
	local src=$1   # /etc/virtual/usage/john.bytes
	local dst=$2   # /usr/local/directadmin/data/users/john/bandwidth.tally

	if [ -s "${src}" ]; then
		cat "${src}" >> "${dst}"
		rm -f "${src}"
	fi
}

for DA_USERDIR in /usr/local/directadmin/data/users/*; do
	if [ ! -d "${DA_USERDIR}" ]; then
		continue
	fi

	echo "0=type=timestamp&time=$(date +%s)"                      >> "${DA_USERDIR}/bandwidth.tally"
	append_and_remove "/etc/virtual/usage/${DA_USERDIR##*/}.bytes"   "${DA_USERDIR}/bandwidth.tally"
	append_and_remove "${DA_USERDIR}/dovecot.bytes"                  "${DA_USERDIR}/bandwidth.tally"
	while IFS= read -r DOMAIN; do
		append_and_remove "/etc/virtual/${DOMAIN}/dovecot.bytes" "${DA_USERDIR}/bandwidth.tally"
	done < "${DA_USERDIR}/domains.list"
done

#cleanup user mail usage
rm -rf /etc/virtual/usage/*

#reset per-email sent counts:
rm -f /etc/virtual/*/usage/*
