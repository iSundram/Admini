#!/bin/bash

DA=/home/runner/work/Admini/Admini/backend/directadmin
if [ ! -s ${DA} ]; then
	echo "Cannot find DirectAdmin binary:";
	echo "  ${DA}";
	exit 1;
fi

DA_CONF=/home/runner/work/Admini/Admini/backend/conf/directadmin.conf
if [ ! -s ${DA_CONF} ]; then
	echo "Cannot find DirectAdmin Config File:";
	echo "  ${DA_CONF}";
	exit 2;
fi

KEY_BIT_SIZE=2048
KEY_BIT_SIZE_CONF=$(${DA} c | grep '^dnssec_keygen_keysize=' | cut -d= -f2)
if [ -n "${KEY_BIT_SIZE_CONF}" ]; then
	KEY_BIT_SIZE=${KEY_BIT_SIZE_CONF}
fi

BIND_PATH=/etc

if [ -e /etc/debian_version ]; then
	BIND_PATH=/etc/bind
fi

NAMED_PATH=$(${DA} c | grep ^nameddir= | cut -d= -f2 2>/dev/null)
if [ "${NAMED_PATH}" = "" ]; then
	echo "Cannot find nameddir from:";
	echo "${DA} c | grep ^nameddir=";
	exit 3;
fi
DNSSEC_KEYS_PATH=${NAMED_PATH}

NAMED_CONF=${BIND_PATH}/named.conf
NAMED_CONF=$(${DA} c | grep namedconfig= | cut -d= -f2)

if [ -e /etc/debian_version ] && [ -e /etc/bind/named.conf.options ]; then
	 NAMED_CONF=/etc/bind/named.conf.options
fi

if ! command -v named > /dev/null; then
	echo "Cannot find named";
	exit 4;
fi

NAMED_VER=$(named -v | cut -d\  -f2 | cut -d- -f1 | cut -d. -f1,2)

if ! command -v dnssec-keygen > /dev/null; then
	echo "Cannot find dnssec-keygen. Please install dnssec tools";
	exit 12;
fi

DNSSEC_RANDOMDEV=''
if dnssec-keygen -h 2>&1 | grep -q ' -r '; then
	DNSSEC_RANDOMDEV='-r /dev/urandom'
fi

ENC_TYPE=RSASHA256
ENC_TYPE_CONF=$(${DA} c | grep '^dnssec_keygen_algorithm=' | cut -d= -f2)
if [ -n "${ENC_TYPE_CONF}" ] && dnssec-keygen -h 2>&1 | grep -q "${ENC_TYPE_CONF}"; then
	ENC_TYPE=${ENC_TYPE_CONF}
elif dnssec-keygen -h 2>&1 | grep -q ECDSAP256SHA256; then
	ENC_TYPE=ECDSAP256SHA256
fi

if ! command -v dnssec-signzone > /dev/null; then
	echo "Cannot find dnssec-signzone. Please install dnssec tools";
	exit 13;
fi
HAS_SOA_FORMAT=0
if dnssec-signzone -h 2>&1 | grep -q '\-N format:'; then
	HAS_SOA_FORMAT=1
fi

SATZ=skip-add-to-zone
show_help()
{
	echo "Usage:";
	echo "  $0 keygen <domain>"; # [${SATZ}]";
	echo "  $0 sign <domain>";
	echo "";
	echo "The ${SATZ} option will create the keys, but will not trigger the dataskq to add the keys to the zone.";
	echo "";
	exit 1;
}

