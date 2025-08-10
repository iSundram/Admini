#!/bin/bash
VERSION=1.2
CURL=/usr/local/bin/curl
if [ ! -e ${CURL} ]; then
		CURL=/usr/bin/curl
fi
DU=/usr/bin/du
BC=/usr/bin/bc
EXPR=/usr/bin/expr
TOUCH=/bin/touch
PORT=${ftp_port}
FTPS=0

MD5=${ftp_md5}

if [ "${ftp_secure}" = "ftps" ]; then
	FTPS=1
fi

CURL_TLS_HELP=$(${CURL} --help tls)
CURL_VERSION=$(${CURL} --version | head -n 1 | cut -d ' ' -f 2)
int_version() {
	local major minor patch
	major=$(cut -d . -f 1 <<< "$1")
	minor=$(cut -d . -f 2 <<< "$1")
	patch=$(cut -d . -f 3 <<< "$1")
	printf "%03d%03d%03d" "${major}" "${minor}" "${patch}"
}

SSL_ARGS=""
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

#######################################################
# SETUP

if [ ! -e $TOUCH ] && [ -e /usr/bin/touch ]; then
	TOUCH=/usr/bin/touch
fi
if [ ! -x ${EXPR} ] && [ -x /bin/expr ]; then
	EXPR=/bin/expr
fi

if [ ! -e "${ftp_local_file}" ]; then
	echo "Cannot find backup file ${ftp_local_file} to upload";

	/bin/ls -la ${ftp_local_path}

	/bin/df -h

	exit 11;
fi

get_md5() {
	MF=$1

	MD5SUM=/usr/bin/md5sum
	if [ ! -x ${MD5SUM} ]; then
		return
	fi

	if [ ! -e ${MF} ]; then
		return
	fi

	FMD5=`$MD5SUM $MF | cut -d\  -f1`

	echo "${FMD5}"
}

#######################################################

CFG=${ftp_local_file}.cfg
/bin/rm -f $CFG
$TOUCH $CFG
/bin/chmod 600 $CFG

RET=0;

#######################################################
# FTP
upload_file_ftp()
{
        if [ ! -e ${CURL} ]; then
                echo "";
                echo "*** Backup not uploaded ***";
                echo "Please install curl";
                echo "";
                exit 10;
        fi

        /bin/echo "user =  \"$ftp_username:$ftp_password_esc_double_quote\"" >> $CFG

        if [ ! -s ${CFG} ]; then
                echo "${CFG} is empty. curl is not going to be happy about it.";
                ls -la ${CFG}
                ls -la ${ftp_local_file}
                df -h
        fi

        #ensure ftp_path ends with /
        ENDS_WITH_SLASH=`echo "$ftp_path" | grep -c '/$'`
        if [ "${ENDS_WITH_SLASH}" -eq 0 ]; then
                ftp_path=${ftp_path}/
        fi

        ${CURL} --config ${CFG} --silent --show-error --ftp-create-dirs --upload-file $ftp_local_file  ftp://$ftp_ip:${PORT}/$ftp_path$ftp_remote_file 2>&1
        RET=$?

        if [ "${RET}" -ne 0 ]; then
                echo "curl return code: $RET";
        fi
}

#######################################################
# FTPS
upload_file_ftps()
{
	if [ ! -e ${CURL} ]; then
		echo "";
		echo "*** Backup not uploaded ***";
		echo "Please install curl";
		echo "";
		exit 10;
	fi

	/bin/echo "user =  \"$ftp_username:$ftp_password_esc_double_quote\"" >> $CFG

	if [ ! -s ${CFG} ]; then
		echo "${CFG} is empty. curl is not going to be happy about it.";
		ls -la ${CFG}
		ls -la ${ftp_local_file}
		df -h
	fi

	#ensure ftp_path ends with /
	ENDS_WITH_SLASH=`echo "$ftp_path" | grep -c '/$'`
	if [ "${ENDS_WITH_SLASH}" -eq 0 ]; then
		ftp_path=${ftp_path}/
	fi

	${CURL} --config ${CFG} ${SSL_ARGS} -k --silent --show-error --ftp-create-dirs --upload-file $ftp_local_file  ftp://$ftp_ip:${PORT}/$ftp_path$ftp_remote_file 2>&1
	RET=$?

	if [ "${RET}" -ne 0 ]; then
		echo "curl return code: $RET";
	fi
}

#######################################################
# Start

if [ "${FTPS}" = "1" ]; then
	upload_file_ftps
else
	upload_file_ftp
fi

if [ "${RET}" = "0" ] && [ "${MD5}" = "1" ]; then
	MD5_FILE=${ftp_local_file}.md5
	M=`get_md5 ${ftp_local_file}`
	if [ "${M}" != "" ]; then
		echo "${M}" > ${MD5_FILE}

		ftp_local_file=${MD5_FILE}
		ftp_remote_file=${ftp_remote_file}.md5

		if [ "${FTPS}" = "1" ]; then
			upload_file_ftps
		else
			upload_file
		fi
	fi
fi

/bin/rm -f $CFG

exit $RET

