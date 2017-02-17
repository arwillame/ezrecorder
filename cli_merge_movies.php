<?php

// Uses ffmpeg to concatenate video files

include __DIR__."/lib_ffmpeg.php";
include __DIR__."/global_config.inc";

Logger::$print_logs = true;

/*
 * Pour résumer la vue générale :
- ffmpeg crée des .ts dans <asset_dir>/remoteffmpeg/ffmpegmovie_X/high/
- S’il est tué il recommence dans un dossier avec X incrémenté de 1
- Le merge (movie_join_parts) :
   - Rassemble tous les .ts de ffmpegmovie_X en des fichiers joinparts_tmpdir/partX.mov
   - S’il y a plusieurs parties, on les concat en un seul merge.mov, sinon part0 est renommé en merge.mov
- La cutlist: (movie_extract_cutlist)
   - A partir du fichier merge.mov et de cutlist.txt on crée des segments vidéos : cutlist_tmpdir/part-X.mov
   - On merge tous les segments en slide.mov / cam.mov
 */

//handles an offlineqtb recording: multifile recording on podcv and podcs
if ($argc != 6) {
    echo "Usage: " . $argv[0] . " <root_movies_directory> <commonpartname> <output_movie_filename> <cutlist_file> <asset_name>" . PHP_EOL;
    echo "        <root_movies_directory> is the directory containing the movies" . PHP_EOL;
    echo "        <commonpartname> part name that is common to all movies" . PHP_EOL;
    echo "        <output_movie_filename> filename to write output to" . PHP_EOL;
    echo "        <cutlist_file> the file containing the segments to extract from the recording" . PHP_EOL;
    echo "        <asset_name> asset name of the recording" . PHP_EOL;
    echo PHP_EOL;
    echo "Example: php merge_movies.php /Users/podclient/Movies/local_processing/2016_02_20_10h06_PHYS-S201/ffmpeg/ ffmpegmovie cam.mov /Users/podclient/Movies/upload_ok/2016_02_20_10h06_PHYS-S201/ffmpeg/_cut_list.txt 2016_02_20_10h06_PHYS-S201" . PHP_EOL;
    //$logger->log(EventType::RECORDER_MERGE_MOVIES, LogLevel::ERROR, "ffmpeg merge_movies called with wrong arguments", array("merge_movies"));
    exit(1);
}

$movies_path    = $argv[1]; //basedir containing movies (typically /Users/podclient/Movies/local_processing/date_course)
$commonpart     = $argv[2]; // common part of video name (typically 'ffmpegmovie')
$outputfilename = $argv[3]; // // name for output file (typically 'cam.mov')
$cutlist_file   = $argv[4]; // file containing the video segments to extract from the full recording
$asset_name     = $argv[5];

//
//First start with merging parts of each stream 
//join all cam parts (if neccessary)
$moviename = $commonpart;
$merge_file = "merge.mov";

$search_command = "ls -la $movies_path/$moviename* | wc -l";
$output = system($search_command);
if ($output >= 1) {
    print "Join movies with ffmpeg" . PHP_EOL;
    
    $error = movie_join_parts($movies_path, $commonpart, $merge_file); //movie span on multiple files
    if ($error) {
        $logger->log(EventType::RECORDER_MERGE_MOVIES, LogLevel::ERROR, "Movies join failed with result: $error", array("merge_movies"), $asset_name);
        exit(2);
    }
} else if ($output == 0) {
    $logger->log(EventType::RECORDER_MERGE_MOVIES, LogLevel::ERROR, "No video files found (command: $search_command)", array("merge_movies"), $asset_name);
    exit(3);
} else {
    $logger->log(EventType::RECORDER_MERGE_MOVIES, LogLevel::ERROR, "Couldn't get video files because run search command failed: $search_command", array("merge_movies"), $asset_name);
    exit(4);
}

//We will now extract the parts user wants to keep according to the cutlist
$err = movie_extract_cutlist($movies_path, $merge_file, $cutlist_file, $outputfilename, $asset_name);
if($err != 0) {
    $logger->log(EventType::RECORDER_MERGE_MOVIES, LogLevel::ERROR, "Movie cut ($movies_path) failed with error: $err. Move $merge_file to $outputfilename instead.", array("merge_movies"), $asset_name);
    rename("$movies_path/$merge_file", "$movies_path/$outputfilename");
    exit(5);
}

$logger->log(EventType::RECORDER_MERGE_MOVIES, LogLevel::INFO, "Movie cut succeeded ($movies_path)", array("merge_movies"), $asset_name);
unlink($merge_file);

exit(0);
