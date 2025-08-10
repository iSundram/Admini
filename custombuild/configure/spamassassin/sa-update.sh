#!/bin/sh
#VERSION=1.6

#Place this into a cron location, such as:
#/etc/cron.weekly/sa-update.sh
#and chmod to 700

LOG=/var/log/sa-update.log
PID=/var/run/spamd.pid

if [ -s ${LOG} ]; then
	if [ -e ${LOG}.2 ]; then
		rm -f ${LOG}.2
	fi
	if [ -e ${LOG}.1 ]; then
		mv -f ${LOG}.1 ${LOG}.2
	fi
	mv -f ${LOG} ${LOG}.1
fi 

/usr/bin/sa-update -D --nogpg --channel updates.spamassassin.org > ${LOG} 2>&1

RET=$?

if [ "$RET" -ge 4 ]; then
	echo "Error updating SpamAssassin Rules. Code=$RET"
	echo ""
	cat $LOG
else
	systemctl stop spamassassin.service >> ${LOG} 2>&1

	if [ -s $PID ]; then
		kill -9 "$(cat $PID)" > /dev/null 2>&1
	else
		pkill -x -9 spamd >/dev/null 2>&1
	fi

	systemctl start spamassassin.service >> ${LOG} 2>&1
fi

if [ "$RET" -eq 1 ]; then
	RET=0
fi

exit $RET

