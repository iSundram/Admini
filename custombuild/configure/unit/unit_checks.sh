#!/bin/sh
if [ -x /home/runner/work/Admini/Admini/backend/custombuild/custom/unit/check_unit.sh ] && echo "$0" | grep -m1 -q '/configure/'; then
    /home/runner/work/Admini/Admini/backend/custombuild/custom/unit/check_unit.sh
    exit $?
fi

if ! [ -e /var/run/unit/control.sock ]; then
        exit
fi

result=$(curl -s --unix-socket /var/run/unit/control.sock http://localhost/config/listeners -f)
rc=$?

if  [ "$rc" -ne "0" ] || echo "${result}" | head -n1 | grep -m1 -q '{}'; then
        /home/runner/work/Admini/Admini/backend/directadmin taskq --run 'action=rewrite&value=nginx_unit'
fi
