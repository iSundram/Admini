#!/bin/bash
#VERSION=0.0.8
# This script is written by Martynas Bendorius and DirectAdmin
# It is used to gzip all emails in Maildir directory
# Official DirectAdmin webpage: http://www.directadmin.com
# Usage:
# ./dovecot_compress.sh </home/user/imap/domain.com/email/Maildir>
MYUID=`/usr/bin/id -u`
if [ "${MYUID}" != 0 ]; then
	echo "You require Root Access to run this script"
	exit 0
fi

if [ $# -lt 1 ]; then
	echo "Usage:"
	echo "$0 /home/user/imap/domain.com/email/Maildir"
	echo "or"
	echo "$0 all"
	echo "or"
	echo "$0 /home/user/imap/domain.com/email/Maildir decompress"
	echo "or"
	echo "$0 decompress_all"
	echo "you gave #$#: $0 $1"
	exit 0
fi

SU_BIN=/bin/su

run_as_user()
{
	${SU_BIN} -l -s /bin/bash -c "umask 022; $2" ${1}
	return $?
}

if [ -e /usr/local/bin/zstdmt ] && [ -e /usr/local/bin/unzstd ]; then
	ZSTDMT_BIN=/usr/local/bin/zstdmt
	UNZSTD_BIN=/usr/local/bin/unzstd
else
	ZSTDMT_BIN=/usr/bin/zstdmt
	UNZSTD_BIN=/usr/bin/unzstd
fi

doCompressMaildir() {
	MAILDIR_PATH="${1}"
	if ! echo "${MAILDIR_PATH}" | grep -m1 -q '/Maildir$'; then
		echo "Path does not end with /Maildir: ${MAILDIR_PATH}. skipping.."
		return
	fi

	if [ ! -d "${MAILDIR_PATH}/cur" ]; then
		echo "${MAILDIR_PATH}/cur does not exist, skipping..."
		return
	fi

	cd "${MAILDIR_PATH}"
	if [ $? -ne 0 ]; then
		echo "Failed to cd to ${MAILDIR_PATH}. skipping..."
		return
	fi

	DIRECTORY_OWNER=`ls -ld "${MAILDIR_PATH}" | awk 'NR==1 {print $3}'`
	if [ -z "${DIRECTORY_OWNER}" ]; then
		echo "Unable to find directory owner of ${MAILDIR_PATH}"
		return
	fi

	DIRECTORY_OWNER=`ls -ld "${MAILDIR_PATH}" | awk 'NR==1 {print $3}'`
	if [ -z "${DIRECTORY_OWNER}" ]; then
		echo "Unable to find directory owner of ${MAILDIR_PATH}"
		return
	fi

	echo "Checking for directories in ${MAILDIR_PATH}..."

	# https://wiki.dovecot.org/Plugins/Zlib
	find . -maxdepth 2 -mindepth 1 -type d \( -name 'cur' -o -name "new" \) -print0 | while read -d $'\0' directory; do {
		TMPMAILDIR="${MAILDIR_PATH}/${directory}/../tmp"
		if [ -d "${MAILDIR_PATH}/${directory}" ] && [ ! -d "${MAILDIR_PATH}/${directory}"/tmp/cur ]; then
			run_as_user ${DIRECTORY_OWNER} "mkdir -p \"${TMPMAILDIR}\""
		fi
		run_as_user ${DIRECTORY_OWNER} "find \"${TMPMAILDIR}\" -maxdepth 1 -group mail -type f -delete"
		# ignore all files with "*,S=*" (dovecot needs to know the size of the email, when it's gzipped) and "*,*:2,*,*Z*" (dovecot recommends adding Z to the end of gzipped files just to know which ones are gzipped) in their names, also skip files that are also compressed (find skips all other 'exec' after first failure)
		# dovecot: Note that if the filename doesn't contain the ',S=<size>' before compression, adding it afterwards changes the base filename and thus the message UID. The safest thing to do is simply to not compress such files.
		if [ "$2" = "decompress" ]; then
			run_as_user ${DIRECTORY_OWNER} "cd \"${MAILDIR_PATH}/${directory}\" && find . -type f -name \"*,S=*\" ! -name \"*,*:2,*,*Z*\" -exec gzip -t {} 2>/dev/null \; -exec sh -c 'gunzip --stdout \$1 > \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'chmod --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'touch --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \;"
			if run_as_user ${DIRECTORY_OWNER} "[ -x ${ZSTDMT_BIN} ]"; then
				run_as_user ${DIRECTORY_OWNER} "cd \"${MAILDIR_PATH}/${directory}\" && find . -type f -name \"*,S=*\" ! -name \"*,*:2,*,*Z*\" -exec ${ZSTDMT_BIN} -l {} 2>&1>/dev/null \; -exec sh -c '${UNZSTD_BIN} -fq \$1 -o \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'chmod --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'touch --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \;"
			fi
		else
			if run_as_user ${DIRECTORY_OWNER} "[ ! -x ${ZSTDMT_BIN} ]"; then
				run_as_user ${DIRECTORY_OWNER} "cd \"${MAILDIR_PATH}/${directory}\" && find . -type f -name \"*,S=*\" ! -name \"*,*:2,*,*Z*\" ! -exec gzip -t {} 2>/dev/null \; -exec sh -c 'gzip --best --stdout \$1 > \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'chmod --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'touch --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \;"
			else
				run_as_user ${DIRECTORY_OWNER} "cd \"${MAILDIR_PATH}/${directory}\" && find . -type f -name \"*,S=*\" ! -name \"*,*:2,*,*Z*\" -exec gzip -t {} 2>/dev/null \; -exec sh -c 'gunzip < \$1 | ${ZSTDMT_BIN} -6 -fq -o \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'chmod --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'touch --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \;"
				run_as_user ${DIRECTORY_OWNER} "cd \"${MAILDIR_PATH}/${directory}\" && find . -type f -name \"*,S=*\" ! -name \"*,*:2,*,*Z*\" ! -exec test -e \"${TMPMAILDIR}\"/{} \; ! -exec ${ZSTDMT_BIN} -l {} 2>&1>/dev/null \; -exec sh -c '${ZSTDMT_BIN} -6 -fq \$1 -o \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'chmod --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \; -exec sh -c 'touch --reference=\$1 \"${TMPMAILDIR}\"/\$1' x {} \;"
			fi
		fi
		#if there are any compressed files, maildirlock the directory
		if ! find "${TMPMAILDIR}" -maxdepth 0 -type d -empty | grep -m1 -q '\.'; then
			echo "Size before compression: `du -sh \"${MAILDIR_PATH}/${directory}\" | awk '{print $1}'`"
			MAILDIRLOCK=/usr/libexec/dovecot/maildirlock
			if [ ! -x ${MAILDIRLOCK} ]; then
				MAILDIRLOCK=/usr/lib/dovecot/maildirlock
			fi
			if [ ! -x ${MAILDIRLOCK} ]; then
				echo "Unable to find ${MAILDIRLOCK}, exiting..."
				run_as_user ${DIRECTORY_OWNER} "find \"${TMPMAILDIR}\" -maxdepth 1 -group mail -type f -delete"
				exit 2
			fi
			if [ -d /etc/cagefs/conf.d ] && [ ! -s /etc/cagefs/conf.d/dovecot.cfg ]; then
				echo '[dovecot]' > /etc/cagefs/conf.d/dovecot.cfg 
				echo 'comment=dovecot' >> /etc/cagefs/conf.d/dovecot.cfg 
				echo "paths=${MAILDIRLOCK}" >> /etc/cagefs/conf.d/dovecot.cfg
				if [ -x /usr/sbin/cagefsctl ]; then
					/usr/sbin/cagefsctl --force-update
				fi
			fi
			# If we're able to create the maildirlock, then continue with moving compressed emails back
			#MAILDIRLOCK had a bug, which is patched in CB 2.0
			if PIDOFMAILDIRLOCK=`run_as_user ${DIRECTORY_OWNER} "${MAILDIRLOCK} \"${MAILDIR_PATH}\" 10"`; then
				# Move email only if it exists in destination folder, otherwise it's been removed at the time we converted it
				run_as_user ${DIRECTORY_OWNER} "find \"${TMPMAILDIR}\" -maxdepth 1 -type f -exec sh -c 'if [ -s \"\${1}\" ]; then mv -f \"\${1}\" \"${MAILDIR_PATH}/${directory}\"/; fi' x {} \;"
				kill ${PIDOFMAILDIRLOCK}
				echo "Compressed ${MAILDIR_PATH}/${directory}..."
				# Remove dovecot index files to have no issues with mails
				run_as_user ${DIRECTORY_OWNER} "find \"${MAILDIR_PATH}\" -type f -name dovecot.index\* -delete"
				echo "Size after compression: `du -sh \"${MAILDIR_PATH}/${directory}\" | awk '{print $1}'`"
			else
				echo "Failed to lock: ${MAILDIR_PATH}" >&2
				run_as_user ${DIRECTORY_OWNER} "find \"${TMPMAILDIR}\" -maxdepth 1 -group mail -type f -delete"
			fi
		fi
	};
	done
}

if [ "${1}" = "all" ]; then
	cat /etc/virtual/*/passwd | cut -d: -f6 | sort | uniq | while read line; do {
		doCompressMaildir "${line}/Maildir" "$2"
	}
	done
elif [ "${1}" = "decompress_all" ]; then
	cat /etc/virtual/*/passwd | cut -d: -f6 | sort | uniq | while read line; do {
		doCompressMaildir "${line}/Maildir" "decompress"
	}
	done
else
	doCompressMaildir "${1}" "$2"
fi

exit 0