if [ $# = 0 ]; then
	show_help;
fi

##################################################################################################################################################
#
# Key Gen Code
#

ensure_domain()
{
	DOMAIN=$1
	
	if [ "${DOMAIN}" = "" ]; then
		echo "Missing Domain";
		show_help;
	fi
	
	#check for valid domain
	DB_FILE=${NAMED_PATH}/${DOMAIN}.db
	if [ ! -s "${DB_FILE}" ]; then
		echo "Cannot find valid zone at ${DB_FILE}";
		exit 10;
	fi
}

ensure_keys_path()
{
	if [ ! -d ${DNSSEC_KEYS_PATH} ]; then
		mkdir ${DNSSEC_KEYS_PATH};
	fi
	
	if [ ! -d ${DNSSEC_KEYS_PATH} ]; then
		echo "Cannot find directory ${DNSSEC_KEYS_PATH}";
		exit 11;
	fi
}

do_keygen()
{
	DOMAIN=$1;
	
	ensure_domain "${DOMAIN}";
	ensure_keys_path;
	DB_FILE=${NAMED_PATH}/${DOMAIN}.db

	echo "Starting keygen process for $DOMAIN";

	cd ${DNSSEC_KEYS_PATH};

	#ZSK
	KEY_STR=`dnssec-keygen ${DNSSEC_RANDOMDEV} -a $ENC_TYPE -b ${KEY_BIT_SIZE} -n ZONE ${DOMAIN}`
	
	K=${KEY_STR}.key
	P=${KEY_STR}.private
	if [ ! -s $K ] || [ ! -s $P ]; then
		echo "Cannot find ${DNSSEC_KEYS_PATH}/${K} or ${DNSSEC_KEYS_PATH}/${P}";
		exit 14;
	fi
	mv -f $K ${DOMAIN}.zsk.key
	mv -f $P ${DOMAIN}.zsk.private

	
	#KSK	
	KEY_STR=`dnssec-keygen ${DNSSEC_RANDOMDEV} -a $ENC_TYPE -b ${KEY_BIT_SIZE} -n ZONE -f KSK ${DOMAIN}`
	RET=$?
	
	K=${KEY_STR}.key
	P=${KEY_STR}.private
	if [ ! -s $K ] || [ ! -s $P ]; then
		echo "Cannot find ${DNSSEC_KEYS_PATH}/${K} or ${DNSSEC_KEYS_PATH}/${P}";
		exit 15;
	fi
	mv -f $K ${DOMAIN}.ksk.key
	mv -f $P ${DOMAIN}.ksk.private

	echo "${DOMAIN} now has keys.";
	
	exit $RET;
}

#
# End Key Gen Code
#
##################################################################################################################################################
#
# Signing Code
#

do_sign()
{
	DOMAIN=$1;
	
	ensure_domain "${DOMAIN}";
	ensure_keys_path;
	DB_FILE=${NAMED_PATH}/${DOMAIN}.db

	echo "Starting signing process for $DOMAIN";
	
	cd ${DNSSEC_KEYS_PATH};

	ZSK=${DOMAIN}.zsk.key
	KSK=${DOMAIN}.ksk.key
	
	if [ ! -s ${ZSK} ] || [ ! -s ${KSK} ]; then
		echo "Cannot find ${ZSK} or ${KSK}";
		exit 16;
	fi

	#first, create a copy of the zone to work with.
	T=${DB_FILE}.dnssec_temp
	cat ${DB_FILE} > ${T}
	
	#add the key includes
	echo "\$include ${DNSSEC_KEYS_PATH}/${DOMAIN}.zsk.key;" >> ${T};
	echo "\$include ${DNSSEC_KEYS_PATH}/${DOMAIN}.ksk.key;" >> ${T};

	N_INC="-N INCREMENT"
	if [ "${HAS_SOA_FORMAT}" -eq 0 ]; then
		N_INC=""
	fi	

	dnssec-signzone ${DNSSEC_RANDOMDEV} -n 1 -e +3024000 ${N_INC} -o ${DOMAIN} -k ${KSK} ${T} ${ZSK}
	RET=$?
	
	rm -f ${T}
	if [ -s ${T}.signed ]; then
		mv -f ${T}.signed ${DB_FILE}.signed
	else
		if [ "$RET" -eq 0 ]; then
			echo "cannot find ${T}.signed to rename to ${DB_FILE}.signed";
		fi
	fi
	
	exit $RET;
}

#
# End Signing Code
#
##################################################################################################################################################





case "$1" in
	install)	exit 0;
			;;
	keygen)		do_keygen "$2" "$3";
			;;
	sign)		do_sign "$2";
			;;
	*)		show_help;
			;;
esac
