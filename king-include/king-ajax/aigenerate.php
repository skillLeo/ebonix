<?php
/*
File: king-include/king-ajax/aigenerate.php
Description: Server-side response to Ajax AI image generation

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
*/

// CRITICAL: Set execution time limits FIRST
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

require_once QA_INCLUDE_DIR . 'king-app/users.php';
require_once QA_INCLUDE_DIR . 'king-app/limits.php';
require_once QA_INCLUDE_DIR . 'king-db/selects.php';
require_once QA_INCLUDE_DIR . 'king-app-video.php';
require_once QA_INCLUDE_DIR . 'king-app/cookies.php';
require_once QA_INCLUDE_DIR . 'king-db/metas.php';

if (qa_is_logged_in()) {
    $userid = qa_get_logged_in_userid();
} else {
    $userid = qa_remote_ip_address();
}

$input = qa_post_text('input');
$aiselect = qa_post_text('selectElement');
$imsize = qa_post_text('radioBut');
$imageid = qa_post_text('imageid');
$chkk = true;
$error = '';

if (qa_opt('enable_membership') && (qa_opt('ailimits') || qa_opt('ulimits')) && qa_get_logged_in_level() <= QA_USER_LEVEL_ADMIN) {
    $chkk = kingai_check();
}

if (qa_opt('enable_credits') && qa_opt('post_ai')) {
    $chkk = king_spend_credit(qa_opt('post_ai'));
}

