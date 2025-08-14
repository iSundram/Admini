#!/bin/sh
if [ -x /usr/local/directadmin/custombuild/custom/unit/check_unit.sh ] && echo "$0" | grep -m1 -q '/configure/'; then
    /usr/local/directadmin/custombuild/custom/unit/check_unit.sh
    exit $?
fi

if ! [ -e /var/run/unit/control.sock ]; then
        exit
fi

result=$(curl -s --unix-socket /var/run/unit/control.sock http://localhost/config/listeners -f)
rc=$?

if  [ "$rc" -ne "0" ] || echo "${result}" | head -n1 | grep -m1 -q '{}'; then
        /usr/local/directadmin/directadmin taskq --run 'action=rewrite&value=nginx_unit'
fi
