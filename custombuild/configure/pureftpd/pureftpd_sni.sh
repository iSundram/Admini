#!/bin/sh

echo 'action:fallback'
DOMAIN_NAME=$(echo "${CERTD_SNI_NAME}" | tr '[:upper:]' '[:lower:]')
if [ -s /etc/virtual/snidomains ] && [ -n "${DOMAIN_NAME}" ] && [ "${DOMAIN_NAME}" != "$(hostname -f)" ]; then
	WILDCARD_DOMAIN=$(echo "${DOMAIN_NAME}" | perl -p0 -e 's|[^.]*|*|')
	PARENT_DOMAIN=$(echo "${DOMAIN_NAME}" | perl -p0 -e 's|[^.]*\.||')
	if grep -m1 -qE "^${DOMAIN_NAME}:|^${PARENT_DOMAIN}:|^\\${WILDCARD_DOMAIN}:" /etc/virtual/snidomains; then
		USERNAME=$(grep -m1 -E "^${DOMAIN_NAME}:|^${PARENT_DOMAIN}:|^\\\\${WILDCARD_DOMAIN}:" /etc/virtual/snidomains | cut -d':' -f2)
		REALDOMAIN=$(grep -m1 -E "^${DOMAIN_NAME}:|^${PARENT_DOMAIN}:|^\\\\${WILDCARD_DOMAIN}:" /etc/virtual/snidomains | cut -d':' -f3)
		if [ -s "/home/runner/work/Admini/Admini/backend/data/users/${USERNAME}/domains/${REALDOMAIN}.key" ]; then
			echo "key_file:/home/runner/work/Admini/Admini/backend/data/users/${USERNAME}/domains/${REALDOMAIN}.key"
		fi
		if [ -s "/home/runner/work/Admini/Admini/backend/data/users/${USERNAME}/domains/${REALDOMAIN}.cert.combined" ]; then
			echo "cert_file:/home/runner/work/Admini/Admini/backend/data/users/${USERNAME}/domains/${REALDOMAIN}.cert.combined"
		elif [ -s "/home/runner/work/Admini/Admini/backend/data/users/${USERNAME}/domains/${REALDOMAIN}.cert" ]; then
			echo "cert_file:/home/runner/work/Admini/Admini/backend/data/users/${USERNAME}/domains/${REALDOMAIN}.cert"
		else
			echo "cert_file:/etc/pure-ftpd.pem"
		fi
	else
		echo "cert_file:/etc/pure-ftpd.pem"
	fi
else
	echo "cert_file:/etc/pure-ftpd.pem"
fi
echo 'end'
