#!/bin/sh

ipv6_addr=$(ip route get to 2001:db8:: 2> /dev/null | grep -m 1 -o 'src [0-9a-f:]*' | cut -d ' ' -f 2)
if [ -z "${ipv6_addr}" ]; then
	exit 1
fi
echo "${ipv6_addr}"
