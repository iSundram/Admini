#!/bin/bash

export EXEC_PROPAGATION_TIMEOUT=300
export EXEC_POLLING_INTERVAL=30

# Use Google DNS for external lookups
DNS_SERVER="8.8.8.8"
DNS6_SERVER="2001:4860:4860::8888"
# Fallback DNS server
DA_IPV6=false
LEGO_DATA_PATH=/home/runner/work/Admini/Admini/backend/data/.lego
WELLKNOWN_PATH=/var/www/html/.well-known/acme-challenge
SERVER_CERT_DNSPROVIDER_ENV=/home/runner/work/Admini/Admini/backend/conf/ca.dnsprovider
DNS_SERVERS=( "8.8.8.8" "1.1.1.1" "2001:4860:4860::8888" "2606:4700:4700::1111")

if [ "$(id -u)" != "0" ]; then
	echo "this script can only run as root";
	exit 1
fi

if [ ! -x /usr/local/bin/lego ]; then
	echo "missing 'lego' command, it can be installed using CustomBuild with command:"
	echo "	da build lego"
	exit 1
fi

fallbackedDig(){
	lastret=1
	for i in "${DNS_SERVERS[@]}";do
		resp=$(dig "@${i}" "$@")
		lastret=$?
		if [ "${lastret}" -eq "0" ];then
			echo "${resp}"
			return 0
		fi
		if [ "${lastret}" -ne "9" ];then
			return "${lastret}"
		fi
		DNS_SERVERS=("${DNS_SERVERS[@]:1}" "$i")
	done
	return ${lastret}
}

caa_check() {
	CAA_OK=true
	for i in $(echo "$1" | awk -F'.' '{b=$NF;for(i=NF-1;i>0;i--){b=$i FS b;print b}}'); do
		if fallbackedDig CAA "${i}" +short | grep -m1 -q -F -- "issue"; then
			CAA_OK=false
			if fallbackedDig CAA "${i}" +short | grep -m1 -q -F -- "letsencrypt.org"; then
				CAA_OK=true
			else
				CAA_CURRENT=$(fallbackedDig CAA "${i}" +short | grep -m1 issue | awk '{print $3}')
			fi
		fi
		if fallbackedDig CAA "${i}" | grep -m1 -q -F -- "SERVFAIL"; then
			CAA_OK=false
			CAA_CURRENT="SERVFAIL"
		fi
	done

	if ! ${CAA_OK}; then
		echo "CAA record prevents issuing the certificate: ${CAA_CURRENT}"
		exit 1
	fi
}

challenge_check() {
	TEMP_FILENAME="letsencrypt_$(openssl rand -hex 16)"
	touch "${WELLKNOWN_PATH}/${TEMP_FILENAME}"
	chmod 644 "${WELLKNOWN_PATH}/${TEMP_FILENAME}"
	#if 8.8.8.8 is not accessible, dig returns code 9.  The dig|grep method returns code 1, so have to redo.
	IP_TO_RESOLV=$(fallbackedDig AAAA "$1" +short | grep -v '\.$' | tail -n1)
	if ! echo "${IP_TO_RESOLV}" | grep -m1 -q ':'; then
		IP_TO_RESOLV=""
	fi
	if [ -z "${IP_TO_RESOLV}" ]; then
		IP_TO_RESOLV=$(fallbackedDig "$1" +short | tail -n1)
	fi
	if [ -z "${IP_TO_RESOLV}" ]; then
		rm -f "${WELLKNOWN_PATH}/${TEMP_FILENAME}"
		return 1
	fi
	if command -v ping6 > /dev/null; then
		if ! ${DA_IPV6}; then
			if ! ping6 -q -c 1 -W 1 "$1" >/dev/null 2>&1; then
				IP_TO_RESOLV=$(fallbackedDig "$1" +short | tail -n1)
			fi
		fi
	fi
	local CURL_OPTIONS=("--connect-timeout" "40" "-k" "--silent")
	if [ -n "${IP_TO_RESOLV}" ]; then
		CURL_OPTIONS+=("--resolve" "${1}:80:${IP_TO_RESOLV}" "--resolve" "${1}:443:${IP_TO_RESOLV}")
	fi
	if ! curl "${CURL_OPTIONS[@]}" -I -L -X GET "http://${1}/.well-known/acme-challenge/${TEMP_FILENAME}" 2>/dev/null | grep -m1 -q 'HTTP.*200'; then
		if [ "$2" = "silent" ]; then
			rm -f "${WELLKNOWN_PATH}/${TEMP_FILENAME}"
			return 1
		else
			echo "Challenge pre-checks for http://${1}/.well-known/acme-challenge/${TEMP_FILENAME} failed... Command:"
			echo "curl ${CURL_OPTIONS[*]} -I -L -X GET http://${1}/.well-known/acme-challenge/${TEMP_FILENAME}"
			echo "Exiting."
			rm -f "${WELLKNOWN_PATH}/${TEMP_FILENAME}"
			exit 1
		fi
	elif [ "$2" = "silent" ]; then
		rm -f "${WELLKNOWN_PATH}/${TEMP_FILENAME}"
		return 0
	fi
	if [ -e "${WELLKNOWN_PATH}/${TEMP_FILENAME}" ]; then
		rm -f "${WELLKNOWN_PATH}/${TEMP_FILENAME}"
	fi
}

