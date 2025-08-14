#!/bin/sh

DA_PATH=/usr/local/directadmin
DA_CONFIG=${DA_PATH}/conf/directadmin.conf
SERVICES_STATUS=${DA_PATH}/data/admin/services.status

path_to_custom_script() {
	main_file="${DA_PATH}/scripts/$1" # $1=directadmin.rotate
	custom_file="${DA_PATH}/scripts/custom/$1"
	if [ -e "${custom_file}" ]; then
		echo "${custom_file}"
	else
		echo "${main_file}"
	fi
}

# DA 1.646 systemd only
if [ -f /etc/init.d/directadmin ]; then
	cp -f ${DA_PATH}/scripts/directadmin.service /etc/systemd/system/directadmin.service
	rm -f /etc/init.d/directadmin
	systemctl daemon-reload
	systemctl enable directadmin.service
elif ! diff --brief ${DA_PATH}/scripts/directadmin.service /etc/systemd/system/directadmin.service > /dev/null; then
	cp -f ${DA_PATH}/scripts/directadmin.service /etc/systemd/system/directadmin.service
	systemctl daemon-reload
fi

if [ ! -s /etc/systemd/system/directadmin-userd@.service ] || ! diff --brief ${DA_PATH}/scripts/directadmin-userd@.service /etc/systemd/system/directadmin-userd@.service > /dev/null; then
	cp -f ${DA_PATH}/scripts/directadmin-userd@.service /etc/systemd/system/directadmin-userd@.service
	systemctl daemon-reload
fi

if [ ! -s /etc/systemd/system/directadmin-userd@.socket ] || ! diff --brief ${DA_PATH}/scripts/directadmin-userd@.socket /etc/systemd/system/directadmin-userd@.socket > /dev/null; then
	cp -f ${DA_PATH}/scripts/directadmin-userd@.socket /etc/systemd/system/directadmin-userd@.socket
	systemctl daemon-reload
fi 

if ! diff --brief ${DA_PATH}/scripts/startips.service /etc/systemd/system/startips.service > /dev/null; then
	cp -f     ${DA_PATH}/scripts/startips.service /etc/systemd/system/startips.service
	chmod 644                                     /etc/systemd/system/startips.service
	systemctl daemon-reload
fi

${DA_PATH}/scripts/startips-installer.sh

if ! diff --brief ${DA_PATH}/scripts/directadmin_cron /etc/cron.d/directadmin_cron > /dev/null; then
	cp -f ${DA_PATH}/scripts/directadmin_cron /etc/cron.d/directadmin_cron
	chmod 600 /etc/cron.d/directadmin_cron
	chown root /etc/cron.d/directadmin_cron
fi

if [ -e /etc/logrotate.d ] && ! diff --brief "$(path_to_custom_script directadmin.rotate)" /etc/logrotate.d/directadmin > /dev/null; then
	cp -f "$(path_to_custom_script directadmin.rotate)" /etc/logrotate.d/directadmin
	chmod 644 /etc/logrotate.d/directadmin
fi

#Set permissions with current DA version.
${DA_PATH}/directadmin permissions

{
	echo "action=cache&value=showallusers"
	echo "action=cache&value=safemode"
	echo "action=convert&value=cronbackups"
	echo "action=syscheck"

	# Do we really need them?
	#DA 1.56.2
	#https://www.directadmin.com/features.php?id=2332
	echo 'action=rewrite&value=cron_path'

	# rewrite jail configs to be compatible with old MSMTP
	# DA v.1.653
	echo "action=rewrite&value=jail"
} >> ${DA_PATH}/data/task.queue

#Allow all TCP/UDP outbound connections from root
if [ -e /etc/csf/csf.allow ] && [ -x /usr/sbin/csf ]; then
	if ! grep -q 'out|u=0' /etc/csf/csf.allow; then
		/usr/sbin/csf -a "tcp|out|u=0" "Added by DirectAdmin"
		/usr/sbin/csf -a "udp|out|u=0" "Added by DirectAdmin"
	fi
