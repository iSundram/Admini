#!/bin/bash
DA_SHARED=/usr/local/directadmin/shared

if systemctl is-enabled NetworkManager.service -q >/dev/null 2>&1; then
	NM_DISP=/etc/NetworkManager/dispatcher.d
	if [ ! -f ${NM_DISP}/startips-networkmanager ] || ! diff --brief ${DA_SHARED}/startips-networkmanager ${NM_DISP}/startips-networkmanager > /dev/null; then
		cp -f ${DA_SHARED}/startips-networkmanager ${NM_DISP}/startips-networkmanager
		chown root:root                               ${NM_DISP}/startips-networkmanager
		chmod 755                                     ${NM_DISP}/startips-networkmanager
	fi
	# Cleanup other types
	rm -f /etc/networkd-dispatcher/routable.d/startips-networkd
	rm -f /etc/network/if-up.d/startips-networking
elif systemctl is-enabled networkd-dispatcher.service -q >/dev/null 2>&1; then
	SN_DISP=/etc/networkd-dispatcher/routable.d
	if [ ! -f ${SN_DISP}/startips-networkd ] || ! diff --brief ${DA_SHARED}/startips-networkd ${SN_DISP}/startips-networkd > /dev/null; then
		cp -f ${DA_SHARED}/startips-networkd ${SN_DISP}/startips-networkd
		chown root:root                         ${SN_DISP}/startips-networkd
		chmod 755                               ${SN_DISP}/startips-networkd
	fi
	# Cleanup other types
	rm -f /etc/NetworkManager/dispatcher.d/startips-networkmanager
	rm -f /etc/network/if-up.d/startips-networking
elif [ -d /etc/network/if-up.d ]; then
	IF_UP=/etc/network/if-up.d
	if [ ! -f ${IF_UP}/startips-networking ] || ! diff --brief ${DA_SHARED}/startips-networking ${IF_UP}/startips-networking > /dev/null; then
		cp -f ${DA_SHARED}/startips-networking ${IF_UP}/startips-networking
		chown root:root                           ${IF_UP}/startips-networking
		chmod 755                                 ${IF_UP}/startips-networking
	fi
	# Cleanup other types
	rm -f /etc/NetworkManager/dispatcher.d/startips-networkmanager
	rm -f /etc/networkd-dispatcher/routable.d/startips-networkd
else
	# Could not identify the active network hook dispatcher
	# Self cleaning possible scripts
	rm -f /etc/NetworkManager/dispatcher.d/startips-networkmanager
	rm -f /etc/networkd-dispatcher/routable.d/startips-networkd
	rm -f /etc/network/if-up.d/startips-networking
fi
