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

$input    = qa_post_text('input');
$aiselect = qa_post_text('selectElement');
$imsize   = qa_post_text('radioBut');
$imageid  = qa_post_text('imageid');

$chkk  = true;
$error = '';

// membership/limits
if (qa_opt('enable_membership') && (qa_opt('ailimits') || qa_opt('ulimits')) && qa_get_logged_in_level() <= QA_USER_LEVEL_ADMIN) {
    $chkk = kingai_check();
}

// credits
if (qa_opt('enable_credits') && qa_opt('post_ai')) {
    $chkk = king_spend_credit(qa_opt('post_ai'));
}

/* =========================================================
   LUMA HELPERS (IMAGE)
   Luma Image endpoint supports: 1:1, 3:4, 4:3, 9:16, 16:9, 9:21, 21:9
   ========================================================= */

if (!function_exists('king_luma_image_aspect_ratio')) {
    function king_luma_image_aspect_ratio($imsize)
    {
        // map your UI sizes -> supported Luma ratios
        $map = [
            '1344x768'  => '16:9',  // widescreen
            '1792x1024' => '16:9',  // 7:4 -> closest
            '1152x896'  => '4:3',   // 5:4 -> closest
            '512x512'   => '1:1',
            '1024x1024' => '1:1',
            '896x1152'  => '3:4',   // 4:5 -> closest
            '832x1216'  => '3:4',   // 2:3 -> closest
            '768x1344'  => '9:16',
            '1024x1792' => '9:16',  // 4:7 -> closest
        ];

        if (!empty($map[$imsize])) {
            return $map[$imsize];
        }

        // fallback: compute & choose nearest supported ratio
        $supported = [
            '1:1'  => 1.0,
            '3:4'  => 3/4,
            '4:3'  => 4/3,
            '9:16' => 9/16,
            '16:9' => 16/9,
            '9:21' => 9/21,
            '21:9' => 21/9,
        ];

        if (preg_match('~^(\d+)x(\d+)$~', (string)$imsize, $m)) {
            $w = (int)$m[1];
            $h = (int)$m[2];
            if ($w > 0 && $h > 0) {
                $r = $w / $h;
                $bestKey = '1:1';
                $bestDiff = PHP_FLOAT_MAX;
                foreach ($supported as $k => $val) {
                    $diff = abs($r - $val);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestKey = $k;
                    }
                }
                return $bestKey;
            }
        }

        return '1:1';
    }
}