fi

# DA 1.63.5 remove directadmin from services.status list
if grep -q -s '^directadmin=' ${SERVICES_STATUS}; then
	sed -i '/^directadmin=/d' ${SERVICES_STATUS}
fi

# DA 1.641 remove old system DB file
if [ -s "${DA_PATH}/data/admin/da.db" ]; then
	rm -f "${DA_PATH}/data/admin/da.db"
fi

# DA 1.643 replace relative tmpdir config option to absolute
# old:
#     tmpdir=../../../home/tmp
# new:
#     tmpdir=/home/tmp
if grep -q '^tmpdir=\.\./\.\./\.\./' ${DA_CONFIG}; then
	sed -i 's|^tmpdir=\.\./\.\./\.\./|tmpdir=/|' ${DA_CONFIG}
fi

# DA 1.643 unify Evolution custom translations structure by removing language
# directories. This make sure files `.../lang/{xx}/custom/lang.po` are moved
# to `../lang/custom/{xx}.po`.
EVO_LANGS=${DA_PATH}/data/skins/evolution/lang
find "${EVO_LANGS}" -path '*/custom/lang.po' -printf "%P\n" | while read -r file; do
	xx=${file%/custom/lang.po}
	if [ "${xx#*/}" != "${xx}" ]; then
		# Ignore if {xx} contains `/` symbols
		continue
	fi
	mkdir -p "${EVO_LANGS}/custom"
	mv "${EVO_LANGS}/${file}" "${EVO_LANGS}/custom/${xx}.po"
done

if [ -f ${DA_PATH}/custombuild/options.conf ]; then
	# DA 1.644 force CB cron handler to upgrade crontab-file
	${DA_PATH}/directadmin build cron > /dev/null 2> /dev/null || true

	# Add depreciation checks
	${DA_PATH}/directadmin build deprecation_check > /dev/null 2> /dev/null || true
fi

# DA 1.645 run custombuild cronjob from binary
rm -f /etc/cron.daily/custombuild
rm -f /etc/cron.weekly/custombuild
rm -f /etc/cron.monthly/custombuild

# DA 1.645 allow CB to run post-install tasks
${DA_PATH}/directadmin build install

# DA 1.646 drop /etc/virtual/pophosts
rm -f /etc/virtual/pophosts
rm -f /etc/virtual/pophosts_user

# DA 1.647 remove old CustomBuild plugin
if [ -d "${DA_PATH}/plugins/custombuild" ]; then
	rm -rf "${DA_PATH}/plugins/custombuild"
	if getent passwd cb_plugin > /dev/null; then
		userdel cb_plugin
	fi
fi

# DA 1.649 remove da-popb4smtp service
if grep -q -s '^da-popb4smtp=' "${SERVICES_STATUS}"; then
	sed -i '/^da-popb4smtp=/d' "${SERVICES_STATUS}"
fi
if [ -f /etc/systemd/system/da-popb4smtp.service ] || [ -f /etc/rc.d/init.d/da-popb4smtp ]; then
	systemctl --quiet disable --now da-popb4smtp.service
	rm -f /etc/systemd/system/da-popb4smtp.service /etc/rc.d/init.d/da-popb4smtp
	systemctl daemon-reload
fi

# DA 1.653 remove cluster_ip_bind from config if it is set to NULL (case
# insensitive).
if grep -i -q '^cluster_ip_bind=null$' ${DA_CONFIG}; then
	sed -i '/^cluster_ip_bind=/d' ${DA_CONFIG}
fi

# DA 1.659 move data/templates/mx/custom -> data/templates/custom/mx
if [ -d ${DA_PATH}/data/templates/mx/custom ] && [ ! -e ${DA_PATH}/data/templates/custom/mx ]; then
	mkdir -p ${DA_PATH}/data/templates/custom
	chown diradmin:diradmin ${DA_PATH}/data/templates/custom
	mv -f -T ${DA_PATH}/data/templates/mx/custom ${DA_PATH}/data/templates/custom/mx
