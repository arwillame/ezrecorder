<?php
/*
 * This is a CLI script that sends a request to ezcast to download the recordings
 * By default, the data about the record to send is retrieved from the session module.
 * Alternatively, you can provide
 * Usage: cli_upload_to_server.php [asset_name]
 * 
 */

require_once 'global_config.inc';

require_once $cam_lib;
require_once $slide_lib;
require_once $session_lib;
require_once 'lib_error.php';
require_once 'lib_various.php';
require_once 'lib_model.php';

Logger::$print_logs = true;

$asset = '';
if(isset($argv[1]))
{
    $asset = $argv[1];
} else {
    //get session metadata to find last course
    $fct = "session_" . $session_module . "_metadata_get";
    $meta_assoc = $fct();
    if($meta_assoc == false) {
        $logger->log(EventType::RECORDER_UPLOAD_WRONG_METADATA, LogLevel::CRITICAL, "Could not get session metadata file, cannot continue", array("cli_upload_to_server"));
        exit(1);
    }

    $record_date = $meta_assoc['record_date'];
    $course_name = $meta_assoc['course_name'];
    $record_type = $meta_assoc['record_type'];

    $asset = get_asset_name($course_name, $record_date);
}

$asset_dir = get_asset_dir($asset);
$metadata_file = "$asset_dir/metadata.xml";
if (!file_exists($metadata_file)) {
    $logger->log(EventType::RECORDER_UPLOAD_WRONG_METADATA, LogLevel::CRITICAL, "Could not get asset metadata file from dir: $asset_dir, cannot continue", array("cli_upload_to_server"), $asset);
    echo "Error: metadata file $metadata_file does not exist" . PHP_EOL;
    exit(2);
}


////call EZcast server and tell it a recording is ready to download

$cam_download_info = null;
if ($cam_enabled) {
    // get downloading information required by EZcast server
    $fct = 'capture_' . $cam_module . '_info_get';
    $cam_download_info = $fct('download', $asset);
    if($cam_download_info == false) {
        $logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::ERROR, "Couldn't get info from cam module. Camera will be ignored", array("cli_upload_to_server"), $asset);
        $cam_enabled = false;
    }
}

$slide_download_info = null;
if ($slide_enabled) {
    // get downloading information required by EZcast server
    $fct = 'capture_' . $slide_module . '_info_get';
    $slide_download_info = $fct('download', $asset);
    if($slide_download_info == false) {
        $logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::ERROR, "Couldn't get info from slide module. Slides will be ignored", array("cli_upload_to_server"), $asset);
        $slide_enabled = false;
    }
}

if(!$cam_enabled && !$slide_enabled) { //we may have errors on both, stop in this case
    $logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::CRITICAL, "Both cam and slides modules are disabled or have errors, nothing to upload.", array("cli_upload_to_server"), $asset);
    exit(3);
}

//update record type depending on failures above
$record_type = get_record_type($cam_enabled, $slide_enabled);

$logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::INFO, "Starting upload to ezcast", array("cli_upload_to_server"), $asset);

//try repeatedly to call EZcast server and send the right post parameters
$retry_count = 500;
for($retry = 0; $retry < $retry_count; $retry++) {
    $error = server_start_download($record_type, $record_date, $course_name, $cam_download_info, $slide_download_info);
    switch($error) {
    case 0: // no error, exit
        $logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::INFO, "Successfully sent upload request to ezcast.", array("cli_upload_to_server"), $asset);
        //normal exit path
        
        $fct = "session_" . $session_module . "_metadata_delete";
        //debug UNCOMMENT THIS //$fct();

        exit(0);
    case 1: // error, retry
        $logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::ERROR, "Upload (curl) failed, will retry later.", array("cli_upload_to_server"), $asset);
        log_append('EZcast_curl_call', "Will retry later: Error connecting to EZcast server ($ezcast_submit_url). \n");
        sleep(120);
        break;
    case 2: // critical error, exit
        $logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::CRITICAL, "Upload failed after a critical error was encountered, giving up", array("cli_upload_to_server"), $asset);
        exit(4);
    }
}

//if we get here, all retries have failed:
$logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::CRITICAL, "Upload failed after $retry_count tries, giving up", array("cli_upload_to_server"), $asset);
exit(5);

// checks whether we can send this data to the server
function is_valid_download_info($download_info, &$err_info) {
    if(!file_exists($download_info["filename"]))
    {
        $err_info = 'File '. $download_info["filename"]. ' does not exists on recorder.';
        return false;
    }
    
    return true;
}

/**
 *
 * @param <slide|cam|camslide> $recording_type
 * @param <YYYY_MM_DD_HHhmm> $recording_date
 * @param <mnemonique> $course_name
 * @param <associative_array> $cam_download_info information relative to the downloading of cam movie. May be null.
 * @param <associative_array> $slide_download_info information relative to the downloading of slide movie. May be null.
 * @return 0 if all okay, 1 if error, 2 if critical error (don't bother retrying)
 */
function server_start_download($record_type, $record_date, $course_name, $cam_download_info, $slide_download_info) {
    global $logger;
    
    $err_info = '';
    if(isset($cam_download_info) && !is_valid_download_info($cam_download_info, $err_info)) {
        $logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::CRITICAL, "Invalid cam download info for ezcast. Cannot continue. Error info: $err_info . Download info: " . json_encode($cam_download_info), array("cli_upload_to_server","server_start_download"));
        return 2;
    }
    
    if(isset($slide_download_info) && !is_valid_download_info($slide_download_info, $err_info)) {
        $logger->log(EventType::RECORDER_UPLOAD_TO_EZCAST, LogLevel::CRITICAL, "Invalid slide download info for ezcast. Cannot continue. Error info: $err_info . Download info: " . json_encode($slide_download_info), array("cli_upload_to_server","server_start_download"));
        return 2;
    }
    
    //tells the server that a recording is ready to be downloaded
    global $ezcast_submit_url;
    global $asset_dir;
    global $recorder_version;
    global $php_cli_cmd;

    $post_array['action'] = 'download';
    $post_array['record_type'] = $record_type;
    $post_array['record_date'] = $record_date;
    $post_array['course_name'] = $course_name;
    $post_array['php_cli'] = $php_cli_cmd;
    $post_array['metadata_file'] = $asset_dir . "/metadata.xml";

    if (isset($cam_download_info) && count($cam_download_info) > 0) {
        $post_array['cam_info'] = serialize($cam_download_info);
    }

    if (isset($slide_download_info) && count($slide_download_info) > 0) {
        $post_array['slide_info'] = serialize($slide_download_info);
    }

    if (isset($recorder_version) && !empty($recorder_version)) {
        $post_array['recorder_version'] = $recorder_version;
    }
    
    $curl_success = strpos(server_request_send($ezcast_submit_url, $post_array), 'Curl error') === false;
    if(!$curl_success)
        return 1;
    
    return 0;
}