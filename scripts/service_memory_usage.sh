#!/bin/sh

ps axo comm,rss | awk '{arr[$1]+=$2+$4} END {for (i in arr) {print i "=" arr[i]/1024}}' | grep -v '=0$'