fi

# DA 1.659 remove vm-pop3d service
if grep -q -s '^vm-pop3d=' "${SERVICES_STATUS}"; then
	sed -i '/^vm-pop3d=/d' "${SERVICES_STATUS}"
fi

# DA 1.659 replace /root/.zerossl with directadmin.conf setting
if [ -f /root/.zerossl ]; then
	${DA_PATH}/directadmin config-set default_acme_provider zerossl
	rm -f /root/.zerossl
fi

# DA 1.659 move scripts/setup.txt -> conf/setup.txt
if [ -f "${DA_PATH}/scripts/setup.txt" ] && [ ! -L "${DA_PATH}/scripts/setup.txt" ]; then
	mv --no-target-directory "${DA_PATH}/scripts/setup.txt" "${DA_PATH}/conf/setup.txt"
	chmod 600 "${DA_PATH}/conf/setup.txt"
	ln -s "${DA_PATH}/conf/setup.txt" "${DA_PATH}/scripts/setup.txt"
fi

# DA 1.659 rework password check script
if grep -q '^enforce_difficult_passwords=1$' "${DA_CONFIG}" && \
	! grep -q '^password_check_script=' "${DA_CONFIG}" && \
	[ -f "${DA_PATH}/scripts/custom/difficult_password.php" ]
then
	${DA_PATH}/directadmin config-set password_check_script scripts/custom/difficult_password.php
	${DA_PATH}/directadmin config-set enforce_difficult_passwords 0
fi

# DA 1.664 rework server tls dns provider config
ACME_SERVER_CERT_DNS_PROVIDER="$(grep -om1 '^dnsprovider=[^;<>|\ ]*' /usr/local/directadmin/conf/ca.dnsprovider 2>/dev/null | cut -d= -f2)"
if [ -n "${ACME_SERVER_CERT_DNS_PROVIDER}" ]; then
	${DA_PATH}/directadmin config-set acme_server_cert_dns_provider "${ACME_SERVER_CERT_DNS_PROVIDER}"
	sed -i '/^dnsprovider=/d' /usr/local/directadmin/conf/ca.dnsprovider
fi
if [ "$(da config-get letsencrypt)" = 1 ] && [ -f "/usr/local/directadmin/conf/cacert.pem.creation_time" ]; then
	ADDITIONAL_DOMAINS="$(openssl x509 -in "/usr/local/directadmin/conf/cacert.pem" -noout -ext subjectAltName 2>/dev/null | grep -Po '(?<=DNS:)[^,]*' | grep -Fvx "$(da config-get servername)" | paste -sd,)"
	if [ -n "${ADDITIONAL_DOMAINS}" ]; then
		${DA_PATH}/directadmin config-set acme_server_cert_additional_domains "${ADDITIONAL_DOMAINS}"
	fi
	${DA_PATH}/directadmin config-set acme_server_cert_enabled 1
	rm -f "/usr/local/directadmin/conf/cacert.pem.creation_time"
fi

# DA 1.669 move custom FTP and email password change templates to correct location
if [ -s ${DA_PATH}/data/templates/email_pass_change/custom/index.html ] && [ ! -e "${DA_PATH}/data/templates/custom/email_pass_change/index.html" ]; then
	mkdir -p ${DA_PATH}/data/templates/custom/email_pass_change
	cp ${DA_PATH}/data/templates/email_pass_change/custom/index.html ${DA_PATH}/data/templates/custom/email_pass_change/index.html
fi
if [ -s ${DA_PATH}/data/templates/ftp_pass_change/custom/index.html ] && [ ! -e "${DA_PATH}/data/templates/custom/ftp_pass_change/index.html" ]; then
	mkdir -p ${DA_PATH}/data/templates/custom/ftp_pass_change
	cp ${DA_PATH}/data/templates/ftp_pass_change/custom/index.html ${DA_PATH}/data/templates/custom/ftp_pass_change/index.html
