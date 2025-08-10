#!/bin/sh

netstat_out() {
	netstat --numeric --tcp --udp | awk '/udp|tcp/ {split($5,p,/:/); print p[1]}'
}

show_ip_info() {
	I="$1"
	
	echo ""
	echo "Connection info for '${I}':"
	
	netstat -ntu | grep "${I}"
}

if command -v netstat > /dev/null; then
	echo "Connection counts:"
	netstat_out | sort | uniq --count | sort --numeric-sort | tail --lines=100
	
	echo ""

	#now take the IP with top connection count and get more info.
	C_IP=$(netstat_out | sort | uniq --count | sort --numeric-sort | tail --lines=1)
	C=$(echo "${C_IP}" | awk '{print $1}')
	IP=$(echo "${C_IP}" | awk '{print $2}')
	echo "IP '${IP}' currently has '${C}' connections"

	show_ip_info "${IP}"
fi

if command -v ss > /dev/null; then
	echo ""
	echo "ss command output:"
	ss -n
fi

CIP=/usr/local/directadmin/scripts/custom/connection_info_post.sh
if [ -x "${CIP}" ]; then
	"${CIP}"
fi

exit 0
