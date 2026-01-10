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
if ( qa_is_logged_in() ) {
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
			if ( qa_opt('enable_membership') && ( qa_opt('ailimits') || qa_opt('ulimits') ) && qa_get_logged_in_level() <= QA_USER_LEVEL_ADMIN ) {
				$chkk = kingai_check();
			}
			if (qa_opt('enable_credits') && qa_opt('post_ai')) {
				$chkk = king_spend_credit(qa_opt('post_ai'));
			}
			if ($input && $chkk) {
				$npvalue = (null !== qa_post_text('npvalue')) ? qa_post_text('npvalue') : '';
				$imagen = qa_opt('kingai_imgn');
				

			if ('de' === $aiselect || 'de3' === $aiselect ) {
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
					curl_setopt($ch, CURLOPT_TIMEOUT, 400);
					$response_body = curl_exec($ch);

					curl_close($ch);

					$response_obj = json_decode($response_body, true);


					foreach ($response_obj['data'] as $image_data) {
						$image_urls[] = $image_data['url'];
					}
					
			} elseif( 'imagen4' === $aiselect ) {
					$API_KEY = qa_opt('gemini_api');
					$aspect_ratio = aisize_ratio($imsize);
					$api_url = "https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-generate-001:predict?key=" . $API_KEY;

					$payload = [
						"instances" => [
							[
								"prompt" => $input   // your generated prompt
							]
						],
						"parameters" => [
							"sampleCount" => 4,
							"aspectRatio" => $aspect_ratio,
							"personGeneration" => "ALLOW_ALL",
						]
					];

					$ch = curl_init($api_url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_HTTPHEADER, [
						"Content-Type: application/json"
					]);
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
					curl_setopt($ch, CURLOPT_TIMEOUT, 150);

					$response = curl_exec($ch);

					if (curl_errno($ch)) {
						$error = "CURL ERROR: " . curl_error($ch);
					}
					curl_close($ch);

					$data = json_decode($response, true);

					// Prepare holder for final URLs
					$image_urls = [];

					if (!isset($data["predictions"][0]["bytesBase64Encoded"])) {
						$error = "Imagen 4 did not return images: " . $response;

					} else {

						// Google Imagen returns array of base64 images inside predictions
						$base64 = $data["predictions"][0]["bytesBase64Encoded"];
						$image_binary = base64_decode($base64);

						// Save to uploads folder (automatic year/month)
						$folder = 'uploads/' . date("Y") . '/' . date("m") . '/';
						$destDir = QA_INCLUDE_DIR . $folder;

						if (!file_exists($destDir)) {
							mkdir($destDir, 0777, true);
						}

						$filename = 'gemini-image-' . time() . '-' . mt_rand(1000,9999) . '.webp';
						file_put_contents($destDir . $filename, $image_binary);

						// PUBLIC URL (important!)
						$public_url = current_url() . 'king-include/' . $folder . $filename;

						$image_urls[] = $public_url;
					}

			} elseif ('banana' === $aiselect) {
				$API_KEY = qa_opt('gemini_api');
				// ----- Aspect Ratio -----
				$aspect_ratio = aisize_ratio($imsize);

				// ----- Prepare Optional IMAGE INPUT -----
				$inline_image_part = null;

				if (!empty($imageid)) {
					// Example: get local file path from imageid
					$image_info = king_get_uploads(278);
					$img_path = isset($image_info['path']) ? $image_info['path'] : '';

					if ($img_path && file_exists($img_path)) {
						$img_data = file_get_contents($img_path);
						$img_b64 = base64_encode($img_data);

						// Detect MIME type
						$mime = mime_content_type($img_path);

						$inline_image_part = [
							"inline_data" => [
								"mime_type" => $mime,
								"data"      => $img_b64
							]
						];
					}
				}

				// ----- Gemini API Request -----
				$api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-image-preview:generateContent?key=" . $API_KEY;

				$parts = [
					["text" => $input]
				];

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

				$response = curl_exec($ch);

				// Error handling
				if (curl_errno($ch)) {
					$error = "CURL ERROR: " . curl_error($ch);
				}
				curl_close($ch);

				$data = json_decode($response, true);

				// ----- Extract Image -----
				if (!isset($data["candidates"][0]["content"]["parts"][0]["inlineData"]["data"])) {

					$error = "Failed to generate image.";

				} else {

					$base64 = $data["candidates"][0]["content"]["parts"][0]["inlineData"]["data"];
					$image_binary = base64_decode($base64);

					// Save to uploads folder (automatic year/month)
					$folder = 'uploads/' . date("Y") . '/' . date("m") . '/';
					$destDir = QA_INCLUDE_DIR . $folder;

					if (!file_exists($destDir)) {
						mkdir($destDir, 0777, true);
					}

					$filename = 'gemini-image-' . time() . '-' . mt_rand(1000,9999) . '.webp';
					file_put_contents($destDir . $filename, $image_binary);

					// PUBLIC URL (important!)
					$public_url = current_url() . 'king-include/' . $folder . $filename;

					$image_urls[] = $public_url;
				}

			} else {
				$sdapi = qa_opt('king_sd_api');
					$aistyle = qa_post_text('aistyle');
					$aisteps = qa_opt('king_sd_steps');
					
					$URL = "https://kingstudio.io/api/king-text2img";

					if ( isset($aistyle) && 'none' !== $aistyle ) {
						$style_preset = $aistyle;
					} else {
						$style_preset = '';
					}

					if (qa_opt('ennsfw')) {
						$ennsfw = true;
					} else {
						$ennsfw = false;
					}
					if (qa_opt('sdnsfw')) {
						$sdnsfw = true;
					} else {
						$sdnsfw = false;
					}
				    $request_data = [
						"prompt" => $input . ', ' . $style_preset,
						"size" => (int)$imagen,
						"steps" => (int)$aisteps,
						"aisize" => $imsize,
						"model" => $aiselect,
						"nvalue" =>$npvalue,
						"ennsfw" => $ennsfw,
						"sdnsfw" => $sdnsfw,
					];	
					    if ($imageid && ( $aiselect === 'fluxkon' || $aiselect === 'sdream' ) ) {
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
					curl_setopt($ch, CURLOPT_TIMEOUT, 400);
					$response = curl_exec($ch);
					

				$out = json_decode($response, true);
				if (isset($out['error'])) {
					$error = $out['error'];
				}

				$image_urls = $out['out'] ?? [];
					
					
			}

				if (isset($error) && $error) {
					$output = json_encode(array('success' => false, 'message' => $error));
				} else {
					require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
					$uploaded_images = [];
					foreach ($image_urls as $image_url) {
						$thumb = king_urlupload($image_url, true, 600);
						$upload_response = king_urlupload($image_url);
						if (!empty($upload_response)) {
							$uploaded_images[] = $upload_response;
						}
						if (!empty($thumb)) {
							$thumbs[] = $thumb;
						}
					}
					$extra = serialize($uploaded_images);
					$thumb = end($thumbs);
					$cookieid = isset($userid) ? qa_cookie_get() : qa_cookie_get_create();
					$postid = qa_question_create( null, $userid, qa_get_logged_in_handle(), $cookieid, null, $thumb, '', null, null, null, null, null, $extra, 'NOTE', null, 'aimg', $input, null );

                	qa_db_postmeta_set($postid, 'wai', true);
					qa_db_postmeta_set($postid, 'model', $aiselect);
					if ($npvalue) {
						qa_db_postmeta_set($postid, 'nprompt', $npvalue);
					}
					if ($style_preset) {
						qa_db_postmeta_set($postid, 'stle', $style_preset);
					}
					if (isset($imsize)) {
						qa_db_postmeta_set($postid, 'asize', $imsize);
					}					
					if ($imageid && ( $aiselect === 'fluxkon' || $aiselect === 'sdream' || $aiselect === 'banana' ) ) {
						qa_db_postmeta_set($postid, 'pimage', $imageid);
					}			
					if ( qa_opt('enable_membership') && ( qa_opt('ailimits') || qa_opt('ulimits') ) ) {
						kingai_imagen($imagen);
					}		
					$output = json_encode(array(
						'success' => true,
						'postid' => $postid,
					));
				}
					echo "QA_AJAX_RESPONSE\n1\n";

					echo $output."\n";

					echo king_ai_posts($userid, 'aimg');
				
					if ('banana' === $aiselect || 'imagen4' === $aiselect ) {
						// Clean up the temporary image file
						unlink($destDir . $filename);
					}

			} else {
				$outputz = json_encode(array('success' => false, 'message' => qa_lang_html('misc/nocredits')));
				
				echo "QA_AJAX_RESPONSE\n0\n";

                echo $outputz."\n";
			}

