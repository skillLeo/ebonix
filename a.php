Luma HTTP 400: {"detail":"Invalid request: Field required model"}
luma creating image perfectly but nt.  creating. the viddeo


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
set_time_limit(600); // 10 minutes for video
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
$videourl = '';

if (qa_opt('enable_membership') && (qa_opt('ailimits') || qa_opt('ulimits')) && qa_get_logged_in_level() <= QA_USER_LEVEL_ADMIN) {
    $chkk = kingai_check();
}

if (qa_opt('enable_credits') && qa_opt('post_aivid')) {
    $chkk = king_spend_credit(qa_opt('post_aivid'));
}

if ($input && $chkk) {
    
    // ========== VEO 3 / VEO 3 FAST ==========
    if ($provider === 'veo3' || $provider === 'veo3f') {
        $API_KEY = qa_opt('gemini_api');

        if ($provider === 'veo3f') {
            $api_url = "https://generativelanguage.googleapis.com/v1beta/models/veo-3.1-fast-generate-preview:predictLongRunning?key=" . $API_KEY;
        } else {
            $api_url = "https://generativelanguage.googleapis.com/v1beta/models/veo-3.1-generate-preview:predictLongRunning?key=" . $API_KEY;
        }

        $payload = [
            "instances" => [
                ["prompt" => $input]
            ]
        ];

        if (!empty($_POST['file_uri'])) {
            $payload["instances"][0]["file"] = [
                "file_uri" => $_POST['file_uri'],
            ];
        }

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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

                $video_uri = '';
                $max_attempts = 60;
                $attempt = 0;
                $sleep_time = 10;

                while ($attempt < $max_attempts) {
                    $status_url = "https://generativelanguage.googleapis.com/v1beta/" . $operation_name . "?key=" . $API_KEY;

                    $ch = curl_init($status_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
                        $error = 'Veo 3.1 returned error: ' . json_encode($status['error']);
                        break;
                    } else {
                        sleep($sleep_time);
                        $attempt++;
                    }
                }

                if (empty($videourl) && empty($error)) {
                    $error = 'Veo 3.1 video generation timed out after ' . ($max_attempts * $sleep_time) . ' seconds.';
                }
            }
        }

    } 
    // ========== DECART VIDEO ==========
    elseif ($provider === 'decart_vid') {
        $API_KEY = qa_opt('decart_api');
        
        if (empty($API_KEY)) {
            $error = 'Decart API key not configured';
        } else {
            // Determine which Decart endpoint to use
            if ($imageid) {
                // Image-to-video
                $api_url = "https://api.decart.ai/v1/jobs/lucy-pro-i2v";
                $image_info = king_get_uploads($imageid);
                $file_path = isset($image_info['path']) ? $image_info['path'] : '';
            } else {
                // Text-to-video (no input file) or video transformation
                $api_url = "https://api.decart.ai/v1/jobs/lucy-pro-t2v";
                $file_path = '';
            }
            
            // Build multipart form data
            $boundary = '----WebKitFormBoundary' . uniqid();
            $body = '';
            
            // Add prompt
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
            $body .= $input . "\r\n";
            
            // Add file if exists (for image-to-video)
            if ($file_path && file_exists($file_path)) {
                $file_data = file_get_contents($file_path);
                $file_name = basename($file_path);
                $mime_type = mime_content_type($file_path);
                
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"data\"; filename=\"{$file_name}\"\r\n";
                $body .= "Content-Type: {$mime_type}\r\n\r\n";
                $body .= $file_data . "\r\n";
            }
            
            $body .= "--{$boundary}--\r\n";
            
            // Submit job to Decart
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "X-API-KEY: $API_KEY",
                "Content-Type: multipart/form-data; boundary={$boundary}"
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                $error = "Decart API Error: " . curl_error($ch);
            }
            
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if (!$error) {
                $out = json_decode($response, true);
                
                if ($http_code !== 200 && $http_code !== 201) {
                    $error = 'Decart API returned HTTP ' . $http_code . ': ' . ($out['error']['message'] ?? $response);
                } elseif (isset($out['error'])) {
                    $error = $out['error']['message'] ?? 'Decart video generation failed';
                } elseif (isset($out['job_id'])) {
                    // Job submitted - poll for completion
                    $job_id = $out['job_id'];
                    $max_attempts = 120; // 10 minutes (120 * 5s = 600s)
                    $attempt = 0;
                    $sleep_time = 5;
                    
                    while ($attempt < $max_attempts) {
                        sleep($sleep_time);
                        
                        $status_url = "https://api.decart.ai/v1/jobs/{$job_id}";
                        $ch = curl_init($status_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            "X-API-KEY: $API_KEY"
                        ]);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        
                        $status_response = curl_exec($ch);
                        
                        if (curl_errno($ch)) {
                            error_log("Decart polling error: " . curl_error($ch));
                            curl_close($ch);
                            $attempt++;
                            continue;
                        }
                        
                        curl_close($ch);
                        
                        $status = json_decode($status_response, true);
                        
                        if (isset($status['status']) && $status['status'] === 'completed') {
                            // Download video
                            $download_url = "https://api.decart.ai/v1/jobs/{$job_id}/content";
                            $ch = curl_init($download_url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                "X-API-KEY: $API_KEY"
                            ]);
                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
                            
                            $video_data = curl_exec($ch);
                            
                            if (curl_errno($ch)) {
                                $error = "Failed to download video: " . curl_error($ch);
                            } else {
                                // Save to temporary file
                                $temp_dir = sys_get_temp_dir();
                                $temp_file = $temp_dir . '/decart_video_' . uniqid() . '.mp4';
                                
                                if (file_put_contents($temp_file, $video_data)) {
                                    // Create a URL-like reference for king_urlupload
                                    // Since we have local file, we'll handle upload differently
                                    require_once QA_INCLUDE_DIR . 'king-app/blobs.php';
                                    require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
                                    
                                    $folder = 'uploads/' . date("Y") . '/' . date("m") . '/';
                                    $destDir = QA_INCLUDE_DIR . $folder;
                                    
                                    if (!file_exists($destDir)) {
                                        mkdir($destDir, 0777, true);
                                    }
                                    
                                    $finalFilename = 'decart-video-' . time() . '-' . mt_rand(1000, 9999) . '.mp4';
                                    $finalPath = $destDir . $finalFilename;
                                    
                                    if (copy($temp_file, $finalPath)) {
                                        // Upload to cloud if enabled
                                        if (qa_opt('enable_aws')) {
                                            $videourl = king_upload_to_cloud($finalPath, $finalFilename, 'aws');
                                            $extra = king_insert_uploads($videourl, 'mp4', 0, 0, 'aws');
                                        } elseif (qa_opt('enable_wasabi')) {
                                            $videourl = king_upload_to_cloud($finalPath, $finalFilename, 'wasabi');
                                            $extra = king_insert_uploads($videourl, 'mp4', 0, 0, 'wasabi');
                                        } else {
                                            $videourl = qa_path_to_root() . $folder . $finalFilename;
                                            $extra = king_insert_uploads($folder . $finalFilename, 'mp4', 0, 0);
                                        }
                                    }
                                    
                                    @unlink($temp_file);
                                }
                            }
                            
                            curl_close($ch);
                            break;
                        } elseif (isset($status['status']) && $status['status'] === 'failed') {
                            $error = 'Decart video generation failed: ' . ($status['error']['message'] ?? 'Unknown error');
                            break;
                        }
                        
                        $attempt++;
                    }
                    
                    if (empty($videourl) && empty($error) && empty($extra)) {
                        $error = 'Decart video generation timed out after 10 minutes';
                    }
                } else {
                    $error = 'Invalid response from Decart API';
                }
            }
        }
    } 
    // ========== LUMA VIDEO ==========
    elseif ($provider === 'luma_vid') {
        $API_KEY = qa_opt('luma_api');
        
        if (empty($API_KEY)) {
            $error = 'Luma API key not configured';
        } else {
            // Submit generation job
            $api_url = "https://api.lumalabs.ai/dream-machine/v1/generations";
            
            $payload = [
                'prompt' => $input,
            ];
            
            // Add keyframes (image-to-video)
            if ($imageid) {
                $image_info = king_get_uploads($imageid);
                if (isset($image_info['furl'])) {
                    $payload['keyframes'] = [
                        'frame0' => [
                            'type' => 'image',
                            'url' => $image_info['furl']
                        ]
                    ];
                }
            }
            
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $API_KEY",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = "Luma API Error: " . curl_error($ch);
            }
            
            curl_close($ch);
            
            if (!$error) {
                $out = json_decode($response, true);
                
                if ($http_code !== 200 && $http_code !== 201) {
                    $error = 'Luma HTTP ' . $http_code . ': ' . ($out['error'] ?? $response);
                } elseif (isset($out['error'])) {
                    $error = $out['error'];
                } elseif (isset($out['id'])) {
                    // Poll for completion
                    $generation_id = $out['id'];
                    $max_attempts = 120; // 10 minutes
                    $attempt = 0;
                    $sleep_time = 5;
                    
                    while ($attempt < $max_attempts) {
                        sleep($sleep_time);
                        
                        $status_url = "https://api.lumalabs.ai/dream-machine/v1/generations/{$generation_id}";
                        $ch = curl_init($status_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            "Authorization: Bearer $API_KEY"
                        ]);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        
                        $status_response = curl_exec($ch);
                        
                        if (curl_errno($ch)) {
                            error_log("Luma polling error: " . curl_error($ch));
                            curl_close($ch);
                            $attempt++;
                            continue;
                        }
                        
                        curl_close($ch);
                        
                        $status = json_decode($status_response, true);
                        
                        if (isset($status['state']) && $status['state'] === 'completed') {
                            // Get video URL
                            if (isset($status['assets']['video'])) {
                                $videourl = $status['assets']['video'];
                            }
                            break;
                        } elseif (isset($status['state']) && $status['state'] === 'failed') {
                            $error = 'Luma video generation failed: ' . ($status['failure_reason'] ?? 'Unknown error');
                            break;
                        }
                        
                        $attempt++;
                    }
                    
                    if (empty($videourl) && empty($error)) {
                        $error = 'Luma video generation timed out after 10 minutes';
                    }
                } else {
                    $error = 'Invalid response from Luma API';
                }
            }
        }
    }    // ========== OTHER VIDEO MODELS (KingStudio) ==========
    else {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
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

    // ========== PROCESS RESULTS ==========
    if (isset($error) && $error) {
        $output = json_encode(array('success' => false, 'message' => $error));
        echo "QA_AJAX_RESPONSE\n0\n";
        echo $output . "\n";
    } else {
        // For Decart, we already have $extra set from the upload process
        if ($provider === 'decart_vid' && !empty($extra)) {
            // Video already uploaded, create post
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
                kingai_imagen(1);
            }

            $output = json_encode(array(
                'success' => true,
                'postid' => $postid,
                'videourl' => $videourl
            ));

            echo "QA_AJAX_RESPONSE\n1\n";
            echo $output . "\n";
            echo king_ai_posts($userid, 'aivid');
            
        } elseif (empty($videourl)) {
            $output = json_encode(array('success' => false, 'message' => 'Failed to generate video'));
            echo "QA_AJAX_RESPONSE\n0\n";
            echo $output . "\n";
        } else {
            // For other providers, use existing upload flow
            require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
            
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
                    kingai_imagen(1);
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


<?php
/*
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

if (!defined('QA_VERSION')) {
	// don't allow this page to be requested directly from browser
	header('Location: ../');
	exit;
}

require_once QA_INCLUDE_DIR.'king-app/format.php';
require_once QA_INCLUDE_DIR.'king-app/limits.php';
require_once QA_INCLUDE_DIR.'king-db/selects.php';
require_once QA_INCLUDE_DIR.'king-util/sort.php';
require_once QA_INCLUDE_DIR.'king-db/metas.php';
require_once QA_INCLUDE_DIR.'king-app/posts.php';
//    Check whether this is a follow-on question and get some info we need from the database

$in = array();

$followpostid     = qa_get('follow');
$in['categoryid'] = qa_clicked('doask') ? qa_get_category_field_value('category') : qa_get('cat');
$userid           = qa_get_logged_in_userid();
$handle           = qa_get_logged_in_handle();

list($categories, $followanswer, $completetags) = qa_db_select_with_pending(
	qa_db_category_nav_selectspec($in['categoryid'], true),
	isset($followpostid) ? qa_db_full_post_selectspec($userid, $followpostid) : null,
	qa_db_popular_tags_selectspec(0, QA_DB_RETRIEVE_COMPLETE_TAGS)
);
 
if (!isset($categories[$in['categoryid']])) {
	$in['categoryid'] = null;
}

if (@$followanswer['basetype'] != 'A') {
	$followanswer = null;
}

//    Check for permission error

$permiterror = qa_user_maximum_permit_error('permit_post_q', QA_LIMIT_QUESTIONS);

if ($permiterror && qa_clicked('doask')) {
	$errors = array();
	$errors['permiterror'] = qa_lang_html('question/ask_limit');
	$response['status'] = 'error';
	$response['message'] = $errors;
	echo json_encode($response); // Output response as JSON
	exit;
}

if ($permiterror || ! qa_opt('enable_aivideo')) {
	$qa_content = qa_content_prepare();

	switch ($permiterror) {
		case 'login':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent = qa_insert_login_links(qa_lang_html('question/ask_must_login'), qa_request(), isset($followpostid) ? array('follow' => $followpostid) : null);
		break;

		case 'confirm':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent = qa_insert_login_links(qa_lang_html('question/ask_must_confirm'), qa_request(), isset($followpostid) ? array('follow' => $followpostid) : null);
		break;

		case 'limit':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent=qa_lang_html('question/ask_limit');
		break;

		case 'membership':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent=qa_insert_login_links(qa_lang_html('misc/mem_message'));
		$qa_content['custom'] = '<div class="nopost"><i class="fa-solid fa-fingerprint fa-4x"></i><p>'.$econtent.'</p><a href="'. qa_path_html( 'membership' ) .'" class="meme-button">'.qa_lang_html('misc/see_plans').'</a></div>';
		break;

		case 'approve':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent=qa_lang_html('question/ask_must_be_approved');
		break;

		default:
		$econtent=qa_lang_html('users/no_permission');
		$qa_content['error']=qa_lang_html('users/no_permission');
		break;
	}

	if (empty($qa_content['custom'] )) {
		$qa_content['custom'] = '<div class="nopost"><i class="fa-solid fa-circle-user fa-4x"></i>'.$econtent.'</div>';
	}
	return $qa_content;
}


$captchareason = qa_user_captcha_reason();

$in['title'] = qa_get_post_title('title'); // allow title and tags to be posted by an external form


if (qa_using_tags()) {
	$in['tags'] = qa_get_tags_field_value('tags');
}

if (qa_clicked('doask')) {
    require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
	require_once QA_INCLUDE_DIR . 'king-app/post-update.php';
    require_once QA_INCLUDE_DIR . 'king-util/string.php';
	$in['postid'] = qa_post_text('uniqueid');
	$post=qa_db_select_with_pending(qa_db_full_post_selectspec($userid, $in['postid']));
    $categoryids = array_keys(qa_category_path($categories, @$in['categoryid']));
    $userlevel   = qa_user_level_for_categories($categoryids);

    $in['nsfw']   = qa_post_text('nsfw');
    $in['prvt']   = qa_post_text('prvt');
	qa_get_post_content('editor', 'content', $in['editor'], $in['content'], $in['format'], $in['text']);

	$errors = array();
    if (!qa_check_form_security_code('ask', qa_post_text('code'))) {
        $errors['page'] = qa_lang_html('misc/form_security_again');
    } else {
        $filtermodules = qa_load_modules_with('filter', 'filter_question');
        foreach ($filtermodules as $filtermodule) {
            $oldin = $in;
            $filtermodule->filter_question($in, $errors, null);
            qa_update_post_text($in, $oldin);
        }

        if (qa_using_categories() && count($categories) && (!qa_opt('allow_no_category')) && !isset($in['categoryid'])) {
            $errors['categoryid'] = qa_lang_html('question/category_required');
        } elseif (qa_user_permit_error('permit_post_q', null, $userlevel)) {
            $errors['categoryid'] = qa_lang_html('question/category_ask_not_allowed');
        }

        if ($captchareason) {
            require_once QA_INCLUDE_DIR . 'king-app/captcha.php';
            qa_captcha_validate_post($errors);
        }

		if ( isset( $errors['title'] ) ) {
			$errors['title'] = qa_lang_html('main/title_field');
		}

        if (empty($errors)) {
            $cookieid = isset($userid) ? qa_cookie_get() : qa_cookie_get_create(); // create a new cookie if necessary

			king_update_ai_post($in['postid'], $in['title'], isset($in['tags']) ? qa_tags_to_tagstring($in['tags']) : '', $in['nsfw'], 'V');

			$answers         = qa_post_get_question_answers( $in['postid'] );
			$commentsfollows = qa_post_get_question_commentsfollows( $in['postid'] );
			$closepost       = qa_post_get_question_closepost( $in['postid'] );

			if ( qa_using_categories() && isset($in['categoryid']) ){
				qa_question_set_category( $post, $in['categoryid'], $userid, $handle, $cookieid,
					$answers, $commentsfollows, $closepost, false );
			}
            if (isset($in['prvt'])) {
                qa_post_set_hidden($in['postid'], true, null);
            }
            $response['status'] = 'success';
            $response['message'] = qa_lang_html('misc/published');
			$response['url'] = qa_q_request($in['postid'], $in['title']);
			$response['message2'] = qa_lang_html('misc/seep');
        } else {
            $response['status'] = 'error';
            $response['message'] = $errors;
        }
        echo json_encode($response); // Output response as JSON
        exit;
    }
}
	if (qa_is_logged_in() && ( qa_opt('ailimits') || qa_opt('ulimits') ) && qa_get_logged_in_level() <= QA_USER_LEVEL_ADMIN && qa_opt('enable_membership')) {
		$qa_content = qa_content_prepare();
		$mp  = qa_db_usermeta_get( $userid, 'membership_plan' );
		$pl = null;
		if ($mp) {
			$pl = (INT)qa_opt('plan_'.$mp.'_lmt');
		} elseif(qa_opt('ulimits')) {
			$pl = (INT)qa_opt('ulimit');
		}
		$alm = (INT)qa_db_usermeta_get( $userid, 'ailmt' );
		if ($alm >= $pl) {
			$qa_content['custom'] = '<div class="nopost"><i class="fa-solid fa-circle-user fa-4x"></i>'.qa_lang('misc/nocredits').'<p><a href="'.qa_path_html('membership').'">'.qa_lang('misc/buycredits').'</a></p></div>';
			return $qa_content;
		}
	}
//    Prepare content for theme

$qa_content = qa_content_prepare(false, array_keys(qa_category_path($categories, @$in['categoryid'])));

$qa_content['title'] = qa_lang_html('misc/king_aivid');
$qa_content['error'] = @$errors['page'];


$field['label'] = qa_lang_html('question/q_content_label');
$field['error'] = qa_html(@$errors['content']);

$custom = qa_opt('show_custom_ask') ? trim(qa_opt('custom_ask')) : '';


if (qa_is_logged_in()) {

$cont = '';
if ( qa_opt( 'king_leo_enable' ) && qa_opt( 'enable_aivideo' ) ) {
	$cont .= '<ul class="king-nav-kingsub-list" id="nav-kingsub">';
	if ( qa_opt( 'king_leo_enable' ) ) {
		$cont .='<li class="king-nav-kingsub-item">';
		$cont .='<a href="' . qa_path_html( 'submitai' ) . '" ><i class="fa-regular fa-image"></i> ' . qa_lang_html( 'misc/king_ai' ) . '</a>';
		$cont .='</li>';
	}
	if ( qa_opt( 'enable_aivideo' ) ) {
		$cont .='<li class="king-nav-kingsub-item">';
		$cont .='<a href="' . qa_path_html( 'videoai' ) . '" class="king-nav-kingsub-selected"><i class="fa-regular fa-circle-play"></i> ' . qa_lang_html( 'misc/king_aivid' ) . '</a>';
		$cont .='</li>';
	}
	$cont .='</ul>';
}

$cont .= '<div class="kingai-box active">
<div class="king-form-tall-error" id="ai-error" style="display: none;"></div>';
if ($custom) {
$cont .= '<div class="snote" >'.$custom.'</div>';
}
$cont .= '<div class="kingai-input">
			<textarea type="textarea" id="ai-box" class="aiinput" oninput="adjustHeight(this)" placeholder="'.qa_lang('misc/dvideo').'" maxlength="600" autocomplete="off" style="height: 44px;" rows="1"></textarea>';
$cont .= '<div class="kingai-down">';
// Define available models and their options
$models = array(
	'kst' => array(
		'enabled' => qa_opt('enable_kst'),
		'label' => qa_lang('misc/kst'),
	),

	'luma_vid' => array(
    'enabled' => qa_opt('enable_luma_vid'),
    'label' => qa_lang('misc/luma_vid'),
),
	'decart_vid' => array(
	'enabled' => qa_opt('enable_decart_vid'),
	'label' => qa_lang('misc/decart_vid'),
),


	'wan' => array(
		'enabled' => qa_opt('enable_wan'),
		'label' => qa_lang('misc/wan'),
	),
	'luma' => array(
		'enabled' => qa_opt('enable_luna'),
		'label' => qa_lang('misc/luma'),
	),
	'pixverse' => array(
		'enabled' => qa_opt('enable_pixverse'),
		'label' => qa_lang('misc/pixverse'),
	),	
	'veo' => array(
		'enabled' => false,
		'label' => qa_lang('misc/veo'),
	),
	'see' => array(
		'enabled' => qa_opt('enable_see'),
		'label' => qa_lang('misc/see'),
	),
	'veo3' => array(
		'enabled' => qa_opt('enable_veo3'),
		'label' => qa_lang('misc/veo3'),
	),
	'veo3f' => array(
		'enabled' => qa_opt('enable_veo3f'),
		'label' => qa_lang('misc/veo3f'),
	),
);

// Define enabled status variables for each model
$luna_enabled = !empty($models['luma']['enabled']);
$pixverse_enabled = !empty($models['pixverse']['enabled']);
$wan_enabled = !empty($models['wan']['enabled']);
$veo_enabled = !empty($models['veo']['enabled']);
$see_enabled = !empty($models['see']['enabled']);
$kst_enabled = !empty($models['kst']['enabled']);
$veo3_enabled = !empty($models['veo3']['enabled']);
$veo3f_enabled = !empty($models['veo3f']['enabled']);

// Filter enabled models
$enabled_models = array_filter($models, function($model) {
	return !empty($model['enabled']);
});

$model_count = count($enabled_models);
$hide_model = ($model_count <= 1) ? ' hide' : '';

// Set default model (first enabled one)
$default_key = key($enabled_models);
$default_model = $default_value = '';
if ($enabled_models) {
	$first = reset($enabled_models);
	$default_model = $first['label'];
	$default_value = $default_key;
}

// Determine the model class if only one model is enabled
if ($model_count == 1) {
	$cmodel = $default_value;
} else {
	$cmodel = '';
}
$cont .= '<div class="' . qa_html($default_value) . '" id="chclass">';
$cont .= '<div class="kingai-downleft kingai-buttons">';
if ( qa_opt( 'enable_luna_img')) {
$cont .= '<div id="newsthumb" class="dropzone king-poll-file aiupload dhpix hveo"></div>';
}


$cont .= '<div class="king-dropdownup custom-select model-select' . qa_html($hide_model) . '">
	<div class="king-sbutton kings-button" id="model-select-btn" data-toggle="dropdown" aria-expanded="false" role="button">
		<span id="model-select-label">' . qa_html($default_model) . '</span>
	</div>
	<div class="king-dropdownc king-dropleft model-options">';
if ($kst_enabled) {
	$checked = ($default_value == 'kst') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="kst" ' . $checked . '>
		<span>' . qa_lang('misc/kst') . '</span>
	</label>';
}	
if ($wan_enabled) {
	$checked = ($default_value == 'wan') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="wan" ' . $checked . '>
		<span>' . qa_lang('misc/wan') . '</span>
	</label>';
}	
if ($luna_enabled) {
	$checked = ($default_value == 'luma') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="luma" ' . $checked . '>
		<span>Luma Ray</span>
	</label>';
}
if ($pixverse_enabled) {
	$checked = ($default_value == 'pixverse') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="pixverse" ' . $checked . '>
		<span>' . qa_lang('misc/pixverse') . '</span>
	</label>';
}

if ($veo_enabled) {
	$checked = ($default_value == 'veo') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="veo" ' . $checked . '>
		<span>' . qa_lang('misc/veo') . '</span>
	</label>';
}
if ($see_enabled) {
	$checked = ($default_value == 'see') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="see" ' . $checked . '>
		<span>Seedance</span>
	</label>';
}

if ($veo3_enabled) {
	$checked = ($default_value == 'veo3') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="veo3" ' . $checked . '>
		<span>' . qa_lang('misc/veo3') . '</span>
	</label>';
}
if ($veo3f_enabled) {
	$checked = ($default_value == 'veo3f') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="veo3f" ' . $checked . '>
		<span>' . qa_lang('misc/veo3f') . '</span>
	</label>';
}

$decart_vid_enabled = !empty($models['decart_vid']['enabled']);
if ($decart_vid_enabled) {
	$checked = ($default_value == 'decart_vid') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="decart_vid" ' . $checked . '>
		<span>' . qa_lang('misc/decart_vid') . '</span>
	</label>';
}
$luma_vid_enabled = !empty($models['luma_vid']['enabled']);
if ($luma_vid_enabled) {
	$checked = ($default_value == 'luma_vid') ? 'checked' : '';
	$cont .= '<label class="cradio">
		<input type="radio" name="aimodel" value="luma_vid" ' . $checked . '>
		<span>' . qa_lang('misc/luma_vid') . '</span>
	</label>';
}
$cont .= '</div>';
$cont .= '</div>';





$cont .= '<div class="king-dropdownup custom-select hveo">
							<div class="king-sbutton kings-button" id="aivsizeb" data-toggle="dropdown" aria-expanded="false" role="button">16:9</div>
							<div class="king-dropdownc king-dropleft aivsize">                   
								<label class="cradio"><input type="radio" name="aisize" value="16:9" id="aivsize" checked class="hide"><span><i class="king-square s1"></i>16:9</span></label>
								<label class="cradio hwan"><input type="radio" name="aisize" value="4:3" class="hide"><span><i class="king-square s2"></i>4:3</span></label>
								<label class="cradio hwan dhpix"><input type="radio" name="aisize" value="1:1" class="hide"><span><i class="king-square"></i>1:1</span></label>
								<label class="cradio hwan"><input type="radio" name="aisize" value="3:4" class="hide"><span><i class="king-square s4"></i>3:4</span></label>
								<label class="cradio"><input type="radio" name="aisize" value="9:16" class="hide"><span><i class="king-square s5"></i>9:16</span></label>
							</div>
						</div>';

$cont .= '<div class="king-dropdownup custom-select video-reso-select dhpix hveo">
	<div class="king-sbutton kings-button" id="video-reso-btn" data-toggle="dropdown" aria-expanded="false" role="button">
		<span id="video-reso-label">540p</span>
	</div>
	<div class="king-dropdownc king-dropleft video-reso-options">
		<label class="cradio">
			<input type="radio" name="reso" value="540p" checked onchange="document.getElementById(\'video-reso-label\').innerText=\'540p\'">
			<span>540p</span>
		</label>
		<label class="cradio">
			<input type="radio" name="reso" value="720p" onchange="document.getElementById(\'video-reso-label\').innerText=\'720p\'">
			<span>720p</span>
		</label>
	</div>
</div>';
$cont .= '</div>';
$cont .= '</div>';
$cont .= '<div class="kingai-buttons">';
if (qa_opt('eprompter')) {
    $showElement = qa_opt('oaprompter') ? (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN) : true;
    
    if ($showElement) {
        $cont .= '<button type="button" id="prompter" onclick="aipromter(this)" class="king-sbutton ai-create promter" data-toggle="tooltip" title="' . qa_lang('misc/prompter') . '" data-placement="left"><i class="fa-solid fa-feather"></i><div class="loader"></div></button>';
    }
}
$cont .= '<button type="button" id="ai-submit" class="ai-submit" onclick="return videogenerate(this);">
<span><i class="fa-solid fa-paper-plane"></i> '.qa_lang('misc/generate').'</span><div class="loader"></div></button>';
$cont .= '</div>';
$cont .= '</div>';
	$cont .= '</div>';

$cont .= '<div id="ai-results">'.king_ai_posts($userid, 'aivid').'</div>';
$cont .= '</div>';
$qa_content['custom'] = $cont;	
$qa_content['custom'] .= '
<script>
(function(){
	function kingGetReusePayload(){
		var raw = null;
		try{ raw = sessionStorage.getItem("king_ai_reuse"); }catch(e){}
		if(!raw) return null;
		try{
			var data = JSON.parse(raw);
			try{ sessionStorage.removeItem("king_ai_reuse"); }catch(e){}
			return data;
		}catch(e){
			return null;
		}
	}

	function kingSetTextarea(id, val){
		var el = document.getElementById(id);
		if(!el) return;
		el.value = val || "";
		if(typeof adjustHeight === "function"){ try{ adjustHeight(el); }catch(e){} }
	}

	function kingSelectRadio(name, value){
		if(!value) return false;
		var input = document.querySelector(\'input[name="\' + name + \'"][value="\' + CSS.escape(value) + \'"]\');
		if(!input) return false;

		var label = input.closest("label");
		if(label && label.offsetParent === null) return false;

		input.checked = true;
		try{ input.dispatchEvent(new Event("change", {bubbles:true})); }catch(e){}
		try{ input.click(); }catch(e){}
		return true;
	}

	function kingSetVideoModel(model){
		if(!model) return;
		var input = document.querySelector(\'input[name="aimodel"][value="\' + CSS.escape(model) + \'"]\');
		if(!input) return;

		input.checked = true;
		try{ input.dispatchEvent(new Event("change", {bubbles:true})); }catch(e){}
		try{ input.click(); }catch(e){}

		// update dropdown label
		var labelEl = document.getElementById("model-select-label");
		if(labelEl){
			var lbl = input.closest("label");
			if(lbl){
				var t = (lbl.innerText || lbl.textContent || "").trim();
				if(t) labelEl.innerText = t;
			}
		}

		// update container class so css rules apply
		var ch = document.getElementById("chclass");
		if(ch) ch.className = model;
	}

	document.addEventListener("DOMContentLoaded", function(){
		var payload = kingGetReusePayload();
		if(!payload) return;

		// only apply for video page
		if(!payload.isVideo || parseInt(payload.isVideo, 10) !== 1) return;

		if(payload.prompt) kingSetTextarea("ai-box", payload.prompt);

		if(payload.model) kingSetVideoModel(payload.model);

		// size value on video page is like 16:9 9:16 etc
		if(payload.size){
			var ok = kingSelectRadio("aisize", payload.size);
			if(ok){
				var b = document.getElementById("aivsizeb");
				if(b) b.innerText = payload.size;
			}
		}

		// reso optional 540p 720p
		if(payload.reso){
			var ok2 = kingSelectRadio("reso", payload.reso);
			if(ok2){
				var r = document.getElementById("video-reso-label");
				if(r) r.innerText = payload.reso;
			}
		}

		var box = document.getElementById("ai-box");
		if(box){ try{ box.focus(); }catch(e){} }
	});
})();
</script>
';


$qa_content['form'] = array(
	'tags'    => 'name="ask" method="post" action="' . qa_self_html() . '" id="ai-form"',

	'style'   => 'tall',

	'fields'  => array(
		'close'    => array(
			'type' => 'custom',
			'html' => '<span onclick="aipublish(this)" class="aisclose"><i class="fa-solid fa-xmark"></i></span>',
		),
		'errorc'    => array(
			'type' => 'custom',
			'html' => '<div id="error-container"></div>',
		),		
		
		'title'     => array(
			'label' => qa_lang_html('question/q_title_label'),
			'tags'  => 'name="title" id="title" autocomplete="off" minlength="'.qa_opt('min_len_q_title').'"  required',
			'value' => qa_html(@$in['title']),
			'error' => qa_html(@$errors['title']),
		),

		'similar'   => array(
			'type' => 'custom',
			'html' => '<span id="similar"></span>',
		),
		'uniqueid'  => array(
			'label' => '',
			'tags'  => 'name="uniqueid" id="uniqueid" class="hide"',
		),


	),

	'buttons' => array(
		'ask' => array(
			'tags'  => 'onclick="submitAiform(event);" id="submitButton"',
			'label' => qa_lang_html('question/ask_button'),
		),
	),

	'hidden'  => array(
		'code'   => qa_get_form_security_code('ask'),
		'doask'  => '1',
	),
);

script_options($qa_content);
if (!strlen($custom)) {
	unset($qa_content['form']['fields']['custom']);
}

if (qa_opt('do_ask_check_qs') || qa_opt('do_example_tags')) {
	$qa_content['script_rel'][] = 'king-content/king-ask.js?' . QA_VERSION;
	$qa_content['form']['fields']['title']['tags'] .= ' onchange="qa_title_change(this.value);"';

	if (strlen(@$in['title'])) {
		$qa_content['script_onloads'][] = 'qa_title_change(' . qa_js($in['title']) . ');';
		

	}

}
$qa_content['script_var']['leoai']=qa_path('submitai_ajax');


if (isset($followanswer)) {
	$viewer = qa_load_viewer($followanswer['content'], $followanswer['format']);

	$field = array(
		'type'  => 'static',
		'label' => qa_lang_html('question/ask_follow_from_a'),
		'value' => $viewer->get_html($followanswer['content'], $followanswer['format'], array('blockwordspreg' => qa_get_block_words_preg())),
	);

	qa_array_insert($qa_content['form']['fields'], 'title', array('follows' => $field));
}

if (qa_using_categories() && count($categories)) {
	$field = array(
		'label' => qa_lang_html('question/q_category_label'),
		'error' => qa_html(@$errors['categoryid']),
	);

	qa_set_up_category_field($qa_content, $field, 'category', $categories, $in['categoryid'], true, qa_opt('allow_no_sub_category'));

	if (!qa_opt('allow_no_category')) // don't auto-select a category even though one is required
	{
		$field['options'][''] = '';
	}

	qa_array_insert($qa_content['form']['fields'], 'similar', array('category' => $field));
}


if (qa_using_tags()) {
	$field = array(
		'error' => qa_html(@$errors['tags']),
	);



	qa_set_up_tag_field($qa_content, $field, 'tags', isset($in['tags']) ? $in['tags'] : array(), array(),
		qa_opt('do_complete_tags') ? array_keys($completetags) : array(), qa_opt('page_size_ask_tags'));

	qa_array_insert($qa_content['form']['fields'], null, array('tags' => $field));

}


if ( qa_opt('enable_nsfw') || qa_opt('enable_pposts') ) {
	$nsfw = '';
	$prvt = '';
	if ( qa_opt('enable_pposts') ) {
		$prvt = '<input name="prvt" id="king_prvt" type="checkbox" class="hide" value="'.qa_html(@$in['prvt']).'"><label for="king_prvt" class="king-nsfw"><i class="fa-solid fa-user-ninja"></i> '.qa_lang('misc/prvt').'</label>';
	}
	if ( qa_opt('enable_nsfw') ) {
		$nsfw = '<input name="nsfw" id="king_nsfw" type="checkbox" value="'.qa_html(@$in['nsfw']).'"><label for="king_nsfw" class="king-nsfw">'.qa_lang_html('misc/nsfw').'</label>';
	}
	$field = array(
		'type' => 'custom',
		'html' => ''.$prvt.$nsfw.''
	);
	qa_array_insert($qa_content['form']['fields'], null, array('nsfw' => $field));
}

if (!isset($userid)) {
	qa_set_up_name_field($qa_content, $qa_content['form']['fields'], @$in['name']);
}


if ($captchareason) {
	require_once QA_INCLUDE_DIR . 'king-app/captcha.php';
	qa_set_up_captcha_field($qa_content, $qa_content['form']['fields'], @$errors, qa_captcha_reason_note($captchareason));
}

} else {
	$cont2  = '<div class="kingai-input">';
	$cont2 .= '<textarea type="textarea" id="ai-box" class="aiinput" data-toggle="modal" data-target="#loginmodal" placeholder="'.qa_lang('misc/aiplace').'" maxlength="600" autocomplete="off" style="height: 44px;" rows="1"></textarea>';
	$cont2 .= '<div class="kingai-buttons">';

	$cont2 .= '<button type="button" id="ai-submit" class="ai-submit" data-toggle="modal" data-target="#loginmodal">
<span><i class="fa-solid fa-paper-plane"></i> '.qa_lang('misc/generate').'</span><div class="loader"></div></button>';
	$cont2 .= '</div>';
	$cont2 .= '</div>';
	$qa_content['custom'] = $cont2;

}
$qa_content['class']=' ai-create';
$qa_content['focusid'] = 'ai-box';

return $qa_content;
/*
Omit PHP closing tag to help avoid accidental output
 */