fi

# DA 1.674 move default access hosts from mysql.conf into directadmin.conf
if grep -sq '^access_host.*=' "${DA_PATH}/conf/mysql.conf"; then
	da config-set db_default_access_hosts "$(grep '^access_host.*=' "${DA_PATH}/conf/mysql.conf" | cut -d= -f2 | paste -sd,)"
	sed -i '/^access_host.*=/d' "${DA_PATH}/conf/mysql.conf"
fi

# DA 1.674 move admin.conf fields to directadmin.conf
if grep -sq '^auto_update=' "${DA_PATH}/data/admin/admin.conf"; then
	if grep -sq '^auto_update=no' "${DA_PATH}/data/admin/admin.conf"; then
		da config-set allow_push_autoupdate 0
	fi
	sed -i '/^auto_update=/d' "${DA_PATH}/data/admin/admin.conf"
fi
if grep -sq '^oversell=' "${DA_PATH}/data/admin/admin.conf"; then
	if grep -sq '^oversell=no' "${DA_PATH}/data/admin/admin.conf"; then
		da config-set allow_reseller_oversell 0
	fi
	sed -i '/^oversell=/d' "${DA_PATH}/data/admin/admin.conf"
fi
if grep -sq '^service_email_active=' "${DA_PATH}/data/admin/admin.conf"; then
	if grep -sq '^service_email_active=no' "${DA_PATH}/data/admin/admin.conf"; then
		da config-set notify_admins_down_services 0
	fi
	sed -i '/^service_email_active=/d' "${DA_PATH}/data/admin/admin.conf"
fi
if grep -sq '^suspend=' "${DA_PATH}/data/admin/admin.conf"; then
	if grep -sq '^suspend=no' "${DA_PATH}/data/admin/admin.conf"; then
		da config-set suspend_reseller_on_overuse 0
	fi
	sed -i '/^suspend=/d' "${DA_PATH}/data/admin/admin.conf"
fi
if grep -sq '^user_backup=' "${DA_PATH}/data/admin/admin.conf"; then
	if grep -sq '^user_backup=no' "${DA_PATH}/data/admin/admin.conf"; then
		da config-set allow_reseller_to_backup_users 0
	fi
	sed -i '/^user_backup=/d' "${DA_PATH}/data/admin/admin.conf"
fi
if grep -sq '^backup_threshold=' "${DA_PATH}/data/admin/admin.conf"; then
	USER_BACKUPS_DISK_THRESHOLD="$(grep -s '^backup_threshold=' "${DA_PATH}/data/admin/admin.conf" | cut -d= -f2)"
	if [ "${USER_BACKUPS_DISK_THRESHOLD}" -ne 90 ] ; then
		da config-set user_backups_disk_threshold "${USER_BACKUPS_DISK_THRESHOLD}"
	fi
	sed -i '/^backup_threshold=/d' "${DA_PATH}/data/admin/admin.conf"
fi

# DA 1.674 cleanup unused admin.conf fields
if grep -sqE '^admin_widgets=|^demo_(user|reseller|admin)=' "${DA_PATH}/data/admin/admin.conf"; then
	sed -i -E '/^admin_widgets=/d; /^demo_(user|reseller|admin)=/d' "${DA_PATH}/data/admin/admin.conf"
fi

