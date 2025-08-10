#!/bin/sh

if [ $# -lt 1 ]; then
	echo "Usage:";
	echo "    $0 alpha"
	echo "    $0 beta"
	echo "    $0 current"
	echo "    $0 stable"
	echo "    $0 [commit-hash]"
	exit 0;
fi

if [ $# -gt 1 ]; then
	shift
fi

/usr/local/directadmin/directadmin update "$1"
