<?php

if (!@$argc) {
    die(0);
}
require str_replace('\\', '/', dirname($argv[0])) . '/../wwwdir/init.php';
$unique_id = TMP_DIR . md5(UniqueID() . __FILE__);
KillProcessCmd($unique_id);
cli_set_process_title('XtreamCodes[VOD CC Checker]');
ini_set('memory_limit', -1);
$ipTV_db->query('SELECT * FROM `streams` t1 INNER JOIN `transcoding_profiles` t2 ON t2.profile_id = t1.transcode_profile_id WHERE t1.type = 3');
if (0 < $ipTV_db->num_rows()) {
    $streams = $ipTV_db->get_rows();
    foreach ($streams as $stream) {
        echo '[*] Checking Stream ' . $stream['stream_display_name'] . '';
        switch (ipTV_stream::TranscodeBuild($stream['id'])) {
            case 1:
                echo 'Build Is Still Going!';
                ipTV_stream::TranscodeBuild($stream['id']);
                break;
            case 2:
                echo 'Build Finished';
                break;
        }
    }
}
$pid = ipTV_servers::getPidFromProcessName(SERVER_ID, FFMPEG_PATH);
$ipTV_db->query('SELECT t1.*,t2.* FROM `streams_sys` t1 INNER JOIN `streams` t2 ON t2.id = t1.stream_id AND t2.direct_source = 0 INNER JOIN `streams_types` t3 ON t3.type_id = t2.type AND t3.live = 0 WHERE (t1.to_analyze = 1 OR t1.stream_status = 2) AND t1.server_id = \'%d\'', SERVER_ID);
if (0 < $ipTV_db->num_rows()) {
    $series_data = $ipTV_db->get_rows();
    foreach ($series_data as $data) {
        echo '[*] Checking Movie ' . $data['stream_display_name'] . ' ON Server ID ' . $data['server_id'] . ' 		---> ';
        if ($data['to_analyze'] == 1) {
            if (!empty($pid[$data['server_id']]) && in_array($data['pid'], $pid[$data['server_id']])) {
                echo 'WORKING';
            } else {
                echo '';
                $target_container = json_decode($data['target_container'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['target_container'] = $target_container;
                } else {
                    $data['target_container'] = array($data['target_container']);
                }
                $data['target_container'] = $data['target_container'][0];
                $fileURL = MOVIES_PATH . $data['stream_id'] . '.' . $data['target_container'];
                if ($stream_info = ipTV_stream::GetStreamInfo($fileURL, $data['server_id'])) {
                    $duration = isset($stream_info['duration']) ? $stream_info['duration'] : 0;
                    sscanf($duration, '%d:%d:%d', $hours, $minutes, $seconds);
                    $duration_secs = isset($seconds) ? $hours * 3600 + $minutes * 60 + $seconds : $hours * 60 + $minutes;
                    $resultCommand = ipTV_servers::RunCommandServer($data['server_id'], 'wc -c < ' . $fileURL, 'raw');
                    $bitrate = round($resultCommand[$data['server_id']] * 0.008 / $duration_secs);
                    $movie_propeties = json_decode($data['movie_propeties'], true);
                    if (!is_array($movie_propeties)) {
                        $movie_propeties = array();
                    }
                    if (!isset($movie_propeties['duration_secs']) || $duration_secs != $movie_propeties['duration_secs']) {
                        $movie_propeties['duration_secs'] = $duration_secs;
                        $movie_propeties['duration'] = $duration;
                    }
                    if (!isset($movie_propeties['video']) || $stream_info['codecs']['video']['codec_name'] != $movie_propeties['video']) {
                        $movie_propeties['video'] = $stream_info['codecs']['video'];
                    }
                    if (!isset($movie_propeties['audio']) || $stream_info['codecs']['audio']['codec_name'] != $movie_propeties['audio']) {
                        $movie_propeties['audio'] = $stream_info['codecs']['audio'];
                    }
                    if (!isset($movie_propeties['bitrate']) || $bitrate != $movie_propeties['bitrate']) {
                        $movie_propeties['bitrate'] = $bitrate;
                    }
                    $ipTV_db->query('UPDATE `streams` SET `movie_propeties` = \'%s\' WHERE `id` = \'%d\'', json_encode($movie_propeties), $data['stream_id']);
                    $ipTV_db->query('UPDATE `streams_sys` SET `bitrate` = \'%d\',`to_analyze` = 0,`stream_status` = 0,`stream_info` = \'%s\'  WHERE `server_stream_id` = \'%d\'', $bitrate, json_encode($stream_info), $data['server_stream_id']);
                    echo 'VALID';
                } else {
                    $ipTV_db->query('UPDATE `streams_sys` SET `to_analyze` = 0,`stream_status` = 1  WHERE `server_stream_id` = \'%d\'', $data['server_stream_id']);
                    echo 'BAD MOVIE';
                }
            }
        } else {
            echo 'NO ACTION';
        }
    }
}
?>
