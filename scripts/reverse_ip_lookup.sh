#!/bin/sh

if [ $# -ne 1 ]; then
	echo "Usage:";
	echo "  $0 <ip>";
	echo "";
	echo "where <ip> can be an IPv4 or IPv6 IP address.";
	exit 1;
fi

IP=$1

if [ "$IP" = "" ]; then
	echo "IP value blank is not";
fi

HAS_SHORT=1
COUNT=$(dig -h 2>&1 | grep -c '\[no\]short')
if [ "$COUNT" -eq 0 ]; then
	HAS_SHORT=0;
fi

if [ "$HAS_SHORT" -eq 1 ]; then
	dig -x "$IP" +short 2>&1
else
	dig -x "$IP" 2>&1 | grep PTR | awk '{ print $5 }'
fi
