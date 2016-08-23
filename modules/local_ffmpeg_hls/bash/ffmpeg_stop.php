<?php

/**
Stops the recording and start post processing
*/

require_once __DIR__."/../etc/config.inc";
require_once __DIR__."/../lib_capture.php";
require_once "$basedir/lib_various.php";    

Logger::$print_logs = true;

if ($argc != 2) {
    echo 'Usage: php ffmpeg_stop.php <asset>' . PHP_EOL;
    $logger->log(EventType::RECORDER_FFMPEG_STOP, LogLevel::WARNING, __FILE__ . " called with wrong arg count", array($module_name, "ffmpeg_stop"));
    exit(1);
}

$asset = $argv[1];

$process_dir = get_asset_ffmpeg_folder($asset);
$asset_dir = get_asset_dir($asset, "local_processing");
$pid_file = "$process_dir/process_pid";
$cutlist_file = ffmpeg_get_cutlist_file($asset);
$process_result_file = "$asset_dir/$process_result_filename";
$cam_file_name = "cam.mov";

file_put_contents($process_result_file, "2"); //error by default in result file

#stop monitoring
if(file_exists($ffmpeg_monitoring_file))
    unlink($ffmpeg_monitoring_file);

#stop recording 
stop_ffmpeg($ffmpeg_pid_file);
stop_ffmpeg($ffmpeg_pid2_file); //slides if any
        
$cmd = "/usr/bin/nice -n 10 $php_cli_cmd $ffmpeg_script_merge_movies $process_dir $ffmpeg_movie_name $cam_file_name $cutlist_file $asset >> $process_dir/merge_movies.log 2>&1";
$return_val = 0;
system($cmd, $return_val);
if($return_val != 0) {
    $logger->log(EventType::RECORDER_FFMPEG_STOP, LogLevel::WARNING, "Call to merge movies failed, error code: $return_val. Check merge_movies.log", array($module_name, "ffmpeg_stop"));
    file_put_contents($process_result_file, $return_val); 
}

//if merge movies return 2, merge has been successfully but cutting failed. Let's continue with what we got.
if($return_val == 2) {
    $logger->log(EventType::RECORDER_FFMPEG_STOP, LogLevel::ERROR, "Parts merge succeeded but cutting failed. This is BAD, but let's continue with what we got.", array($module_name, "ffmpeg_stop"));
    //at this point, merge movie script should still have produced a cam.mov file
} else if($return_val != 0) {
    exit(1);
}

$logger->log(EventType::RECORDER_FFMPEG_STOP, LogLevel::DEBUG, "ffmpeg_stop finished movie merging", array($module_name, "ffmpeg_stop"));

//move resulting cam file to asset folder (from ffmpeg folder)
rename("$process_dir/$cam_file_name", "$asset_dir/$cam_file_name");

#all okay, write success
file_put_contents($process_result_file, "0"); 

//move asset folder to upload_to_server
$ok = rename($asset_dir, get_upload_to_server_dir($asset));
if(!$ok) {
    $logger->log(EventType::RECORDER_FFMPEG_STOP, LogLevel::CRITICAL, "ffmpeg_stop could not move asset to upload folder", array($module_name, "ffmpeg_stop"));
    exit(3);
}

$logger->log(EventType::RECORDER_FFMPEG_STOP, LogLevel::INFO, "ffmpeg_stop finished successfully", array($module_name, "ffmpeg_stop"));

exit(0);

// ######################################################
// ######################################################
// ######################################################

function stop_ffmpeg($pid_file) {
    global $logger;
    global $module_name;
    
    if(file_exists($pid_file)) {
        $pid = file_get_contents($pid_file);
        unlink($pid_file);
        $return_val = 0;
        system("kill -2 $pid", $return_val);
        if($return_val != 0) {
            $logger->log(EventType::RECORDER_FFMPEG_STOP, LogLevel::ERROR, "Could not kill FFMPEG process (pid $pid)", array($module_name, "ffmpeg_stop"));
            return false;
        }
        //wait until process closed, or $kill_timeout passed
        $kill_timeout = 10;
        $start = time();
        while(true) {
            $now = time();
            if(($now - $kill_timeout) > $start) {
                system("kill -9 $pid", $return_val);
                break;
            }
            if(!is_process_running($pid))
                break;
        }
    }
    return true;
}