# DA 1.674 clean directadmin.conf fields with special empty string treatment
grep -s -F -x \
	-e "addip=" \
	-e "admindir=" \
	-e "admin_ssl_poll_frequency=" \
	-e "allowed_hook_upper_case_env_vars=" \
	-e "apacheca=" \
	-e "apachecert=" \
	-e "apacheconf=" \
	-e "apacheips=" \
	-e "apachekey=" \
	-e "apachelogdir=" \
	-e "apachemimetypes=" \
	-e "apache_pid=" \
	-e "chpasswd=" \
	-e "curl=" \
	-e "custom_stats_path=" \
	-e "da_website=" \
	-e "default_acme_provider=" \
	-e "dig=" \
	-e "dkim_selector=" \
	-e "domainips_default_ip=" \
	-e "emailspoolvirtual=" \
	-e "emailvirtual=" \
	-e "extra_spf_value=" \
	-e "extra_unzip_option=" \
	-e "force_pipe_post=" \
	-e "ftpconfig=" \
	-e "ftppasswd=" \
	-e "ftppasswd_db=" \
	-e "groupadd=" \
	-e "language=" \
	-e "language_list=" \
	-e "letsencrypt_background_default=" \
	-e "letsencrypt_list=" \
	-e "letsencrypt_list_selected=" \
	-e "logdir=" \
	-e "mail_partition="  \
	-e "name=" \
	-e "named_checkzone_level=" \
	-e "named_service_override=" \
	-e "nginx_ca=" \
	-e "nginx_cert=" \
	-e "nginxconf=" \
	-e "nginxips=" \
	-e "nginx_key=" \
	-e "nginxlogdir=" \
	-e "nginx_pid=" \
	-e "one_click_webmail_link=" \
	-e "openlitespeed_ca=" \
	-e "openlitespeed_cert=" \
	-e "openlitespeed_ips_conf=" \
	-e "openlitespeed_key=" \
	-e "openlitespeed_listeners=" \
	-e "openlitespeed_vhosts_conf=" \
	-e "openssl=" \
	-e "password_check_script=" \
	-e "php_mail_log_dir=" \
	-e "pigz_bin=" \
	-e "pureftp_log=" \
	-e "pure_pw=" \
	-e "quota=" \
	-e "quota_partition=" \
	-e "referrer_policy=" \
	-e "removeip=" \
	-e "repquota=" \
	-e "reserved_env_vars=" \
	-e "secure_disposal=" \
	-e "serverpath=" \
	-e "setquota=" \
	-e "show_all_users_cache_extra_vars=" \
	-e "skinsdir=" \
	-e "sshdconfig=" \
	-e "taskqueue=" \
	-e "templates=" \
	-e "ticketsdir=" \
	-e "unpigz_bin=" \
	-e "useradd=" \
	-e "userdata=" \
	-e "userdel=" \
	-e "usermod=" \
	-e "xfs_quota=" \
	-e "zstd_bin=" \
	"${DA_CONFIG}" | while read -r i; do
	sed -i -e "/^${i}$/d" "${DA_CONFIG}"
done

# DA 1.674 migrate letsencrypt renewal days in directadmin.conf
if grep -s -q '^letsencrypt_renewal_days=' ${DA_CONFIG}; then
	old=$(grep '^letsencrypt_renewal_days=' "${DA_CONFIG}" | cut -d'=' -f2)
	if [ -n "${old}" ] && [ "${old}" -ne 60 ] && [ "${old}" -lt 90 ]; then
		da config-set letsencrypt_renew_before_expiry_days "$((90 - old))"
	fi
	sed -i '/^letsencrypt_renewal_days=/d' "${DA_CONFIG}"
fi

# DA 1.677 proftpd vhosts are no longer created but might still be included
if [ -s /etc/proftpd.vhosts.conf ]; then
	: > /etc/proftpd.vhosts.conf
fi

# DA 1.680 unify main TLS cert location
for k in cakey cacert carootcert; do
	if ! grep -s -q "^${k}=" "${DA_CONFIG}"; then
		continue
	fi
	src=$(grep "^${k}=" "${DA_CONFIG}" | cut -d = -f 2-)
	dst="/usr/local/directadmin/conf/${k}.pem"
	if [ -f "${src}" ] && [ "${src}" != "${dst}" ]; then
		cp --force --no-target-directory "${src}" "${dst}"
	fi
	sed -i "/^${k}=/d" "${DA_CONFIG}"
done