acme_provider_url() {
	local provider=$1

	case "${provider}" in
		zerossl)             echo "https://acme.zerossl.com/v2/DV90" ;;
		letsencrypt-staging) echo "https://acme-staging-v02.api.letsencrypt.org/directory" ;;
		letsencrypt)         echo "https://acme-v02.api.letsencrypt.org/directory" ;;
		buypass)             echo "https://api.buypass.com/acme/directory" ;;
		buypass-staging)     echo "https://api.test4.buypass.no/acme/directory" ;;
		*)                   echo "https://acme-v02.api.letsencrypt.org/directory" ;;
	esac
}

lego_key_type() {
	local key_type=$1

	case "${key_type}" in
		# Identity mappings
		ec256|ec384|rsa2048|rsa4096|rsa8192) echo "${key_type}";;
		# Old DA key-types
		prime256v1) echo "ec256";;
		secp384r1)  echo "ec384";;
		2048)       echo "rsa2048";;
		4096)       echo "rsa4096";;
		8192)       echo "rsa8192";;
		# Default
		*) echo "ec256" ;;
	esac
}

issue_lego_cert() {
	local provider=$1          # letsencrypt
	local key_type=$2          # ec256
	local dnsprovider=$3       # empty means HTTP challenge
	local domains=("${@:4}")   # example.com *.example.com

	local email
	email=$(sed -n 's/^email=\([^,]*\).*$/\1/p' "/home/runner/work/Admini/Admini/backend/data/users/$(da admin)/user.conf" 2>/dev/null)
	if [ -z "${email}" ]; then
		email="admin@$(da config-get servername)"
	fi

	local args=(
		--path "${LEGO_DATA_PATH}"
		--dns.resolvers "${DNS_SERVER}"
		--accept-tos
		--server "$(acme_provider_url "${provider}")"
		--email "${email}"
		--key-type "$(lego_key_type "${key_type}")"
	)
	if [ -z "${dnsprovider}" ]; then
		args+=(--http)
		if has_webserver; then
			args+=("--http.webroot" "/var/www/html")
		fi
	else
		args+=(--dns "${dnsprovider}")
	fi
	for d in "${domains[@]}"; do
		args+=(--domains "$d")
	done
	/usr/local/bin/lego "${args[@]}" run --no-bundle --preferred-chain="ISRG Root X1"
}

