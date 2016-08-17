#!/usr/bin/env bash

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

/usr/bin/indexer $( getConfigValue mainindex ) --buildstops $SCRIPT_PATH/../cache/dict.txt 10000000 --buildfreqs
cat $SCRIPT_PATH/../cache/dict.txt | php $SCRIPT_PATH/../slimpd builddictsql > $SCRIPT_PATH/../cache/dict.sql
mysql -u$( getConfigValue dbusername ) -p$( getConfigValue dbpassword ) $( getConfigValue dbdatabase ) < $SCRIPT_PATH/../cache/dict.sql

rm $SCRIPT_PATH/../cache/dict.txt
rm $SCRIPT_PATH/../cache/dict.sql

/usr/bin/indexer --rotate $( getConfigValue suggestindex )

exit $?
