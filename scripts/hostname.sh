#!/bin/bash

if [ $# -lt "1" ] || [ "$1" = '-h' ] || [ "$1" = '--help' ]; then
	echo "Usage: $0 <hostname>";
	exit 1;
fi

curl --silent --insecure --fail --show-error --request POST "$(da api-url)/api/server-settings/change-hostname" --data-raw "{\"hostname\":\"$1\"}"
