#!/bin/bash

set -e

if [ "$(id -u)" != 0 ]; then
        echo "script should be executed as root" 1>&2
        exit 1
fi

src=$1    # john_accounting_db
dst=$2    # peter_stuff_db

src_user=${src%%_*} # john
dst_user=${dst%%_*} # peter

if [ -z "${src}" ] || [ -z "${dst}" ] || [ -z "${src_user}" ] || [ -z "${dst_user}" ]; then
        echo "Usage:";
        echo "	$0 fromuser_sourcedb touser_destinationdb";
        exit 1;
fi

if ! api_url_src=$(da api-url --user="${src_user}"); then
	echo "failed to get API access key for '${src_user}', make sure such user exists" 1>&2
	exit 1
fi
if ! api_url_dst=$(da api-url --user="${dst_user}"); then
	echo "failed to get API access key for '${dst_user}', make sure such user exists" 1>&2
	exit 1
fi

if ! dd=$(curl --fail --silent --insecure "${api_url_src}/api/db-manage/databases/${src}/export-definition"); then
	echo "failed to load '${src}' database definition, make sure source database exists" 1>&2
	exit 1
fi

status=$(curl --silent --insecure --output /dev/null --write-out "%{http_code}" "${api_url_dst}/api/db-show/databases/${dst}")
case "${status}" in
	200)
		echo "destination database '${dst}' already exists" 1>&2
		exit 1
		;;
	404)
		# OK
		;;
	*)
		echo "failed checking if destination database '${dst}' exists: ${status}" 1>&2
		exit 1
		;;
esac

if ! curl --fail --silent --insecure --request POST --data "${dd}" "${api_url_dst}/api/db-manage/databases/${dst}/import-definition"; then
	echo "failed to create destination '${dst}' database" 1>&2
	exit 1
fi
echo "[OK] database '${dst}' created"

if ! curl --fail --silent --insecure "${api_url_src}/api/db-manage/databases/${src}/export" | curl --fail --silent --insecure --request POST --form sqlfile=@- "${api_url_dst}/api/db-manage/databases/${dst}/import"; then
	echo "failed transferring data from '${src}' to '${dst}'" 2>&1
	curl --fail --silent --insecure --request DELETE "${api_url_dst}/api/db-manage/databases/${dst}?drop-orphan-users=true"
	exit 1
fi
echo "[OK] data copied from '${src}' to '${dst}'"

if ! curl --fail --silent --insecure --request DELETE "${api_url_src}/api/db-manage/databases/${src}?drop-orphan-users=true"; then
	echo "destination database is created, but failed to remove source database '${src}'" 2>&1
	exit 1
fi
echo "[OK] database '${src}' removed"