install_file() {
	local file_mode=$1  # 640
	local file_owner=$2 # diradmin:access
	local src=$3        # /home/runner/work/Admini/Admini/backend/conf/cacert.pem.combined
	local dst=$4        # /etc/exim.cert

	local tmp_file
	if ! tmp_file=$(mktemp --tmpdir="$(dirname "${dst}")" --suffix=".$(basename "${dst}")"); then
		echo "${FUNCNAME[0]}: failed to create temp file for '${dst}'" 1>&2
		return 1
	fi

	if ! cp -f "${src}" "${tmp_file}"; then
		rm -f "${tmp_file}"
		echo "${FUNCNAME[0]}: failed to copy '${src}' to '${tmp_file}'" 1>&2
		return 1
	fi
	if ! chmod "${file_mode}" "${tmp_file}"; then
		rm -f "${tmp_file}"
		echo "${FUNCNAME[0]}: chmod '${tmp_file}' to '${file_mode}'" 1>&2
		return 1
	fi
	if ! chown "${file_owner}" "${tmp_file}"; then
		rm -f "${tmp_file}"
		echo "${FUNCNAME[0]}: failed to chown '${tmp_file}' to '${file_owner}'" 1>&2
		return 1
	fi
	if ! mv -f "${tmp_file}" "${dst}"; then
		rm -f "${tmp_file}"
		echo "${FUNCNAME[0]}: failed to rename '${tmp_file}' to '${dst}'" 1>&2
		return 1
	fi
}

install_lego_cert() {
	local name=$1       # example.net
	local dst_key=$2    # /home/runner/work/Admini/Admini/backend/data/users/{user}/domains/{name}.key
	local dst_crt=$3    # /home/runner/work/Admini/Admini/backend/data/users/{user}/domains/{name}.cert
	local dst_ca_crt=$4 # /home/runner/work/Admini/Admini/backend/data/users/{user}/domains/{name}.cacert

	local src_key="${LEGO_DATA_PATH}/certificates/${name}.key"
	local src_crt="${LEGO_DATA_PATH}/certificates/${name}.crt"
	local src_ca_crt="${LEGO_DATA_PATH}/certificates/${name}.issuer.crt"

	if [ ! -s "${src_key}" ]; then
		echo "key file '${src_key}' is missing or empty" 1>&2
		return 1
	fi
	if [ ! -s "${src_crt}" ]; then
		echo "certificate file '${src_crt}' is missing or empty" 1>&2
		return 1
	fi

	# lego older than 4.9 used to combine multiple certs in main cert file:
	# https://github.com/go-acme/lego/issues/963
	if [ "$(grep -c "BEGIN CERTIFICATE" "${src_crt}")" -gt 1 ]; then
		local first_cert
		first_cert=$(openssl x509 -in "${src_crt}") || return 1
		cat > "${src_crt}" <<< "${first_cert}" || return 1
	fi

	local access_group
	access_group=$(da config-get secure_access_group)

	# FIXME there is race condition between moving key and cert usually this
	# is not a problem for services that require reload to re-read certs.
	local owner="diradmin:${access_group:-mail}"
	install_file 640 "${owner}" "${src_key}"                        "${dst_key}"               || return 1
	install_file 640 "${owner}" "${src_crt}"                        "${dst_crt}"               || return 1
	install_file 640 "${owner}" "${src_ca_crt}"                     "${dst_ca_crt}"            || return 1
	install_file 640 "${owner}" <(cat "${src_crt}" "${src_ca_crt}") "${dst_crt}.combined"      || return 1
	install_file 640 "${owner}" <(date +%s)                         "${dst_crt}.creation_time" || return 1
}

command_revoke() {
	local domain=$2
	local user
	local provider
	local email

	email=$(sed -n 's/^email=\([^,]*\).*$/\1/p' "/home/runner/work/Admini/Admini/backend/data/users/$(da admin)/user.conf" 2>/dev/null)
	if [ -z "${email}" ]; then
		email="admin@$(da config-get servername)"
	fi

	user=$(grep -m 1 "^${domain//./\\.}: " /etc/virtual/domainowners | cut -d ' ' -f 2)
	local domain_conf_file="/home/runner/work/Admini/Admini/backend/data/users/${user}/domains/${domain}.conf"
	local domain_ssl_file="/home/runner/work/Admini/Admini/backend/data/users/${user}/domains/${domain}.ssl"
	if [ -n "${user}" ] && [ -s "${domain_conf_file}" ]; then
		provider=$(grep -m1 ^acme_provider= "${domain_conf_file}" | cut -d= -f2)
	elif [ -n "${user}" ] && [ -s "${domain_ssl_file}" ]; then
		provider=$(grep -m1 ^acme_provider= "${domain_ssl_file}" | cut -d= -f2)
	else
		provider=$(da config-get default_acme_provider)
	fi

	/usr/local/bin/lego \
		--path "${LEGO_DATA_PATH}" \
		--server "$(acme_provider_url "${provider}")" \
		--email "${email}" \
		--domains "${domain}" \
		revoke
}

