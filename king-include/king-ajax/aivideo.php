<?php

/*

File: king-include/king-ajax/aivideo.php
Description: Server-side response to Ajax AI video generation

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

More about this license: LICENCE.html
 */

// CRITICAL: Set execution time limits FIRST
set_time_limit(600); // 10 minutes for video (longer than images)
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');

require_once QA_INCLUDE_DIR . 'king-app/users.php';
require_once QA_INCLUDE_DIR . 'king-app/limits.php';
require_once QA_INCLUDE_DIR . 'king-db/selects.php';
require_once QA_INCLUDE_DIR . 'king-app-video.php';
require_once QA_INCLUDE_DIR . 'king-app/cookies.php';
require_once QA_INCLUDE_DIR.'king-db/metas.php';

if (qa_is_logged_in()) {
    $userid = qa_get_logged_in_userid();
} else {
    $userid = qa_remote_ip_address();
}

$input = qa_post_text('input');
$imsize = qa_post_text('radio');
$reso = qa_post_text('reso');
$provider = qa_post_text('model');
$imageid = qa_post_text('imageid');

$chkk = true;
$error = '';

if (qa_opt('enable_membership') && (qa_opt('ailimits') || qa_opt('ulimits')) && qa_get_logged_in_level() <= QA_USER_LEVEL_ADMIN) {
    $chkk = kingai_check();
}

if (qa_opt('enable_credits') && qa_opt('post_aivid')) {
    $chkk = king_spend_credit(qa_opt('post_aivid'));
}

if ($input && $chkk) {
    if ($provider === 'veo3' || $provider === 'veo3f') {
        $API_KEY = qa_opt('gemini_api');

        // Select correct model
        if ($provider === 'veo3f') {
            $api_url = "https://generativelanguage.googleapis.com/v1beta/models/veo-3.1-fast-generate-preview:predictLongRunning?key=" . $API_KEY;
        } else {
            $api_url = "https://generativelanguage.googleapis.com/v1beta/models/veo-3.1-generate-preview:predictLongRunning?key=" . $API_KEY;
        }

        // Build request (prompt only OR prompt + uploaded file)
        $payload = [
            "instances" => [
                [
                    "prompt" => $input
                ]
            ]
        ];

        // If user uploaded a file using resumable upload
        if (!empty($_POST['file_uri'])) {
            $payload["instances"][0]["file"] = [
                "file_uri" => $_POST['file_uri'],
            ];
        }

        // Start request
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Quick initial request
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = "API Error: " . curl_error($ch);
            curl_close($ch);
        } else {
            curl_close($ch);
            
            $data = json_decode($response, true);

            if (!isset($data['name'])) {
                $error = 'Failed to get operation name from Gemini Veo 3.1 API.';
            } else {
                $operation_name = $data['name'];

                // Poll for result - optimized
                $video_uri = '';
                $max_attempts = 60; // 60 attempts
                $attempt = 0;
                $sleep_time = 10; // 10 seconds between attempts

                while ($attempt < $max_attempts) {
                    $status_url = "https://generativelanguage.googleapis.com/v1beta/" . $operation_name . "?key=" . $API_KEY;

                    $ch = curl_init($status_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Reduced from 600
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

                    $status_response = curl_exec($ch);
                    
                    if (curl_errno($ch)) {
                        error_log("Polling error: " . curl_error($ch));
                        curl_close($ch);
                        sleep($sleep_time);
                        $attempt++;
                        continue;
                    }
                    
                    curl_close($ch);

                    $status = json_decode($status_response, true);

                    if (isset($status['done']) && $status['done'] === true) {
                        $video_uri = $status['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] ?? null;

                        if ($video_uri) {
                            $videourl = $video_uri . (strpos($video_uri, '?') === false ? '?' : '&') . 'key=' . $API_KEY;
                        }
                        break;
                    } elseif (isset($status['error'])) {
                        // Error occurred
                        $error = 'Veo 3.1 returned error: ' . json_encode($status['error']);
                        break;
                    } else {
                        // Still processing
                        sleep($sleep_time);
                        $attempt++;
                    }
                }

                if (empty($videourl) && empty($error)) {
                    $error = 'Veo 3.1 video generation timed out after ' . ($max_attempts * $sleep_time) . ' seconds.';
                }
            }
        }

    } else {
        // Other video models (Kling, Luma, Pixverse, etc.)
        $api_url = "https://kingstudio.io/api/king-text2video";
        $api_key = qa_opt('king_sd_api');

        $request_data = [
            "prompt" => $input,
            "aisize" => $imsize,
            "model" => $provider,
            "reso" => $reso,
        ];
        
        if ($imageid) {
            $imageurl = king_get_uploads($imageid);
            $request_data['image'] = $imageurl['furl'];
        }

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $api_key",
            "Accept: application/json",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Reduced from 400 to 300 (5 minutes)
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = "API Error: " . curl_error($ch);
        }
        
        curl_close($ch);

        if (!$error) {
            $out = json_decode($response, true);
            if (isset($out['error'])) {
                $error = $out['error'];
            } else {
                $videourl = $out['out'] ?? '';
            }
        }
    }

    if (isset($error) && $error) {
        $output = json_encode(array('success' => false, 'message' => $error));
        echo "QA_AJAX_RESPONSE\n0\n";
        echo $output . "\n";
    } else {
        if (empty($videourl)) {
            $output = json_encode(array('success' => false, 'message' => 'Failed to generate video'));
            echo "QA_AJAX_RESPONSE\n0\n";
            echo $output . "\n";
        } else {
            require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
            
            // Upload video
            $extra = king_urlupload($videourl);

            if (empty($extra)) {
                $output = json_encode(array('success' => false, 'message' => 'Failed to upload video'));
                echo "QA_AJAX_RESPONSE\n0\n";
                echo $output . "\n";
            } else {
                $thumb = null;
                $cookieid = isset($userid) ? qa_cookie_get() : qa_cookie_get_create();
                
                $postid = qa_question_create(null, $userid, qa_get_logged_in_handle(), $cookieid, null, $thumb, '', null, null, null, null, null, $extra, 'NOTE', null, 'aivid', $input, null);
                
                qa_db_postmeta_set($postid, 'wai', true);
                qa_db_postmeta_set($postid, 'model', $provider);

                if ($reso) {
                    qa_db_postmeta_set($postid, 'stle', $reso);
                }
                if (isset($imsize)) {
                    qa_db_postmeta_set($postid, 'asize', $imsize);
                }
                if ($imageid) {
                    qa_db_postmeta_set($postid, 'pimage', $imageid);
                }
                
                if (qa_opt('enable_membership') && (qa_opt('ailimits') || qa_opt('ulimits'))) {
                    kingai_imagen(1); // Count video generation
                }

                $output = json_encode(array(
                    'success' => true,
                    'postid' => $postid,
                    'videourl' => $videourl
                ));

                echo "QA_AJAX_RESPONSE\n1\n";
                echo $output . "\n";
                echo king_ai_posts($userid, 'aivid');
            }
        }
    }

} else {
    $outputz = json_encode(array('success' => false, 'message' => qa_lang_html('misc/nocredits')));
    echo "QA_AJAX_RESPONSE\n0\n";
    echo $outputz . "\n";
}