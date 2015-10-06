<?php

/*
 * EZCAST EZrecorder
 *
 * Copyright (C) 2014 Université libre de Bruxelles
 *
 * Written by Michel Jansens <mjansens@ulb.ac.be>
 * 	      Arnaud Wijns <awijns@ulb.ac.be>
 *            Antoine Dewilde
 * UI Design by Julien Di Pietrantonio
 *
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this software; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 *  This CLI script performs various monitoring tasks. It is started when the user starts a recording, and stopped when they stop recording.
 * This script is called by qtbstart and qtbstop.
 * Current checks performed:
 * - timeout check (checks whether the user has forgotten to stop recording, and publish the recording if they did)
 */
/**
 * Timeout check:
 * For the first "threshold" seconds (typically 2 or 3 hours), we decide to trust the user.
 * After that, we check that there has been activity at least once every "timeout" seconds (typically 15 min).
 * This program is meant to be run as a crontask at least once every "timeout" seconds
 */
require_once 'global_config.inc';

require_once $session_lib;

// Saves the time when the recording has been init
$init_time = time();
$fct_initstarttime_set = "session_" . $session_module . "_initstarttime_set";
$fct_initstarttime_set($init_time);

// Delays, in seconds
$threshold_timeout = 7200; // Threshold before we start worrying about the user
//$threshold_timeout = 120; // Threshold before we start worrying about the user
$recovery_threshold = 20; // Threshold before we start worrying about QTB
$timeout = 900; // Timeout after which we consider a user has forgotten to stop their recording
//$timeout = 30;
$sleep_time = 60; // Duration of the sleep between two checks

set_time_limit(0);
$pid = getmypid();
fwrite(fopen($recorder_monitoring_pid, 'w'), $pid);

// This is the main loop. Runs until the lock file disappears
while (true) {

    $fct_is_locked = "session_" . $session_module . "_is_locked";

    // We stop if the file does not exist anymore ("kill -9" simulation)
    // or the file contains an other pid
    // or the recorder is not locked anymore
    if (!file_exists($recorder_monitoring_pid) || $pid != file_get_contents($recorder_monitoring_pid) || !$fct_is_locked()) {
        die;
    }

    // Timeout check

    $fct_last_request_get = "session_" . $session_module . "_last_request_get";

    $lastmod_time = $fct_last_request_get();
    $now = time();

    if ($now - $init_time > $threshold_timeout && $now - $lastmod_time > $timeout) {
        mail($mailto_admins, 'Recording timed out', 'The recording in classroom ' . $classroom . ' was stopped and published in private album because there has been no user activity since ' . ($now - $lastmod_time) . ' seconds ago.');

        $ezrecorder_force_quit_url = "$ezrecorder_url/index.php?action=recording_force_quit";

        $ch = curl_init($ezrecorder_force_quit_url);
        $res = curl_exec($ch);
        $curlinfo = curl_getinfo($ch);
        curl_close($ch);
    }

    sleep($sleep_time);
}


?>