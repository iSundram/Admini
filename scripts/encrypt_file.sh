#!/bin/sh

if [ "$#" -ne 3 ]; then

        echo "Usage:";
        echo "  $0 <filein> <encryptedout> <passwordfile>"
		echo ""
        exit 1
fi

F=$1
E=$2
P=$3

if [ "${F}" = "" ] || [ ! -e "${F}" ]; then
	echo "Cannot find $F for encryption"
	exit 2;
fi

if [ "${E}" = "" ]; then
	echo "Please pass a destination path"
	exit 3;
fi

if [ "${P}" = "" ] || [ ! -s "${P}" ]; then
	echo "Cannot find passwordfile $P"
	exit 4
fi

openssl enc -e -aes-256-cbc -md sha256 -salt -in "$F" -out "$E" -pass "file:$P" 2>&1
