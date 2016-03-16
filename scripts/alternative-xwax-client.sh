#!/usr/bin/env bash

#
# example script to execute anything else in case specific commands get fired
#

XWAX_CLIENT=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../vendor-dist/othmar52/xwax-1.5-osc/xwax-client"

IP=$1
CMD=$2
DECK=$3
ARG1=$4
ARG2=$5
ARG3=$6

# pass supported commands to real xwax client
case "$CMD" in
	reconnect|disconnet|recue|get_status|load_track)
		timeout 2 $XWAX_CLIENT "$@"
		;;
esac
	
case "$CMD" in
	launch)
		echo "ERROR"
		echo "insert your start command for xwax-launch here ${BASH_SOURCE[0]}"
		;;
	exit)
		echo "ERROR"
		echo "insert your exit command for xwax-launch here ${BASH_SOURCE[0]}"
		;;
	load_track)
		echo "calling load_track deck=$DECK, track=$ARG1"
		;;
esac