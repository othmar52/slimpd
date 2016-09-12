#!/usr/bin/env bash

# Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
#
# This file is part of sliMpd - a php based mpd web client
#
# This program is free software: you can redistribute it and/or modify it under
# the terms of the GNU Affero General Public License as published by the Free
# Software Foundation, either version 3 of the License, or (at your option) any
# later version.
#
# This program is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
# FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
# details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

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
		echo "calling load_track ip=$IP, deck=$DECK, track=$ARG1, artist=$ARG2, title=$ARG3"
		;;
esac