has_wildcard_domain() {
	local d
	for d in "${@}"; do
		if [ "${d}" != "${d/\*.}" ]; then
			return 0
		fi
	done
	return 1
}

has_webserver() {
	# Result of first check is cached in global variable
        if [ -z "${has_webserver_rc:-}" ]; then
		if ss --no-header --listening --numeric --tcp 'sport = 80' | grep --quiet LISTEN; then
                        has_webserver_rc=0
                else
                        has_webserver_rc=1
                fi
        fi
        return "${has_webserver_rc}"
}

command_server_cert() {
	local domain_csv=$1
	local key_type=$2

	da config-set acme_server_cert_enabled "1"
	if [ -n "${domain_csv}" ]; then
		ADDITIONAL_DOMAINS="$(tr , '\n' <<< "${domain_csv}" | grep -Fvx "$(da config-get servername)" | paste -sd,)"
		da config-set acme_server_cert_additional_domains "${ADDITIONAL_DOMAINS}"
	fi
	if [ -n "${key_type}" ]; then 
		da config-set acme_server_cert_key_type "$(lego_key_type "${key_type}")"
	fi
	if [ -s "${SERVER_CERT_DNSPROVIDER_ENV}" ]; then
		da config-set acme_server_cert_dns_provider_env_file "${SERVER_CERT_DNSPROVIDER_ENV}"
	fi

	if ! da taskq --run 'action=ssl&value=server_acme&force=true'; then
		echo "Failed to issue new certificate"
		exit 1
	fi

	echo "Server certificate with domains ${domain_csv} has been created successfully"
	
	da config-set ssl 1

	if systemctl --quiet is-active directadmin.service; then
		systemctl restart directadmin.service
	fi
}

