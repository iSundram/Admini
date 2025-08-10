#!/bin/sh
# This script is written by Martynas Bendorius and DirectAdmin
# It is used to convert reseller to user
# Official DirectAdmin webpage: http://www.directadmin.com

if [ "$(id -u)" != 0 ]; then
        echo "You require Root Access to run this script";
        exit 1;
fi

if [ $# != 2 ]; then
        echo "Usage:";
        echo "  $0 <user> <reseller>";
        echo "you gave #$#: $0 $1 $2";
	echo "where:"
	echo "user: name of the account to downgrade."
	echo "reseller: name of the new creator of the User: eg: admin";
        exit 1;
fi

PAYLOAD=$(printf '{"account": "%s", "creator": "%s"}' "$1" "$2")
OUTPUT=$(curl --insecure --silent --write-out '\n%{http_code}' --data "${PAYLOAD}" "$(/usr/local/directadmin/directadmin api-url)/api/convert-reseller-to-user")
CODE=$(echo "${OUTPUT}" | tail -n 1)

if [ "${CODE}" = "200" ] || [ "${CODE}" = "204" ]; then
	echo "Reseller '$1' has been converted to user."
	exit 0
else
	echo "${OUTPUT}" | sed '$d'
	exit 1
fi
