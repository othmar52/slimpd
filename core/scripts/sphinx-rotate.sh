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
# FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
# details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

SCRIPT_PATH=$(cd $(dirname "${BASH_SOURCE[0]}") && pwd)
CONFIG="config.ini"
CONFIG_LOCAL="config_local.ini"

getConfigValue () {
	PARAMNAME=$1
	PARAMVALUE=""
	for C in $CONFIG $CONFIG_LOCAL; do
		PARAMVALUECHECK=$( awk -F '=' '{if (! ($0 ~ /^;/) && $0 ~ /'"$PARAMNAME"'/) print $2}' $SCRIPT_PATH/../config/$C )
		if [[ ! -z "$PARAMVALUECHECK" ]]; then PARAMVALUE=$PARAMVALUECHECK; fi
	done
	echo $PARAMVALUE
}

/usr/bin/indexer --rotate $( getConfigValue mainindex )

/usr/bin/indexer $( getConfigValue mainindex ) --buildstops $SCRIPT_PATH/../../localdata/cache/dict.txt 10000000 --buildfreqs
php $SCRIPT_PATH/../../slimpd builddictsql < $SCRIPT_PATH/../../localdata/cache/dict.txt > $SCRIPT_PATH/../../localdata/cache/dict.sql
mysql -u$( getConfigValue dbusername ) -p$( getConfigValue dbpassword ) $( getConfigValue dbdatabase ) < $SCRIPT_PATH/../../localdata/cache/dict.sql

rm $SCRIPT_PATH/../../localdata/cache/dict.txt
rm $SCRIPT_PATH/../../localdata/cache/dict.sql

/usr/bin/indexer --rotate $( getConfigValue suggestindex )

exit $?
