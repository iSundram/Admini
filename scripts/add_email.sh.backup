#!/bin/bash

#script to add an email account to DirectAdmin via command line.

if [ "$(id -u)" != "0" ]; then
	echo "You require Root Access to run this script"
	exit 1
fi

if [ "$#" -lt 3 ]; then
	echo "Usage:"
	echo "   $0 <user> <domain> '<password>' (1 <quota>)"
	echo ""
	echo "Variables <user> <domain> and '<password>' are MANDATORY."
	echo "If you want to specify quota - 1 (digit one) must be spefified"
	echo "as a parameter as well for backwards compatibility"
	echo ""
	echo "Examples:"
	echo "$0 admin example.com 'superStr0ngPw'"
	echo "$0 admin example.com 'superStr0ngPw' 1 50"
	echo ""
	echo "The domain must already exist under a DA account"
	exit 2
fi

EMAIL="$1"
DOMAIN="$2"
PASS="$3"
if [ -n "$4" ] && [ "$4" -ne 1 ]; then
	echo "Adding hashes is not supported!"
	exit 1
fi

if [ -n "$5" ]; then
	QUOTAVAL="$5"
else
	QUOTAVAL=0
fi

DAUSER=$(grep "^${DOMAIN}:" /etc/virtual/domainowners | awk '{print $2}')
if [ -z "${DAUSER}" ]; then
	echo "Could not find the owner of the domain!"
	exit 1
fi


PAYLOAD=$(printf '{"user":"%s","domain":"%s","passwd2":"%s","passwd":"%s","quota":"%s","json":"yes","action":"create"}' "${EMAIL}" "${DOMAIN}" "${PASS}" "${PASS}" "${QUOTAVAL}")
OUTPUT=$(curl --insecure --silent --write-out '\n%{http_code}' --data "${PAYLOAD}" "$(/usr/local/directadmin/directadmin api-url --user="${DAUSER}")/CMD_EMAIL_POP?json=yes")
CODE=$(echo "${OUTPUT}" | tail -n 1)

if [ "${CODE}" = "200" ]; then
	echo "Mailbox '${EMAIL}@${DOMAIN}' has been created"
	exit 0
else
	echo "${OUTPUT}" | sed '$d'
	exit 1
fi