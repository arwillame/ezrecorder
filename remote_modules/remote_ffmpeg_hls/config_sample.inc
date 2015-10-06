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

// used by cli_monitoring.php when a recording has timed out
$classroom = 'Classroom name';
$ezrecorder_url = "http://recorder.domain/ezrecorder";
$remoteffmpeg_force_quit_url = "$ezrecorder_url/index.php?action=recording_force_quit";
$mailto_admins = 'ezrecorder@your.domain';
$remoteffmpeg_basedir = dirname(__FILE__).'/';
$remoteffmpeg_recorddir = '~/Movies/';
$remoteffmpeg_moviesdir = $remoteffmpeg_recorddir.'ffmpeg_hls';
$remoteffmpeg_movie_name = "ffmpegmovie";
$remoteffmpeg_monitoring_file = $remoteffmpeg_basedir . 'var/_monitoring_pid';
$remoteffmpeg_rec_status_file = $remoteffmpeg_basedir . 'var/rec_status';
$remoteffmpeg_status_file = $remoteffmpeg_basedir . 'var/status';
$remoteffmpeg_streaming_info = $remoteffmpeg_basedir . 'var/streaming.xml';
$remoteffmpeg_cli_streaming = $remoteffmpeg_basedir . 'cli_streaming_content_send.php';

$remoteffmpeg_input_source = "UltraStudioMiniRecorder"; // specifies the input source (IntensityShuttle | UltraStudioMiniRecorder | ...)
// you might have to adapt the 'bash/ffmpeg_init' script

$remoteffmpeg_open = $remoteffmpeg_basedir . '/applescript/fmle_open.scpt';
$remoteffmpeg_action = $remoteffmpeg_basedir . '/applescript/fmle_action.scpt';

$remoteffmpeg_capture_file = $remoteffmpeg_basedir . 'var/pic_new.jpg';
$ffmpegpath = "/usr/local/bin/ffmpeg";
$php_path = "/usr/bin/php";
$remoteffmpeg_mono = true; // determines whether the input audio source is mono or stereo
?>