if ($input && $chkk) {
    $npvalue = (null !== qa_post_text('npvalue')) ? qa_post_text('npvalue') : '';
    $imagen = qa_opt('kingai_imgn');
    $image_urls = [];
    $gemini_processed = false;

    if ('de' === $aiselect || 'de3' === $aiselect) {
        $openaiapi = qa_opt('king_leo_api');
        $url = 'https://api.openai.com/v1/images/generations';

        if ('de3' === $aiselect) {
            $params = array(
                'model' => 'dall-e-3',
                'prompt' => $input,
                'n' => 1,
                'size' => $imsize,
            );
        } else {
            $params = array(
                'prompt' => $input,
                'n' => (int)$imagen,
                'size' => $imsize,
            );
        }
        
        $params_json = json_encode($params);
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openaiapi,
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        $response_body = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = "API Error: " . curl_error($ch);
        }
        
        curl_close($ch);

        if (!$error) {
            $response_obj = json_decode($response_body, true);
            
            if (isset($response_obj['data'])) {
                foreach ($response_obj['data'] as $image_data) {
                    $image_urls[] = $image_data['url'];
                }
            } else {
                $error = "API returned no images";
            }
        }
        
    } elseif ('imagen4' === $aiselect) {
        $API_KEY = qa_opt('gemini_api');
        $aspect_ratio = aisize_ratio($imsize);
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-generate-001:predict?key=" . $API_KEY;

        $payload = [
            "instances" => [
                ["prompt" => $input]
            ],
            "parameters" => [
                "sampleCount" => 1,
                "aspectRatio" => $aspect_ratio,
                "personGeneration" => "ALLOW_ALL",
            ]
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = "CURL ERROR: " . curl_error($ch);
        }
        curl_close($ch);

        if (!$error) {
            $data = json_decode($response, true);

            if (!isset($data["predictions"][0]["bytesBase64Encoded"])) {
                $error = "Imagen 4 did not return images";
            } else {
                require_once QA_INCLUDE_DIR . 'king-app/blobs.php';
                require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
                
                $base64 = $data["predictions"][0]["bytesBase64Encoded"];
                $image_binary = base64_decode($base64);

                $folder = 'uploads/' . date("Y") . '/' . date("m") . '/';
                $destDir = QA_INCLUDE_DIR . $folder;

                if (!file_exists($destDir)) {
                    mkdir($destDir, 0777, true);
                }

                // Save with temp_ prefix first
                $timestamp = time() . '-' . mt_rand(1000, 9999);
                $tempFilename = 'temp_gemini-image-' . $timestamp . '.webp';
                $finalFilename = 'gemini-image-' . $timestamp . '.webp';
                
                $tempPath = $destDir . $tempFilename;
                
                // Save the temp file
                file_put_contents($tempPath, $image_binary);

                // Initialize arrays
                $uploaded_images = [];
                $thumbs = [];
                
                // Create thumbnail
                $thumb_result = king_process_local_image($tempPath, $folder . $finalFilename, true, 600);
                
                // Create full image (just copy the temp file with final name)
                $fullPath = $destDir . $finalFilename;
                if (copy($tempPath, $fullPath)) {
                    // Get image dimensions
                    $imageInfo = @getimagesize($fullPath);
                    if ($imageInfo) {
                        list($width, $height) = $imageInfo;
                        
                        // Insert into database
                        if (qa_opt('enable_aws')) {
                            $url = king_upload_to_cloud($fullPath, $finalFilename, 'aws');
                            $full_result = king_insert_uploads($url, 'webp', $width, $height, 'aws');
                        } elseif (qa_opt('enable_wasabi')) {
                            $url = king_upload_to_cloud($fullPath, $finalFilename, 'wasabi');
                            $full_result = king_insert_uploads($url, 'webp', $width, $height, 'wasabi');
                        } else {
                            $full_result = king_insert_uploads($folder . $finalFilename, 'webp', $width, $height);
                        }
                        
                        if ($thumb_result && $full_result) {
                            $uploaded_images[] = $full_result;
                            $thumbs[] = $thumb_result;
                        }
                    }
                }
                
                // Clean up temp file only
                @unlink($tempPath);
                
                // Mark as processed
                $gemini_processed = true;
            }
        }

    } elseif ('banana' === $aiselect) {
        $API_KEY = qa_opt('gemini_api');
        $aspect_ratio = aisize_ratio($imsize);

        $inline_image_part = null;

        if (!empty($imageid)) {
            $image_info = king_get_uploads($imageid);
            $img_path = isset($image_info['path']) ? $image_info['path'] : '';

            if ($img_path && file_exists($img_path)) {
                $img_data = file_get_contents($img_path);
                $img_b64 = base64_encode($img_data);
                $mime = mime_content_type($img_path);

                $inline_image_part = [
                    "inline_data" => [
                        "mime_type" => $mime,
                        "data" => $img_b64
                    ]
                ];
            }
        }

        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-image-preview:generateContent?key=" . $API_KEY;

        $parts = [["text" => $input]];
        if ($inline_image_part) {
            $parts[] = $inline_image_part;
        }

        $payload = [
            "contents" => [
                ["parts" => $parts]
            ],
            "generationConfig" => [
                "imageConfig" => [
                    "aspectRatio" => $aspect_ratio
                ]
            ]
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = "CURL ERROR: " . curl_error($ch);
        }
        curl_close($ch);

        if (!$error) {
            $data = json_decode($response, true);

            if (!isset($data["candidates"][0]["content"]["parts"][0]["inlineData"]["data"])) {
                $error = "Failed to generate image.";
            } else {
                require_once QA_INCLUDE_DIR . 'king-app/blobs.php';
                require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
                
                $base64 = $data["candidates"][0]["content"]["parts"][0]["inlineData"]["data"];
                $image_binary = base64_decode($base64);

                $folder = 'uploads/' . date("Y") . '/' . date("m") . '/';
                $destDir = QA_INCLUDE_DIR . $folder;

                if (!file_exists($destDir)) {
                    mkdir($destDir, 0777, true);
                }

                // Save with temp_ prefix first
                $timestamp = time() . '-' . mt_rand(1000, 9999);
                $tempFilename = 'temp_gemini-image-' . $timestamp . '.webp';
                $finalFilename = 'gemini-image-' . $timestamp . '.webp';
                
                $tempPath = $destDir . $tempFilename;
                
                // Save the temp file
                file_put_contents($tempPath, $image_binary);

                // Initialize arrays
                $uploaded_images = [];
                $thumbs = [];
                
                // Create thumbnail
                $thumb_result = king_process_local_image($tempPath, $folder . $finalFilename, true, 600);
                
                // Create full image (just copy the temp file with final name)
                $fullPath = $destDir . $finalFilename;
                if (copy($tempPath, $fullPath)) {
                    // Get image dimensions
                    $imageInfo = @getimagesize($fullPath);
                    if ($imageInfo) {
                        list($width, $height) = $imageInfo;
                        
                        // Insert into database
                        if (qa_opt('enable_aws')) {
                            $url = king_upload_to_cloud($fullPath, $finalFilename, 'aws');
                            $full_result = king_insert_uploads($url, 'webp', $width, $height, 'aws');
                        } elseif (qa_opt('enable_wasabi')) {
                            $url = king_upload_to_cloud($fullPath, $finalFilename, 'wasabi');
                            $full_result = king_insert_uploads($url, 'webp', $width, $height, 'wasabi');
                        } else {
                            $full_result = king_insert_uploads($folder . $finalFilename, 'webp', $width, $height);
                        }
                        
                        if ($thumb_result && $full_result) {
                            $uploaded_images[] = $full_result;
                            $thumbs[] = $thumb_result;
                        }
                    }
                }
                
                // Clean up temp file only
                @unlink($tempPath);
                
                // Mark as processed
                $gemini_processed = true;
            }
        }

    } else {
        // Stable Diffusion and other models
        $sdapi = qa_opt('king_sd_api');
        $aistyle = qa_post_text('aistyle');
        $aisteps = qa_opt('king_sd_steps');
        
        $URL = "https://kingstudio.io/api/king-text2img";

        $style_preset = (isset($aistyle) && 'none' !== $aistyle) ? $aistyle : '';

        $request_data = [
            "prompt" => $input . ($style_preset ? ', ' . $style_preset : ''),
            "size" => (int)$imagen,
            "steps" => (int)$aisteps,
            "aisize" => $imsize,
            "model" => $aiselect,
            "nvalue" => $npvalue,
            "ennsfw" => qa_opt('ennsfw') ? true : false,
            "sdnsfw" => qa_opt('sdnsfw') ? true : false,
        ];

        if ($imageid && ($aiselect === 'fluxkon' || $aiselect === 'sdream')) {
            $imageurl = king_get_uploads($imageid);
            $request_data['image'] = $imageurl['furl'];
        }

        $ch = curl_init($URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $sdapi",
            "Accept: application/json",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
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
                $image_urls = $out['out'] ?? [];
            }
        }
    }

    // Process results
    if (isset($error) && $error) {
        $output = json_encode(array('success' => false, 'message' => $error));
        echo "QA_AJAX_RESPONSE\n0\n";
        echo $output . "\n";
    } else {
        require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
        
        // Only process if not already done (for Gemini models)
        if (!$gemini_processed) {
            $uploaded_images = [];
            $thumbs = [];
            
            // Process images with error handling
            foreach ($image_urls as $image_url) {
                try {
                    // Thumbnail
                    $thumb = king_urlupload($image_url, true, 600);
                    if (!empty($thumb)) {
                        $thumbs[] = $thumb;
                    }
                    
                    // Full image
                    $upload_response = king_urlupload($image_url);
                    if (!empty($upload_response)) {
                        $uploaded_images[] = $upload_response;
                    }
                } catch (Exception $e) {
                    error_log("Image upload failed: " . $e->getMessage());
                    continue;
                }
            }
        }
        
        if (empty($uploaded_images)) {
            $output = json_encode(array('success' => false, 'message' => 'Failed to upload images'));
            echo "QA_AJAX_RESPONSE\n0\n";
            echo $output . "\n";
        } else {
            $extra = serialize($uploaded_images);
            $thumb = end($thumbs);
            $cookieid = isset($userid) ? qa_cookie_get() : qa_cookie_get_create();
            
            $postid = qa_question_create(null, $userid, qa_get_logged_in_handle(), $cookieid, null, $thumb, '', null, null, null, null, null, $extra, 'NOTE', null, 'aimg', $input, null);

            qa_db_postmeta_set($postid, 'wai', true);
            qa_db_postmeta_set($postid, 'model', $aiselect);
            
            if ($npvalue) {
                qa_db_postmeta_set($postid, 'nprompt', $npvalue);
            }
            if (isset($style_preset) && $style_preset) {
                qa_db_postmeta_set($postid, 'stle', $style_preset);
            }
            if (isset($imsize)) {
                qa_db_postmeta_set($postid, 'asize', $imsize);
            }
            if ($imageid && ($aiselect === 'fluxkon' || $aiselect === 'sdream' || $aiselect === 'banana')) {
                qa_db_postmeta_set($postid, 'pimage', $imageid);
            }
            
            if (qa_opt('enable_membership') && (qa_opt('ailimits') || qa_opt('ulimits'))) {
                kingai_imagen($imagen);
            }
            
            $output = json_encode(array(
                'success' => true,
                'postid' => $postid,
            ));

            echo "QA_AJAX_RESPONSE\n1\n";
            echo $output . "\n";
            echo king_ai_posts($userid, 'aimg');
        }
    }

} else {
    $outputz = json_encode(array('success' => false, 'message' => qa_lang_html('misc/nocredits')));
    echo "QA_AJAX_RESPONSE\n0\n";
    echo $outputz . "\n";
}