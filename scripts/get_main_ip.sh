#!/bin/sh
#Script to return the main useable device IP address of the box, used for main outbound connections.
#on a LAN, this should match your directadmin.conf lan_ip setting.
#for normal servers, this will likely return your license IP (usually)
#Will also be the default IP that exim sends email through.

ip_addr=$(ip route get to 8.8.8.8 2> /dev/null | grep -m 1 -o 'src [0-9.]*' | cut -d ' ' -f 2)
if [ -z "${ip_addr}" ]; then
	exit 1
fi
echo "${ip_addr}"
