<?php

/*

File: king-include/king-ajax-click-wall.php
Description: Server-side response to Ajax single clicks on wall posts

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            die(json_encode(['success' => false, 'message' => curl_error($ch)]));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['name'])) {
            $error = 'Failed to get operation name from Gemini Veo 3.1 API.';
            die(json_encode(['success' => false, 'message' => $error]));
        }

        $operation_name = $data['name'];

        // Poll for result
        $video_uri = '';
        $max_attempts = 60;
        $attempt = 0;

        while ($attempt < $max_attempts) {
            $status_url = "https://generativelanguage.googleapis.com/v1beta/" . $operation_name . "?key=" . $API_KEY;

            $ch = curl_init($status_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 600);

            $status_response = curl_exec($ch);
            curl_close($ch);

            $status = json_decode($status_response, true);

            if (isset($status['done']) && $status['done'] === true) {
                $video_uri = $status['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] ?? null;

                if ($video_uri) {
                    $videourl = $video_uri . (strpos($video_uri, '?') === false ? '?' : '&') . 'key=' . $API_KEY;
                }
                break;
            } else {
                sleep(10);
                $attempt++;
            }
        }

        if (empty($videourl)) {
            $error = 'Veo 3.1 video generation failed or timed out.';
        }

    } else {
        $api_url = "https://kingstudio.io/api/king-text2video"; //change it !!!!
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 400);
        $response = curl_exec($ch);


        $out = json_decode($response, true);
        if (isset($out['error'])) {
            $error = $out['error'];
        }
        $videourl = $out['out'] ?? [];
    }
    

    if (isset($error) && $error) {
        $output = json_encode(array('success' => false, 'message' => $error));
    } else {
        require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
        $extra = king_urlupload($videourl);

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

        $output = json_encode(array(
            'success' => true,
            'postid' => $postid,
            'videourl' => $videourl
        ));
    }
    echo "QA_AJAX_RESPONSE\n1\n";

    echo $output."\n";

    echo king_ai_posts($userid, 'aivid');


} else {
    $outputz = json_encode(array('success' => false, 'message' => qa_lang_html('misc/nocredits')));

    echo "QA_AJAX_RESPONSE\n0\n";

    echo $outputz."\n";
}
