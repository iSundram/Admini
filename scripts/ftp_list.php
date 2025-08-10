#!/bin/bash

CURL=/usr/local/bin/curl
if [ ! -e ${CURL} ]; then
	CURL=/usr/bin/curl
fi
TMPDIR=/home/tmp
PORT=${ftp_port}
FTPS=0
if [ "${ftp_secure}" = "ftps" ]; then
	FTPS=1
fi
SHOW_BYTES=0
if [ "${ftp_show_bytes}" = "yes" ]; then
	SHOW_BYTES=1
fi

int_version() {
        local major minor patch
        major=$(cut -d . -f 1 <<< "$1")
        minor=$(cut -d . -f 2 <<< "$1")
        patch=$(cut -d . -f 3 <<< "$1")
        printf "%03d%03d%03d" "${major}" "${minor}" "${patch}"
}

SSL_ARGS=""
if [ "$FTPS" != "0" ]; then
	CURL_TLS_HELP=$(${CURL} --help tls)
	CURL_VERSION=$(${CURL} --version | head -n 1 | cut -d ' ' -f 2)

	if grep -q 'ftp-ssl-reqd' <<< "${CURL_TLS_HELP}"; then
		SSL_ARGS="${SSL_ARGS} --ftp-ssl-reqd"
	elif grep -q 'ssl-reqd' <<< "${CURL_TLS_HELP}"; then
		SSL_ARGS="${SSL_ARGS} --ssl-reqd"
	fi
	# curl 7.77.0 fixed gnutls ignoring --tls-max if --tlsv1.x was not specified.
	# https://curl.se/bug/?i=6998
	#
	# curl 7.61.0 fixes for openssl to treat --tlsv1.x as minimum required version instead of exact version
	# https://curl.se/bug/?i=2691
	#
	# curl 7.54.0 introduced --max-tls option and changed --tlsv1.x behaviur to be min version
	# https://curl.se/bug/?i=1166
	if [ "$(int_version "${CURL_VERSION}")" -ge "$(int_version '7.54.0')" ]; then
		SSL_ARGS="${SSL_ARGS} --tlsv1.1"
	fi

	# curl 7.78.0 fixed FTP upload TLS 1.3 bug, we add `--tls-max 1.2` for older versions.
	# https://curl.se/bug/?i=7095
	if [ "$(int_version "${CURL_VERSION}")" -lt "$(int_version '7.78.0')" ] && grep -q 'tls-max' <<< "${CURL_TLS_HELP}"; then
		SSL_ARGS="${SSL_ARGS} --tls-max 1.2"
		# curls older than 7.61.0 needs --tlsv.x parameter for --tls-max to work correctly
		# https://curl.se/bug/?i=2571 - openssl: acknowledge --tls-max for default version too
	fi

fi

if [ "$PORT" = "" ]; then
	PORT=21
fi

RANDNUM=`/usr/local/bin/php -r 'echo rand(0,10000);'`
#we need some level of uniqueness, this is an unlikely fallback.
if [ "$RANDNUM" = "" ]; then
        RANDNUM=$ftp_ip;
fi

CFG=$TMPDIR/$RANDNUM.cfg
rm -f $CFG
touch $CFG
chmod 600 $CFG

DUMP=$TMPDIR/$RANDNUM.dump
rm -f $DUMP
touch $DUMP
chmod 600 $DUMP

#######################################################
# FTP
list_files()
{
	if [ ! -e ${CURL} ]; then
		echo "";
		echo "*** Unable to get list ***";
		echo "Please install curl";
		echo "";
		exit 10;
	fi

	#double leading slash required, because the first one doesn't count.
	#2nd leading slash makes the path absolute, in case the login is not chrooted.
	#without double forward slashes, the path is relative to the login location, which might not be correct.
	ftp_path="/${ftp_path}"

	/bin/echo "user =  \"$ftp_username:$ftp_password_esc_double_quote\"" >> $CFG

	${CURL} --config ${CFG} ${SSL_ARGS} -k --silent --show-error ftp://$ftp_ip:${PORT}$ftp_path/ > ${DUMP} 2>&1
	RET=$?

	if [ "$RET" -ne 0 ]; then
		echo "${CURL} returned error code $RET";
		cat $DUMP
	else
		COLS=`awk '{print NF; exit}' $DUMP`
		if [ "${SHOW_BYTES}" = "1" ] && [ "${COLS}" = "9" ]; then
			cat $DUMP | grep -v -e '^d' | awk "{ print \$${COLS} \"=\" \$5; }"
		else
			cat $DUMP | grep -v -e '^d' | awk "{ print \$${COLS}; }"
		fi
	fi
}


#######################################################
# Start

list_files

rm -f $CFG
rm -f $DUMP

exit $RET