if (!function_exists('king_luma_download_file')) {
    function king_luma_download_file($url, $destPath, &$err = '')
    {
        $fp = @fopen($destPath, 'w');
        if (!$fp) {
            $err = 'Failed to create file for download.';
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $ok = curl_exec($ch);
        $curlErr = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if (!$ok || !empty($curlErr) || $code >= 400) {
            @unlink($destPath);
            $err = "Download failed. HTTP {$code}. " . ($curlErr ?: '');
            return false;
        }

        if (!file_exists($destPath) || filesize($destPath) < 5000) {
            @unlink($destPath);
            $err = 'Downloaded file is too small or missing.';
            return false;
        }

        return true;
    }
}



/* =========================
   LUMA HELPERS (IMAGE)
   ========================= */

   /* =========================================================
      LUMA HELPERS (IMAGE) - KEEP ONLY ONE COPY
      ========================================================= */
   
   if (!function_exists('king_luma_clean_key')) {
       function king_luma_clean_key($key)
       {
           $key = trim((string)$key);
           $key = preg_replace('~^Bearer\s+~i', '', $key);
           return trim($key);
       }
   }
   
   if (!function_exists('king_luma_pick_aspect_ratio')) {
       function king_luma_pick_aspect_ratio($imsize)
       {
           // Luma supports: 1:1, 3:4, 4:3, 9:16, 16:9, 9:21, 21:9
           $supported = [
               '1:1'  => 1.0,
               '3:4'  => 3/4,
               '4:3'  => 4/3,
               '9:16' => 9/16,
               '16:9' => 16/9,
               '9:21' => 9/21,
               '21:9' => 21/9,
           ];
   
           $s = trim((string)$imsize);
   
           // if UI sends "Square (1:1)" etc
           if (preg_match('~(\d+\s*:\s*\d+)~', $s, $m)) {
               $ratio = str_replace(' ', '', $m[1]);
               return isset($supported[$ratio]) ? $ratio : '16:9';
           }
   
           // if UI sends "9:16"
           if (preg_match('~^\d+\s*:\s*\d+$~', $s)) {
               $ratio = str_replace(' ', '', $s);
               return isset($supported[$ratio]) ? $ratio : '16:9';
           }
   
           // if UI sends "1024x1024"
           if (preg_match('~^(\d+)x(\d+)$~', $s, $m)) {
               $w = (int)$m[1];
               $h = (int)$m[2];
               if ($w > 0 && $h > 0) {
                   $r = $w / $h;
                   $bestKey = '16:9';
                   $bestDiff = PHP_FLOAT_MAX;
                   foreach ($supported as $k => $val) {
                       $diff = abs($r - $val);
                       if ($diff < $bestDiff) {
                           $bestDiff = $diff;
                           $bestKey = $k;
                       }
                   }
                   return $bestKey;
               }
           }
   
           return '16:9';
       }
   }
   
   if (!function_exists('king_luma_request_json')) {
       function king_luma_request_json($method, $url, $apiKey, $payload = null, &$http = 0, &$raw = '', &$curlErr = '')
       {
           $ch = curl_init($url);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
           curl_setopt($ch, CURLOPT_TIMEOUT, 60);
           curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
   
           $headers = [
               "Authorization: Bearer {$apiKey}",
               "Accept: application/json",
           ];
   
           $method = strtoupper($method);
   
           if ($method === 'POST') {
               curl_setopt($ch, CURLOPT_POST, true);
               $headers[] = "Content-Type: application/json";
               curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
           } elseif ($method !== 'GET') {
               curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
           }
   
           curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
   
           $raw = curl_exec($ch);
           $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
   
           if (curl_errno($ch)) {
               $curlErr = curl_error($ch);
           }
   
           curl_close($ch);
   
           $json = @json_decode((string)$raw, true);
           return is_array($json) ? $json : null;
       }
   }
   
   if (!function_exists('king_luma_download_file')) {
       function king_luma_download_file($url, $destPath, &$err = '')
       {
           $fp = @fopen($destPath, 'w');
           if (!$fp) {
               $err = 'Failed to create file for download.';
               return false;
           }
   
           $ch = curl_init($url);
           curl_setopt($ch, CURLOPT_FILE, $fp);
           curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
           curl_setopt($ch, CURLOPT_TIMEOUT, 180);
           curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
           curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
           curl_setopt($ch, CURLOPT_USERAGENT, 'KingAI/1.0');
   
           $ok = curl_exec($ch);
           $curlErr = curl_error($ch);
           $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
   
           curl_close($ch);
           fclose($fp);
   
           // fallback: some servers have broken CA bundle
           if ((!$ok || !empty($curlErr) || $code >= 400) && stripos($curlErr, 'SSL') !== false) {
               @unlink($destPath);
   
               $fp = @fopen($destPath, 'w');
               if (!$fp) {
                   $err = 'Failed to create file for download (ssl fallback).';
                   return false;
               }
   
               $ch = curl_init($url);
               curl_setopt($ch, CURLOPT_FILE, $fp);
               curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
               curl_setopt($ch, CURLOPT_TIMEOUT, 180);
               curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
               curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
               curl_setopt($ch, CURLOPT_USERAGENT, 'KingAI/1.0');
   
               $ok = curl_exec($ch);
               $curlErr = curl_error($ch);
               $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
   
               curl_close($ch);
               fclose($fp);
           }
   
           if (!$ok || !empty($curlErr) || $code >= 400) {
               @unlink($destPath);
               $err = "Download failed. HTTP {$code}. " . ($curlErr ?: '');
               return false;
           }
   
           if (!file_exists($destPath) || filesize($destPath) < 5000) {
               @unlink($destPath);
               $err = 'Downloaded file is too small or missing.';
               return false;
           }
   
           return true;
       }
   }
   
   if (!function_exists('king_luma_is_transient_failure')) {
       function king_luma_is_transient_failure($reason)
       {
           $r = strtolower(trim((string)$reason));
           if ($r === '') return false;
   
           // from luma docs: these are often temporary infra issues
           $transient = [
               'job failed',
               'error dispatching job',
               'prompt processing failed',
               'error processing callback',
           ];
   
           return in_array($r, $transient, true);
       }
   }
   
   if (!function_exists('king_luma_error_detail')) {
       function king_luma_error_detail($json, $raw)
       {
           if (is_array($json)) {
               if (!empty($json['detail'])) return (string)$json['detail'];
               if (!empty($json['error'])) return is_string($json['error']) ? $json['error'] : json_encode($json['error']);
               if (!empty($json['message'])) return (string)$json['message'];
           }
           $raw = trim((string)$raw);
           return $raw ? substr($raw, 0, 300) : 'Unknown error';
       }
   }
   
   /* =========================
      ✅ LUMA IMAGE (RETRY + REAL ERRORS)
      ========================= */
    elseif ('luma_img' === $aiselect) {
   
       $API_KEY = king_luma_clean_key(qa_opt('luma_api'));
   
       if (empty($API_KEY)) {
           $error = 'Luma API key not configured';
       } else {
   
           $api_url = "https://api.lumalabs.ai/dream-machine/v1/generations/image";
   
           $prompt = trim((string)$input);
           if (strlen($prompt) < 3) {
               $error = 'Prompt is too short (minimum 3 characters)';
           } elseif (strlen($prompt) > 5000) {
               $error = 'Prompt is too long (maximum 5000 characters)';
           } else {
   
               // luma has no negative_prompt param -> append safely
               if (!empty($npvalue)) {
                   $prompt .= "\n\nAvoid: " . trim((string)$npvalue);
               }
   
               $aspect_ratio = king_luma_pick_aspect_ratio($imsize);
   
               // use flash first (usually more stable + faster), then photon-1
               $models_to_try = ['photon-flash-1', 'photon-1'];
   
               // HARD deadline so we never exceed php limit
               $deadline = time() + 280; // keep 20s buffer from 300s
   
               $saved = false;
               $lastFailure = '';
               $attemptJob = 0;
               $maxJobRetries = 2; // total jobs created (2) - prevents infinite loops
   
               while (!$saved && $attemptJob < $maxJobRetries && time() < $deadline) {
                   $attemptJob++;
   
                   // ---------- CREATE GENERATION (try models) ----------
                   $generation_id = null;
                   $create_err = '';
   
                   foreach ($models_to_try as $try_model) {
   
                       $payload = [
                           'prompt'       => $prompt,
                           'aspect_ratio' => $aspect_ratio,
                           'model'        => $try_model,
                       ];
   
                       // optional image-to-image (must be public URL)
                       if (!empty($imageid)) {
                           $image_info = king_get_uploads($imageid);
                           $publicUrl = $image_info['furl'] ?? '';
                           if (!empty($publicUrl) && preg_match('~^https?://~i', $publicUrl)) {
                               $payload['modify_image_ref'] = [
                                   'url'    => $publicUrl,
                                   'weight' => 1.0,
                               ];
                           }
                       }
   
                       $http = 0; $raw = ''; $curlErr = '';
                       $out = king_luma_request_json('POST', $api_url, $API_KEY, $payload, $http, $raw, $curlErr);
   
                       if (!empty($curlErr)) {
                           $create_err = "Luma CURL error: {$curlErr}";
                           continue;
                       }
   
                       if ($http === 200 || $http === 201) {
                           if (!empty($out['id'])) {
                               $generation_id = $out['id'];
                               break;
                           }
                           $create_err = "Luma invalid response (missing id): " . substr($raw, 0, 250);
                           continue;
                       }
   
                       $create_err = "Luma HTTP {$http}: " . king_luma_error_detail($out, $raw);
                       // try next model
                   }
   
                   if (empty($generation_id)) {
                       $error = $create_err ?: 'Failed to create Luma generation';
                       break;
                   }
   
                   // ---------- POLL ----------
                   $pollSleep = 3; // faster feedback
                   $state = '';
                   $failure_reason = '';
   
                   while (time() < $deadline) {
                       sleep($pollSleep);
   
                       $status_url = "https://api.lumalabs.ai/dream-machine/v1/generations/{$generation_id}";
                       $http = 0; $raw = ''; $curlErr = '';
                       $status = king_luma_request_json('GET', $status_url, $API_KEY, null, $http, $raw, $curlErr);
   
                       if (!empty($curlErr) || $http >= 400 || !is_array($status)) {
                           // transient polling issue - keep polling
                           continue;
                       }
   
                       $state = strtolower((string)($status['state'] ?? ''));
   
                       if ($state === 'completed') {
   
                           $img_url = $status['assets']['image'] ?? '';
   
                           if (empty($img_url) || !is_string($img_url)) {
                               $error = 'Luma completed but returned no image url';
                               break;
                           }
   
                           require_once QA_INCLUDE_DIR . 'king-app/blobs.php';
                           require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
   
                           $folder  = 'uploads/' . date("Y") . '/' . date("m") . '/';
                           $destDir = QA_INCLUDE_DIR . $folder;
   
                           if (!file_exists($destDir)) {
                               mkdir($destDir, 0777, true);
                           }
   
                           $stamp = time() . '-' . mt_rand(1000, 9999);
                           $path = parse_url($img_url, PHP_URL_PATH);
                           $ext  = strtolower(pathinfo($path ?: '', PATHINFO_EXTENSION));
                           if (!$ext) $ext = 'jpg';
                           if ($ext === 'jpeg') $ext = 'jpg';
   
                           $tempPath = $destDir . "temp_luma_{$stamp}.{$ext}";
                           $finalFilename = "luma-img-{$stamp}.{$ext}";
                           $finalPath = $destDir . $finalFilename;
   
                           $dlErr = '';
                           if (!king_luma_download_file($img_url, $tempPath, $dlErr)) {
                               $error = "Failed to download Luma image: {$dlErr}";
                               break;
                           }
   
                           $imageInfo = @getimagesize($tempPath);
                           if (!$imageInfo) {
                               @unlink($tempPath);
                               $error = "Downloaded Luma image is not a valid image.";
                               break;
                           }
   
                           list($w, $h) = $imageInfo;
   
                           $uploaded_images = [];
                           $thumbs = [];
   
                           // Create thumb + final file
                           $thumb_result = king_process_local_image($tempPath, $folder . $finalFilename, true, 600);
   
                           if (!copy($tempPath, $finalPath)) {
                               @unlink($tempPath);
                               $error = "Failed to move Luma image into uploads folder.";
                               break;
                           }
   
                           if (qa_opt('enable_aws')) {
                               $url = king_upload_to_cloud($finalPath, $finalFilename, 'aws');
                               $full_result = king_insert_uploads($url, $ext, $w, $h, 'aws');
                           } elseif (qa_opt('enable_wasabi')) {
                               $url = king_upload_to_cloud($finalPath, $finalFilename, 'wasabi');
                               $full_result = king_insert_uploads($url, $ext, $w, $h, 'wasabi');
                           } else {
                               $full_result = king_insert_uploads($folder . $finalFilename, $ext, $w, $h);
                           }
   
                           @unlink($tempPath);
   
                           if ($thumb_result && $full_result) {
                               $uploaded_images[] = $full_result;
                               $thumbs[] = $thumb_result;
                               $gemini_processed = true; // skip urlupload block
                               $saved = true;
                           } else {
                               $error = "Failed to save Luma image to database.";
                           }
   
                           break;
                       }
   
                       if ($state === 'failed') {
                           $failure_reason = $status['failure_reason'] ?? 'Unknown error';
                           $lastFailure = $failure_reason;
   
                           // IMPORTANT: retry only if it is transient
                           if (king_luma_is_transient_failure($failure_reason) && time() < $deadline && $attemptJob < $maxJobRetries) {
                               // break poll loop -> create a new job
                               break;
                           }
   
                           $error = 'Luma generation failed: ' . $failure_reason;
                           break;
                       }
   
                       // queued / dreaming / processing -> continue polling
                   }
   
                   if (!empty($error) || $saved) {
                       break;
                   }
   
                   // if we exited polling because transient fail -> loop creates new job
               }
   
               if (!$saved && empty($error)) {
                   if (!empty($lastFailure)) {
                       $error = 'Luma generation failed: ' . $lastFailure;
                   } else {
                       $error = 'Luma image generation timed out (no completed state within limit)';
                   }
               }
           }
       }
   }
   

if (!function_exists('king_luma_pick_aspect_ratio')) {
    function king_luma_pick_aspect_ratio($imsize)
    {
        // Luma supports: 1:1, 3:4, 4:3, 9:16, 16:9, 9:21, 21:9
        $supported = [
            '1:1'  => 1.0,
            '3:4'  => 3/4,
            '4:3'  => 4/3,
            '9:16' => 9/16,
            '16:9' => 16/9,
            '9:21' => 9/21,
            '21:9' => 21/9,
        ];

        $s = (string)$imsize;

        // If UI sends like "Long (9:16)" or "Widescreen (16:9)"
        if (preg_match('~(\d+\s*:\s*\d+)~', $s, $m)) {
            $ratio = str_replace(' ', '', $m[1]);
            return isset($supported[$ratio]) ? $ratio : '16:9';
        }

        // If UI sends direct "9:16"
        if (preg_match('~^\d+\s*:\s*\d+$~', trim($s))) {
            $ratio = str_replace(' ', '', trim($s));
            return isset($supported[$ratio]) ? $ratio : '16:9';
        }

        // If UI sends "768x1344"
        if (preg_match('~^(\d+)x(\d+)$~', trim($s), $m)) {
            $w = (int)$m[1];
            $h = (int)$m[2];
            if ($w > 0 && $h > 0) {
                $r = $w / $h;
                $bestKey = '16:9';
                $bestDiff = PHP_FLOAT_MAX;
                foreach ($supported as $k => $val) {
                    $diff = abs($r - $val);
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestKey = $k;
                    }
                }
                return $bestKey;
            }
        }

        // default Luma is 16:9
        return '16:9';
    }
}

