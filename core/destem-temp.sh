#!/bin/env sh

# webbrowser is not able to handle 3 hour stems with 7 streams
# i did not have problems with 30 minute stems containing 7 streams
# a value of 0 will not split
# values unit is seconds
# TODO: move configuration to config_local and parse it
maxStemDuration=1800 # 30 minutes
#maxStemDuration=15


if [ -z "$1" ]; then
	echo "Usage:";
	echo " destem targetDirectoryECTORY INPUTFILE";
	exit 1
fi

targetDirectory="$1"
fileToDestem="$2"

if [ ! -d "$targetDirectory" ]; then
	echo "ERROR: directory '$targetDirectory' does not exist"
	exit 1
fi

if [ ! -f "$fileToDestem" ]; then
	echo "ERROR: file '$fileToDestem' does not exist"
	exit 1
fi


# check if we have to split files based on configuration
splitted=0
filesToProcess=("$fileToDestem")
cmd=(-v error -select_streams a:0 -show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 "$fileToDestem")
duration=$( ffprobe "${cmd[@]}" )
if [[ $(echo " $duration > $maxStemDuration" | bc) -eq 1 ]] && [[ $maxStemDuration -gt 0 ]]
then
	echo "splitting input file into $maxStemDuration second chunks"
    ffmpeg -y -loglevel panic -i "$fileToDestem" -f segment -segment_time $maxStemDuration -c copy -map 0 -reset_timestamps 1 "$targetDirectory/_temp.stem.%01d.mp4"
    splitted=1
    filesToProcess=( $(find "$targetDirectory/_temp.stem"*) )
fi


if [[ "${#filesToProcess[@]}" -eq 0 ]]
then
	echo "ERROR: something went wrong"
	exit 
fi

echo "destemming" > "$targetDirectory/status"

COUNT_STREAMS=$( ffprobe -i "${filesToProcess[0]}" -show_streams -select_streams a -loglevel panic | grep index | wc -l )
((COUNT_STREAMS--))

chunkCounter=0

for fileToProcess in "${filesToProcess[@]}"
do

	echo "Destemming `basename "$fileToProcess"` ..."
	mkdir mkdir "$targetDirectory/$chunkCounter"
	echo "destemming" > "$targetDirectory/$chunkCounter/status"
	# TODO: make skipping of stream 0 configurable
	echo "skipping stream 0..."
	for IDX in $(seq 1 $COUNT_STREAMS)
	do
		
		echo " extracting stream $IDX"
		cmd=(-y)
		#cmd+=(-loglevel panic)
		cmd+=(-i "$fileToProcess")
		cmd+=(-ar 44100 -ac 2 -ab 192k -f mp3)
		cmd+=(-map 0:$IDX -map_metadata 0)
		cmd+=(-id3v2_version 3 -write_id3v1 1)
		cmd+=("$targetDirectory/$chunkCounter/""`basename "${fileToDestem%.*}" `.$IDX.mp3" )
		ffmpeg "${cmd[@]}" || { exit 1; }
		# print path of new generated stem tracks
		echo "newStemTrack: $targetDirectory/$chunkCounter/""`basename "${fileToDestem%.*}" `.$IDX.mp3"
	done
	echo "finished" > "$targetDirectory/$chunkCounter/status"
	((chunkCounter++))
done
echo "finished" > "$targetDirectory/status"
if [[ "$splitted" -eq 1 ]]
then
	rm "$targetDirectory/_temp.stem"*
fi

exit 0