command_do_everything() {
	local action=$1     # request|renew|revoke
	DOMAIN=$2
	KEY_SIZE=$3
	CSR_CF_FILE=$4

	if [ "$(da config-get ipv6)" = "1" ]; then
		if command -v ping6 > /dev/null; then
			if ping6 -q -c 1 -W 1 ${DNS6_SERVER} >/dev/null 2>&1; then
				DA_IPV6=true
				DNS_SERVER=${DNS6_SERVER}
			fi
		fi
	fi

	CHALLENGETYPE="http"

	DA_HOSTNAME=$(da config-get servername)

	CHILD_DOMAIN=false

	#We need the domain to match in /etc/virtual/domainowners, if we use grep -F, we cannot use any regex'es including ^
	FOUNDDOMAIN=0
	for TDOMAIN in $(echo "${DOMAIN}" | tr ',' ' '); do
		if [ "${DA_HOSTNAME}" = "${TDOMAIN}" ]; then
			#we're a hostname, skip this check
			break
		fi
		DOMAIN_NAME_FOUND=${TDOMAIN}
		DOMAIN_ESCAPED=${TDOMAIN//./\\.}

		if grep -m1 -q "^${DOMAIN_ESCAPED}:" /etc/virtual/domainowners; then
			USER=$(grep -m1 "^${DOMAIN_ESCAPED}:" /etc/virtual/domainowners | cut -d' ' -f2)
			HOSTNAME=0
			FOUNDDOMAIN=1
			PARENT_DOMAIN_NAME_FOUND=${TDOMAIN}
			break
		fi
	done

	if [ "${FOUNDDOMAIN}" = "0" ]; then
		#check parent domain
		for TDOMAIN in $(echo "${DOMAIN}" | tr ',' ' '); do
			if [ "${DA_HOSTNAME}" = "${TDOMAIN}" ]; then
				#we're a hostname, skip this check
				break
			fi

			#should only apply to subdomains.
			#Domain match would have been in the first FOUNDDOMAIN case.
			#Thus there should be at least 2 dots.
			#start matching the longest possible parent domain, then reduce parent & increase subdomain length.
			#foo.bar.domain.com: DOT_COUNT=3
			DOT_COUNT=`echo "${TDOMAIN}" | grep -o '\.' | wc -l`
			CHECK_FIELD=2
			FIELD_LESS_ONE=1
			while [ ${CHECK_FIELD} -le ${DOT_COUNT} ]; do
				CHILD_NAME=$(echo "${TDOMAIN}" | cut -d'.' -f-${FIELD_LESS_ONE})
				PARENT_DOMAIN_NAME_FOUND=$(echo "${TDOMAIN}" | cut -d'.' -f${CHECK_FIELD}-)
				PARENT_DOMAIN_ESCAPED=${PARENT_DOMAIN_NAME_FOUND//./\\.}
				PARENT_DOMAIN_OWNER_USER=$(grep -m1 "^${PARENT_DOMAIN_ESCAPED}:" /etc/virtual/domainowners | cut -d' ' -f2)
				if [ -s "/home/runner/work/Admini/Admini/backend/data/users/${PARENT_DOMAIN_OWNER_USER}/domains/${PARENT_DOMAIN_NAME_FOUND}.subdomains" ] && grep -q "^${CHILD_NAME}$" "/home/runner/work/Admini/Admini/backend/data/users/${PARENT_DOMAIN_OWNER_USER}/domains/${PARENT_DOMAIN_NAME_FOUND}.subdomains"; then
					DOMAIN_NAME_FOUND=${TDOMAIN}
					DOMAIN_ESCAPED=${DOMAIN_NAME_FOUND//./\\.}
					USER=${PARENT_DOMAIN_OWNER_USER}
					HOSTNAME=0
					FOUNDDOMAIN=1
					CHILD_DOMAIN=true
					break 2
				fi
				((CHECK_FIELD++))
				((FIELD_LESS_ONE++))
			done
		done
	fi
	if [ "${FOUNDDOMAIN}" = "0" ]; then
		LETSENCRYPT_LIST=$(da config-get letsencrypt_list | tr ':' ' ')
		#check parent domain
		for TDOMAIN in $(echo "${DOMAIN}" | tr ',' ' '); do
			if [ "${DA_HOSTNAME}" = "${TDOMAIN}" ]; then
				#we're a hostname, skip this check
				break
			fi
			if [ "${FOUNDDOMAIN}" != "0" ]; then
				break
			fi
			if [ "$(echo "${TDOMAIN}" | grep -o '\.' | wc -l)" -gt 1 ]; then
				CHILD_NAME=$(echo "${TDOMAIN}" | cut -d'.' -f1)
				PARENT_DOMAIN_NAME_FOUND=$(echo "${TDOMAIN}" | perl -p0 -e 's|^[^\.]*\.||g')
				PARENT_DOMAIN_ESCAPED=${PARENT_DOMAIN_NAME_FOUND//./\\.}
				PARENT_DOMAIN_OWNER_USER=$(grep -m1 "^${PARENT_DOMAIN_ESCAPED}:" /etc/virtual/domainowners | cut -d' ' -f2)
				for letsencrypt_prefix in ${LETSENCRYPT_LIST}; do
					if [ "${CHILD_NAME}" = "${letsencrypt_prefix}" ] && [ -n "${PARENT_DOMAIN_OWNER_USER}" ]; then
						DOMAIN_NAME_FOUND=${TDOMAIN}
						DOMAIN_ESCAPED=${DOMAIN_NAME_FOUND//./\\.}
						USER=${PARENT_DOMAIN_OWNER_USER}
						HOSTNAME=0
						FOUNDDOMAIN=1
						CHILD_DOMAIN=true
						break
					fi
				done
			fi
		done
	fi
	if [ "${FOUNDDOMAIN}" = "0" ]; then
		for TDOMAIN in $(echo "${DOMAIN}" | tr ',' ' '); do
			DOMAIN_NAME_FOUND=${TDOMAIN}
			DOMAIN_ESCAPED=${DOMAIN_NAME_FOUND//./\\.}
			USER="root"
			if [ "${DA_HOSTNAME}" = "${TDOMAIN}" ]; then
				echo "Setting up certificate for a hostname: ${DOMAIN_NAME_FOUND}"
				HOSTNAME=1
				FOUNDDOMAIN=1
				if ! grep -m1 -q "^${DOMAIN_ESCAPED}$" /etc/virtual/domains; then
					echo "${DOMAIN_NAME_FOUND}" >> /etc/virtual/domains
				fi
				break
			else
				echo "Domain does not exist on the system. Unable to find ${DOMAIN_NAME_FOUND} in /etc/virtual/domainowners, and domain is not set as hostname (servername) in DirectAdmin configuration. Exiting..."
			fi
		done
	fi

	if [ ${FOUNDDOMAIN} -eq 0 ]; then
		echo "no valid domain found - exiting"
		exit 1
	fi

	DA_USERDIR="/home/runner/work/Admini/Admini/backend/data/users/${USER}"
	DA_CONFDIR="/home/runner/work/Admini/Admini/backend/conf"

	if [ ! -d "${DA_USERDIR}" ] && [ "${HOSTNAME}" -eq 0 ]; then
		echo "${DA_USERDIR} not found, exiting..."
		exit 1
	elif [ ! -d "${DA_CONFDIR}" ] && [ "${HOSTNAME}" -eq 1 ]; then
		echo "${DA_CONFDIR} not found, exiting..."
		exit 1
	fi

	if [ "${HOSTNAME}" -eq 0 ]; then
		DNSPROVIDER_FALLBACK="${DA_USERDIR}/domains/${DOMAIN_NAME_FOUND}.dnsprovider"
		if [ -s "${DNSPROVIDER_FALLBACK}" ]; then
			if grep -m1 -q "^dnsprovider=inherit-creator$" "${DNSPROVIDER_FALLBACK}"; then
				CREATOR=$(grep -m1 '^creator=' "${DA_USERDIR}/user.conf" | cut -d= -f2)
				CREATOR_DNSPROVIDER="/home/runner/work/Admini/Admini/backend/data/users/${CREATOR}/dnsprovider.conf"
				if [ -s "${CREATOR_DNSPROVIDER}" ]; then
					DNSPROVIDER_FALLBACK="${CREATOR_DNSPROVIDER}"
				fi
			elif grep -m1 -q "^dnsprovider=inherit-global$" "${DNSPROVIDER_FALLBACK}"; then
				if [ -s "/home/runner/work/Admini/Admini/backend/data/admin/dnsprovider.conf" ]; then
					DNSPROVIDER_FALLBACK="/home/runner/work/Admini/Admini/backend/data/admin/dnsprovider.conf"
				fi
			fi
		fi
		KEY="${DA_USERDIR}/domains/${DOMAIN_NAME_FOUND}.key"
		CERT="${DA_USERDIR}/domains/${DOMAIN_NAME_FOUND}.cert"
		CACERT="${DA_USERDIR}/domains/${DOMAIN_NAME_FOUND}.cacert"
	else
		DNSPROVIDER_FALLBACK="${DA_CONFDIR}/ca.dnsprovider"
		KEY=/home/runner/work/Admini/Admini/backend/conf/cakey.pem
		CERT=/home/runner/work/Admini/Admini/backend/conf/cacert.pem
		CACERT=/home/runner/work/Admini/Admini/backend/conf/carootcert.pem
	fi

	if [ -s "${CERT}" ] && [ "${action}" = "renew" ]; then
		if [ -s "${CERT}" ]; then
			DOMAIN=$(openssl x509 -text -noout -in "${CERT}" | grep -m1 'Subject Alternative Name:' -A1 | grep 'DNS:' | perl -p0 -e 's|DNS:||g' | tr -d ' ')
		fi
	elif [ "${action}" = "request" ] && ! echo "${DOMAIN}" | grep -m1 -q ","; then
		if [ -s "${CSR_CF_FILE}" ] && grep -m1 -q 'DNS:' "${CSR_CF_FILE}"; then
			DOMAIN=$(grep '^subjectAltName=' "${CSR_CF_FILE}" | cut -d= -f2 | grep 'DNS:' | perl -p0 -e 's|DNS:||g' | tr -d ' ')
		elif [ -s "${CERT}" ] && openssl x509 -text -noout -in "${CERT}" | grep -m1 -q 'Subject Alternative Name:' >/dev/null 2>&1; then
			DOMAIN=$(openssl x509 -text -noout -in "${CERT}" | grep -m1 'Subject Alternative Name:' -A1 | grep 'DNS:' | perl -p0 -e 's|DNS:||g' | tr -d ' ')
		elif [ "${HOSTNAME}" -eq 0 ] && ! ${CHILD_DOMAIN}; then
			if ! echo "${DOMAIN}" | grep -q "^www\."; then
				#We have a domain without www., add www domain to to SAN too
				DOMAIN="${DOMAIN},www.${DOMAIN}"
			else
				#We have a domain with www., drop www and add it to SAN too
				DOMAIN2=$(echo "${DOMAIN}" | perl -p0 -e 's#^www.##')
				DOMAIN="${DOMAIN2},www.${DOMAIN2}"
			fi
		fi
	fi

	#Set validation method
	CHALLENGETYPE=http
	#empty env for dnsprovider - but dnsprovider file in use
	if [ -s "${DNSPROVIDER_FALLBACK}" ] && [ -z "${dnsprovider}" ]; then
		readarray -t args < <(grep -o '^[a-zA-Z0-9_]*=[^;<>|\ ]*' "${DNSPROVIDER_FALLBACK}")
		export "${args[@]}"
	fi
	if [ "${HOSTNAME}" -ne 0 ]; then
		dnsprovider="$(da config-get acme_server_cert_dns_provider)"
	fi
	if [ -n "${dnsprovider}" ] && [ "${dnsprovider}" != "exec" ]; then
		echo "Found DNS provider configured: ${dnsprovider}"
		DNSPROVIDER_NAME=${dnsprovider}
		CHALLENGETYPE="dns"
	elif echo "${DOMAIN}" | grep -m1 -q '\*\.'; then
		echo "Found wildcard domain name and http challenge type, switching to dns-01 validation."
		DNSPROVIDER_NAME="exec"
		CHALLENGETYPE="dns"
		export EXEC_PATH=/home/runner/work/Admini/Admini/scripts/letsencrypt.sh
	fi
	if [ "${CHALLENGETYPE}" = "http" ]; then
		RESOLVING_DOMAINS=""
		for domain_name in $(echo "${DOMAIN}" | perl -p0 -e "s/,/ /g" | perl -p0 -e "s/^\*.//g"); do
			if has_webserver && ! challenge_check "${domain_name}" silent; then
				echo "${domain_name} was skipped due to unreachable http://${domain_name}/.well-known/acme-challenge/${TEMP_FILENAME} file."
			else
				if [ -z "${RESOLVING_DOMAINS}" ]; then
					RESOLVING_DOMAINS="${domain_name}"
				else
					RESOLVING_DOMAINS="${RESOLVING_DOMAINS},${domain_name}"
				fi
			fi
		done
		if [ -z "${RESOLVING_DOMAINS}" ]; then
			echo "No domains pointing to this server to generate the certificate for."
			exit 1
		fi
		DOMAIN="${RESOLVING_DOMAINS}"
	fi
	#Run all domains through CAA and http pre-checks to save LE rate-limits
	for domain_name in $(echo "${DOMAIN}" | perl -p0 -e "s/,/ /g" | perl -p0 -e "s/^\*.//g"); do
		caa_check "${domain_name}"
		if [ "${CHALLENGETYPE}" = "http" ] && has_webserver; then
			challenge_check "${domain_name}"
		fi
	done

	FIRST_DOMAIN=$(echo "${DOMAIN}" | cut -d, -f1)
	IFS=',' read -ra DOMAIN_ARRAY <<< "$DOMAIN"

	# extract the acme_provider=provider from domain configuration and determine the ACME url from that
	ACME=''
	domain_conf_file="${DA_USERDIR}/domains/${FIRST_DOMAIN}.conf"
	domain_ssl_file="${DA_USERDIR}/domains/${FIRST_DOMAIN}.ssl"

	if [ -s "${domain_conf_file}" ]; then
		ACME=$(grep -m1 ^acme_provider= "${domain_conf_file}" | cut -d= -f2)
	elif [ -s "${domain_ssl_file}" ]; then
		ACME=$(grep -m1 ^acme_provider= "${domain_ssl_file}" | cut -d= -f2)
	elif [ "${PARENT_DOMAIN_NAME_FOUND}" != "" ] && [ -s "${DA_USERDIR}/domains/${PARENT_DOMAIN_NAME_FOUND}.conf" ]; then
		# mail.domain.com, allowed host might not be a subdomain, no .ssl file for manual cert creation of mail.domin.com
		ACME=$(grep -m1 ^acme_provider= "${DA_USERDIR}/domains/${PARENT_DOMAIN_NAME_FOUND}.conf" | cut -d= -f2)
	fi

	if [ "${ACME}" = "" ]; then
		ACME=$(da config-get default_acme_provider)
	fi

	local challenge=""
	if [ "${CHALLENGETYPE}" = "dns" ]; then
		challenge=${DNSPROVIDER_NAME}
	fi
	if ! issue_lego_cert "${ACME}" "${KEY_SIZE}" "${challenge}" "${DOMAIN_ARRAY[@]}"; then
		echo "Failed to issue new certificate"
		exit 1
	fi
	if ! install_lego_cert "${FIRST_DOMAIN//\*/_}" "${KEY}" "${CERT}" "${CACERT}"; then
		echo "Failed to install new certificate"
		exit 1
	fi
	echo "Certificate for ${DOMAIN} has been created successfully!"

	#Change exim, apache/nginx certs
	if [ "${HOSTNAME}" -eq 1 ]; then
		echo "DirectAdmin certificate has been setup."
			if [ "$(da config-get ssl)" = "0" ]; then
			da config-set ssl 1
		fi

		systemctl restart directadmin
		da build sync_server_cert
	fi
}

command_add_dns_challenge_record() {
	local key=${1%%.}                     # Strip '.' suffix
	local value=$2
	local domain=${key##_acme-challenge.} # Strip '_acme-challenge.' prefix

	if [ -z "${domain}" ] || [ "${key}" = "${domain}" ]; then
		echo "refusing to create DNS challenge record '${key}', missing _acme-challenge prefix" 1>&2
		exit 1
	fi

	# cleanup of the old record
	# it's run in reverse because the list is sorted for duplicates.  Must run the dataskq immediately before calling the add.
	da taskq --run "action=dns&do=delete&domain=${domain}&type=TXT&name=_acme-challenge"
	da taskq --run "action=dns&do=add&domain=${domain}&type=TXT&name=_acme-challenge&value=\"${value}\"&ttl=5&named_reload=yes"
	exit 0
}

command_remove_dns_challenge_record() {
	local key=${1%%.}                     # Strip '.' suffix
	local domain=${key##_acme-challenge.} # Strip '_acme-challenge.' prefix

	if [ -z "${domain}" ] || [ "${key}" = "${domain}" ]; then
		echo "refusing to remove DNS challenge record '${key}', missing _acme-challenge prefix" 1>&2
		exit 1
	fi

	da taskq --run "action=dns&do=delete&domain=${domain}&type=TXT&name=_acme-challenge"
	exit 0
}

command_help() {
	echo "Usage:"
	echo "    $0 request|renew <domain> <key-type> [<csr-config-file>]"
	echo "    $0 server_cert [<domain>] [<key-type>]"
	echo "    $0 revoke"
	echo ""
	echo "Got $# args:"
	echo "    $0 $1 $2 $3 $4"
	echo ""
	echo "Multiple comma separated domains, owned by the same user, can be used for a certificate request"
	exit 0
}

case "$1" in
	server_cert)
		command_server_cert "${2}" "${3}"
		;;
	request|request_single|request_full)
		command_do_everything "request" "${2}" "${3}" "${4}"
		;;
	renew)
		command_do_everything "renew" "${2}" "${3}" "${4}"
		;;
	revoke)
		command_revoke "revoke" "${2}"
		;;
	present)
		# lego callback for DNS challenge
		# ./letsencrypt.sh "present" "_acme-challenge.foo.example.com." "MsijOYZxqyjGnFGwhjrhfg-Xgbl5r68WPda0J9EgqqI"
		command_add_dns_challenge_record "${2}" "${3}"
		;;
	cleanup)
		# lego callback for DNS challenge
		command_remove_dns_challenge_record "${2}"
		;;
	*)
		command_help "$@" ;;
esac