if (!function_exists('king_luma_request_json')) {
    function king_luma_request_json($method, $url, $apiKey, $payload = null, &$http = 0, &$raw = '', &$curlErr = '')
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

        $headers = [
            "Authorization: Bearer {$apiKey}",
            "Accept: application/json",
        ];

        $method = strtoupper($method);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $headers[] = "Content-Type: application/json";
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $curlErr = curl_error($ch);
        }

        curl_close($ch);

        $json = @json_decode((string)$raw, true);
        return is_array($json) ? $json : null;
    }
}

if (!function_exists('king_luma_download_file')) {
    function king_luma_download_file($url, $destPath, &$err = '')
    {
        $fp = @fopen($destPath, 'w');
        if (!$fp) {
            $err = 'Failed to create file for download.';
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $ok = curl_exec($ch);
        $curlErr = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if (!$ok || !empty($curlErr) || $code >= 400) {
            @unlink($destPath);
            $err = "Download failed. HTTP {$code}. " . ($curlErr ?: '');
            return false;
        }

        if (!file_exists($destPath) || filesize($destPath) < 5000) {
            @unlink($destPath);
            $err = 'Downloaded file is too small or missing.';
            return false;
        }

        return true;
    }
}


if ($input && $chkk) {
    $npvalue = (null !== qa_post_text('npvalue')) ? qa_post_text('npvalue') : '';
    $imagen  = qa_opt('kingai_imgn');
    $image_urls = [];
    $gemini_processed = false;

    /* =========================
       OPENAI (DALL·E)
       ========================= */
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
                    if (!empty($image_data['url'])) {
                        $image_urls[] = $image_data['url'];
                    }
                }
            } else {
                $error = "API returned no images";
            }
        }

    /* =========================
       IMAGEN 4
       ========================= */
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

                $timestamp = time() . '-' . mt_rand(1000, 9999);
                $tempFilename = 'temp_gemini-image-' . $timestamp . '.webp';
                $finalFilename = 'gemini-image-' . $timestamp . '.webp';

                $tempPath = $destDir . $tempFilename;

                file_put_contents($tempPath, $image_binary);

                $uploaded_images = [];
                $thumbs = [];

                $thumb_result = king_process_local_image($tempPath, $folder . $finalFilename, true, 600);

                $fullPath = $destDir . $finalFilename;
                if (copy($tempPath, $fullPath)) {
                    $imageInfo = @getimagesize($fullPath);
                    if ($imageInfo) {
                        list($width, $height) = $imageInfo;

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

                @unlink($tempPath);

                $gemini_processed = true;
            }
        }

    /* =========================
       GEMINI IMAGE PREVIEW (banana)
       ========================= */
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

                $timestamp = time() . '-' . mt_rand(1000, 9999);
                $tempFilename = 'temp_gemini-image-' . $timestamp . '.webp';
                $finalFilename = 'gemini-image-' . $timestamp . '.webp';

                $tempPath = $destDir . $tempFilename;

                file_put_contents($tempPath, $image_binary);

                $uploaded_images = [];
                $thumbs = [];

                $thumb_result = king_process_local_image($tempPath, $folder . $finalFilename, true, 600);

                $fullPath = $destDir . $finalFilename;
                if (copy($tempPath, $fullPath)) {
                    $imageInfo = @getimagesize($fullPath);
                    if ($imageInfo) {
                        list($width, $height) = $imageInfo;

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

                @unlink($tempPath);

                $gemini_processed = true;
            }
        }

    /* =========================
       DECART IMAGE
       ========================= */
    } elseif ('decart_img' === $aiselect) {
        $API_KEY = qa_opt('decart_api');

        if (empty($API_KEY)) {
            $error = 'Decart API key not configured';
        } else {
            $api_url = "https://api.decart.ai/v1/generate/lucy-pro-t2i";

            $post_fields = array(
                'prompt' => $input
            );

            if (!empty($npvalue)) {
                $post_fields['negative_prompt'] = $npvalue;
            }

            if (!empty($imageid)) {
                $api_url = "https://api.decart.ai/v1/generate/lucy-pro-i2i";
                $image_info = king_get_uploads($imageid);
                $img_path = isset($image_info['path']) ? $image_info['path'] : '';

                if ($img_path && file_exists($img_path)) {
                    $post_fields['data'] = new CURLFile($img_path);
                }
            }

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "X-API-KEY: $API_KEY"
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $image_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $error = "Decart API Error: " . curl_error($ch);
            }

            curl_close($ch);

            if (!$error) {
                if ($http_code !== 200) {
                    $json_error = @json_decode($image_data, true);
                    $error = 'Decart HTTP ' . $http_code . ': ' . ($json_error['error']['message'] ?? substr($image_data, 0, 200));
                } elseif (empty($image_data)) {
                    $error = 'Decart returned empty response';
                } else {
                    require_once QA_INCLUDE_DIR . 'king-app/blobs.php';
                    require_once QA_INCLUDE_DIR . 'king-app/post-create.php';

                    $folder = 'uploads/' . date("Y") . '/' . date("m") . '/';
                    $destDir = QA_INCLUDE_DIR . $folder;

                    if (!file_exists($destDir)) {
                        mkdir($destDir, 0777, true);
                    }

                    $timestamp = time() . '-' . mt_rand(1000, 9999);
                    $finalFilename = 'decart-img-' . $timestamp . '.png';
                    $tempPath = $destDir . 'temp_' . $finalFilename;
                    $fullPath = $destDir . $finalFilename;

                    file_put_contents($tempPath, $image_data);

                    $imageInfo = @getimagesize($tempPath);
                    if (!$imageInfo) {
                        $error = 'Decart returned invalid image data';
                        @unlink($tempPath);
                    } else {
                        $uploaded_images = [];
                        $thumbs = [];

                        $thumb_result = king_process_local_image($tempPath, $folder . $finalFilename, true, 600);

                        if (copy($tempPath, $fullPath)) {
                            list($img_width, $img_height) = $imageInfo;

                            if (qa_opt('enable_aws')) {
                                $url = king_upload_to_cloud($fullPath, $finalFilename, 'aws');
                                $full_result = king_insert_uploads($url, 'png', $img_width, $img_height, 'aws');
                            } elseif (qa_opt('enable_wasabi')) {
                                $url = king_upload_to_cloud($fullPath, $finalFilename, 'wasabi');
                                $full_result = king_insert_uploads($url, 'png', $img_width, $img_height, 'wasabi');
                            } else {
                                $full_result = king_insert_uploads($folder . $finalFilename, 'png', $img_width, $img_height);
                            }

                            if ($thumb_result && $full_result) {
                                $uploaded_images[] = $full_result;
                                $thumbs[] = $thumb_result;
                            }
                        }

                        @unlink($tempPath);
                        $gemini_processed = true;
                    }
                }
            }
        }

    /* =========================
       ✅ LUMA IMAGE (FIXED)
       ========================= */
    } elseif ('luma_img' === $aiselect) {

        $API_KEY = king_luma_clean_key(qa_opt('luma_api'));
    
        if (empty($API_KEY)) {
            $error = 'Luma API key not configured';
        } else {
    
            // Luma Image endpoint (official)
            $api_url = "https://api.lumalabs.ai/dream-machine/v1/generations/image";
    
            // Make aspect ratio safe for any UI value
            $aspect_ratio = king_luma_pick_aspect_ratio($imsize);
    
            // Luma has no native negative_prompt, so we append it safely
            $prompt = (string)$input;
            if (!empty($npvalue)) {
                $prompt .= "\n\nAvoid: " . trim((string)$npvalue);
            }
    
            // Try models in order (some accounts only allow flash)
            $models_to_try = ['photon-1', 'photon-flash-1'];
    
            $generation_id = null;
            $create_err = '';
    
            foreach ($models_to_try as $try_model) {
    
                $payload = [
                    'prompt'       => $prompt,
                    'aspect_ratio' => $aspect_ratio,
                    'model'        => $try_model,
                ];
    
                // OPTIONAL modify image (must be PUBLIC url)
                if (!empty($imageid)) {
                    $image_info = king_get_uploads($imageid);
                    $publicUrl = $image_info['furl'] ?? '';
    
                    if (!empty($publicUrl) && preg_match('~^https?://~i', $publicUrl)) {
                        $payload['modify_image_ref'] = [
                            'url'    => $publicUrl,
                            'weight' => 1.0,
                        ];
                    }
                }
    
                $http = 0; $raw = ''; $curlErr = '';
                $out = king_luma_request_json('POST', $api_url, $API_KEY, $payload, $http, $raw, $curlErr);
    
                if (!empty($curlErr)) {
                    $create_err = "Luma CURL error: {$curlErr}";
                    continue;
                }
    
                if ($http === 200 || $http === 201) {
                    if (!empty($out['id'])) {
                        $generation_id = $out['id'];
                        break;
                    }
                    $create_err = "Luma invalid response (missing id): " . substr($raw, 0, 250);
                    continue;
                }
    
                // capture most useful error message
                $detail = '';
                if (is_array($out)) {
                    $detail = $out['detail'] ?? ($out['error'] ?? '');
                }
                if (!$detail) $detail = substr((string)$raw, 0, 250);
    
                // if model not allowed / no access, try next model
                $create_err = "Luma HTTP {$http}: {$detail}";
    
                // If it is clearly "no access" do not keep retrying forever (but we still try flash once)
                // just continue to next model
            }
    
            if (empty($generation_id)) {
                $error = $create_err ?: 'Failed to create Luma image generation';
            } else {
    
                // Poll status
                $max_attempts = 75; // 75*4 = 300s
                $sleep_time   = 4;
    
                for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
    
                    sleep($sleep_time);
    
                    $status_url = "https://api.lumalabs.ai/dream-machine/v1/generations/{$generation_id}";
    
                    $http = 0; $raw = ''; $curlErr = '';
                    $status = king_luma_request_json('GET', $status_url, $API_KEY, null, $http, $raw, $curlErr);
    
                    if (!empty($curlErr)) {
                        // keep polling; transient network issue
                        continue;
                    }
                    if ($http >= 400 || !is_array($status)) {
                        continue;
                    }
    
                    $state = strtolower((string)($status['state'] ?? ''));
    
                    if ($state === 'completed') {
    
                        // Luma returns assets.image as a STRING url
                        $img_url = $status['assets']['image'] ?? '';
    
                        if (empty($img_url) || !is_string($img_url)) {
                            $error = 'Luma completed but returned no image url';
                            break;
                        }
    
                        require_once QA_INCLUDE_DIR . 'king-app/blobs.php';
                        require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
    
                        $folder  = 'uploads/' . date("Y") . '/' . date("m") . '/';
                        $destDir = QA_INCLUDE_DIR . $folder;
    
                        if (!file_exists($destDir)) {
                            mkdir($destDir, 0777, true);
                        }
    
                        $stamp = time() . '-' . mt_rand(1000, 9999);
    
                        $path = parse_url($img_url, PHP_URL_PATH);
                        $ext  = strtolower(pathinfo($path ?: '', PATHINFO_EXTENSION));
                        if (!$ext) $ext = 'jpg';
                        if ($ext === 'jpeg') $ext = 'jpg';
    
                        $tempPath = $destDir . "temp_luma_{$stamp}.{$ext}";
                        $finalFilename = "luma-img-{$stamp}.{$ext}";
                        $finalPath = $destDir . $finalFilename;
    
                        $dlErr = '';
                        if (!king_luma_download_file($img_url, $tempPath, $dlErr)) {
                            $error = "Failed to download Luma image: {$dlErr}";
                            break;
                        }
    
                        $imageInfo = @getimagesize($tempPath);
                        if (!$imageInfo) {
                            @unlink($tempPath);
                            $error = "Downloaded Luma image is not a valid image.";
                            break;
                        }
    
                        list($w, $h) = $imageInfo;
    
                        $uploaded_images = [];
                        $thumbs = [];
    
                        // Create thumb
                        $thumb_result = king_process_local_image($tempPath, $folder . $finalFilename, true, 600);
    
                        // Move to final
                        if (!copy($tempPath, $finalPath)) {
                            @unlink($tempPath);
                            $error = "Failed to move Luma image into uploads folder.";
                            break;
                        }
    
                        // Save upload record
                        if (qa_opt('enable_aws')) {
                            $url = king_upload_to_cloud($finalPath, $finalFilename, 'aws');
                            $full_result = king_insert_uploads($url, $ext, $w, $h, 'aws');
                        } elseif (qa_opt('enable_wasabi')) {
                            $url = king_upload_to_cloud($finalPath, $finalFilename, 'wasabi');
                            $full_result = king_insert_uploads($url, $ext, $w, $h, 'wasabi');
                        } else {
                            $full_result = king_insert_uploads($folder . $finalFilename, $ext, $w, $h);
                        }
    
                        @unlink($tempPath);
    
                        if ($thumb_result && $full_result) {
                            $uploaded_images[] = $full_result;
                            $thumbs[] = $thumb_result;
                            $gemini_processed = true; // skip urlupload loop below
                        } else {
                            $error = "Failed to save Luma image to database.";
                        }
    
                        break;
    
                    } elseif ($state === 'failed') {
    
                        $reason = $status['failure_reason'] ?? 'Unknown error';
                        $error = 'Luma generation failed: ' . $reason;
                        break;
    
                    } else {
                        // dreaming/queued/processing -> keep polling
                        continue;
                    }
                }
    
                if (!$gemini_processed && empty($error)) {
                    $error = 'Luma image generation timed out (no completed state within 300s)';
                }
            }
        }

    }
    
    
    else {
        $sdapi   = qa_opt('king_sd_api');
        $aistyle = qa_post_text('aistyle');
        $aisteps = qa_opt('king_sd_steps');

        $URL = "https://kingstudio.io/api/king-text2img";
        $style_preset = (isset($aistyle) && 'none' !== $aistyle) ? $aistyle : '';

        $request_data = [
            "prompt"   => $input . ($style_preset ? ', ' . $style_preset : ''),
            "size"     => (int)$imagen,
            "steps"    => (int)$aisteps,
            "aisize"   => $imsize,
            "model"    => $aiselect,
            "nvalue"   => $npvalue,
            "ennsfw"   => qa_opt('ennsfw') ? true : false,
            "sdnsfw"   => qa_opt('sdnsfw') ? true : false,
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

        // Only process if not already done (for Gemini/Decart/Luma-local branches)
        if (!$gemini_processed) {
            $uploaded_images = [];
            $thumbs = [];

            foreach ($image_urls as $image_url) {
                try {
                    $thumb = king_urlupload($image_url, true, 600);
                    if (!empty($thumb)) {
                        $thumbs[] = $thumb;
                    }

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
            $extra    = serialize($uploaded_images);
            $thumb    = end($thumbs);
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

            // ✅ include luma_img here too
            if ($imageid && ($aiselect === 'fluxkon' || $aiselect === 'sdream' || $aiselect === 'banana' || $aiselect === 'decart_img' || $aiselect === 'luma_img')) {
                qa_db_postmeta_set($postid, 'pimage', $imageid);
            }

            if (qa_opt('enable_membership') && (qa_opt('ailimits') || qa_opt('ulimits'))) {
                kingai_imagen($imagen);
            }

            $output = json_encode(array(
                'success' => true,
                'postid'  => $postid,
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
