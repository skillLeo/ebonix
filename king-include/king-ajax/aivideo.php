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
function king_luma_aspect_ratio($imsize) {
    $imsize = trim((string)$imsize);
    $allowed = ['16:9','9:16','1:1','4:3','3:4'];
    return in_array($imsize, $allowed, true) ? $imsize : '16:9';
}

function king_luma_resolution($reso) {
    $reso = trim((string)$reso);
    $allowed = ['540p','720p','1080p','4k'];
    return in_array($reso, $allowed, true) ? $reso : '540p';
}

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
// ========== LUMA VIDEO ==========
elseif ($provider === 'luma_vid') {
    $API_KEY = qa_opt('luma_api');

    if (empty($API_KEY)) {
        $error = 'Luma API key not configured';
    } else {

        // ✅ correct endpoint for video generations (per Luma docs)
        $api_url = "https://api.lumalabs.ai/dream-machine/v1/generations/video";

        // map ui -> luma params
        $aspect = king_luma_aspect_ratio($imsize);
        $res    = king_luma_resolution($reso);

        // ✅ IMPORTANT: model is required for video
        // choose one:
        // - ray-2 (better quality)
        // - ray-flash-2 (faster)
        $payload = [
            'prompt'       => $input,
'model' => 'ray-flash-2',
            'aspect_ratio' => $aspect,
            'resolution'   => $res,
            'duration'     => '5s',
        ];

        // image-to-video (start frame)
        if (!empty($imageid)) {
            $image_info = king_get_uploads($imageid);
            if (!empty($image_info['furl'])) {
                $payload['keyframes'] = [
                    'frame0' => [
                        'type' => 'image',
                        'url'  => $image_info['furl'],
                    ]
                ];
            }
        }

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $API_KEY,
            "Content-Type: application/json",
            "Accept: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = "Luma API Error: " . curl_error($ch);
            curl_close($ch);
        } else {
            curl_close($ch);

            $out = json_decode($response, true);

            // ✅ luma errors often come in "detail"
            if ($http_code !== 200 && $http_code !== 201) {
                $msg = $out['detail'] ?? $out['error'] ?? $response;
                $error = 'Luma HTTP ' . $http_code . ': ' . (is_string($msg) ? $msg : json_encode($msg));
            } elseif (!empty($out['error'])) {
                $error = is_string($out['error']) ? $out['error'] : json_encode($out['error']);
            } elseif (!empty($out['id'])) {

                $generation_id = $out['id'];

                // poll for completion
                $max_attempts = 180; // 15 min (180 * 5s)
                $attempt = 0;
                $sleep_time = 5;

                while ($attempt < $max_attempts) {
                    sleep($sleep_time);

                    $status_url = "https://api.lumalabs.ai/dream-machine/v1/generations/{$generation_id}";
                    $ch2 = curl_init($status_url);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer " . $API_KEY,
                        "Accept: application/json",
                    ]);
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 10);

                    $status_response = curl_exec($ch2);

                    if (curl_errno($ch2)) {
                        error_log("Luma polling error: " . curl_error($ch2));
                        curl_close($ch2);
                        $attempt++;
                        continue;
                    }

                    curl_close($ch2);

                    $status = json_decode($status_response, true);

                    if (!empty($status['state']) && $status['state'] === 'completed') {
                        // assets.video is usually a direct mp4 url
                        $videourl = $status['assets']['video'] ?? '';
                        break;
                    }

                    if (!empty($status['state']) && $status['state'] === 'failed') {
                        $error = 'Luma video generation failed: ' . ($status['failure_reason'] ?? 'Unknown error');
                        break;
                    }

                    $attempt++;
                }

                if (empty($videourl) && empty($error)) {
                    $error = 'Luma video generation timed out after ' . ($max_attempts * $sleep_time) . ' seconds.';
                }

            } else {
                $error = 'Invalid response from Luma API';
            }
        }
    }
}
   // ========== OTHER VIDEO MODELS (KingStudio) ==========
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
