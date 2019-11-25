#!/bin/bash

# $1 modem number

case "$1" in
	1)
		echo "switch to modem 1"
		route delete default; route add default gw 192.168.1.1
		iptables -F -t nat
		iptables -t nat -A POSTROUTING -o eth1 -j SNAT --to-source 192.168.1.100
	;;

	2)
		echo "switch to modem 2";
		ifconfig eth4 192.168.8.2
                route delete default; route add default gw 192.168.8.1
                iptables -F -t nat
                iptables -t nat -A POSTROUTING -o eth4 -j SNAT --to-source 192.168.8.2
	;;
	
	current)
		CURR_IP=`route -n | head -n 3 | tail -n 1 | awk '{print $2}'`
                [ "$CURR_IP" = "192.168.1.1" ] && echo "current modem: 1" && exit 1
		[ "$CURR_IP" = "192.168.8.1" ] && echo "current modem: 2" && exit 2
                echo "current modem: unknown"
		exit -1
	;;

	*)
		echo "Incorrect modem number"
	;;
esac
