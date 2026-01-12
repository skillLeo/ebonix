<?php
/*

File: king-include/king-app-blobs.php
Description: Application-level blob-management functions

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

function kingsource($videocontent)
{
	$parsed = parse_url($videocontent);
	return str_replace('www.', '', strtolower($parsed['host']));
}

function get_thumb($videocontent)
{
	$opts = array('http'=>array('header' => "User-Agent:MyAgent/1.0\r\n")); 
	$context = stream_context_create( $opts );
	$res = file_get_contents( $videocontent, false, $context );

	preg_match('/property="og:image" content="(.*?)"/', $res, $output);
	return ($output[1]) ? $output[1] : false;
}
function getInstagramMediaUrl($url) {
    $urlParts = explode('/', rtrim($url, '/'));
    $code = end($urlParts);
    $mediaUrl = "https://www.instagram.com/p/{$code}/media?size=l";
    
    return $mediaUrl;
}

function get_vimeo($vimeo_url)
{
	if ( ! $vimeo_url ) {
		return false;
	}
	$data = json_decode( file_get_contents( 'http://vimeo.com/api/oembed.json?url=' . $vimeo_url ) );
	if ( ! $data ) {
		return false;
	}

	$new_url = str_replace('d_295x166', 'd_1024', $data->thumbnail_url);
	return $new_url;
}
function king_twitch($videocontent)
{
	$res = file_get_contents("$videocontent");
	preg_match('/content=\'(.*?)\' property=\'og:image\'/', $res, $matches);

	return ($matches[1]) ? $matches[1] : false;
}
function king_tiktok($videocontent)
{
	$url = 'https://www.tiktok.com/oembed?url=' . $videocontent . '';
	$res   = file_get_contents($url);
	$video = json_decode($res);
	return $video->thumbnail_url;
}
function king_vk($videocontent)
{
	$page          = file_get_contents("$videocontent");
	$page_for_hash = preg_replace('/\\\/', '', $page);
	if (preg_match("@,\"jpg\":\"(.*?)\",@", $page_for_hash, $matches)) {
		$result = $matches[1];
		return $result;
	}
}

function king_mailru($videocontent)
{
	$page = file_get_contents("$videocontent");
	if (preg_match('/content="(.*?)" name="og:image"/', $page, $mailru)) {
		$king = $mailru[1];
		return $king;
	}
}

function king_facebook($content)
{
	$facebook_access_token = qa_opt('fb_user_token');
	$paths                 = explode("/", $content);
	$num                   = count($paths);
	for ($i = $num - 1; $i > 0; $i--) {
		if ($paths[$i] != "") {
			$video_id = $paths[$i];
			break;
		}
	}
	$data = file_get_contents('https://graph.facebook.com/' . $video_id . '/thumbnails?access_token=' . $facebook_access_token . '');
	if ($data !== false) {
		$result           = json_decode($data);
		return $thumbnail = $result->data[0]->uri;
	}
}

function king_youtube($url)
{
	$queryString = parse_url($url, PHP_URL_QUERY);
	parse_str($queryString, $params);
	if (isset($params['v'])) {
		return "https://i3.ytimg.com/vi/" . trim($params['v']) . "/hqdefault.jpg";
	}
	return true;
}


function king_xhamster($videocontent)
{
	$res = file_get_contents("$videocontent");
	preg_match('/name="twitter:image" property="og:image" content="(.*?)"/', $res, $output);
	return ($output[1]) ? $output[1] : false;
}

function king_okru($videocontent)
{
	$res = file_get_contents("$videocontent");
	preg_match('/rel="image_src" href="(.*?)"/', $res, $output);
	return ($output[1]) ? $output[1] : false;
}

function coub_thumb($videocontent)
{
	$page2 = file_get_contents("$videocontent");
	if (preg_match('/property="og:image" content="(.*?)"/', $page2, $coub)) {
		$cou = $coub[1];
		return $cou;
	}
}

function king_gfycat($videocontent)
{
	$res = file_get_contents("$videocontent");
	preg_match('/name="twitter:image" content="(.*?)"/', $res, $output);
	return ($output[1]) ? $output[1] : false;
}
function embed_replace($text)
	{

		$w = '800';

		$h = '450';

		$w2 = '100%';

		$h2 = 'auto';

		$types = array(
			'youtube'     => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*youtube\.com\/watch\?\S*v=([A-Za-z0-9_-]+)[^< ]*',
					'<iframe width="' . $w . '" height="' . $h . '" src="https://www.youtube.com/embed/$1?wmode=transparent" frameborder="0" allowfullscreen></iframe>',
				),
				array(
					'https{0,1}:\/\/w{0,3}\.*youtu\.be\/([A-Za-z0-9_-]+)[^< ]*',
					'<iframe width="' . $w . '" height="' . $h . '" src="https://www.youtube.com/embed/$1?wmode=transparent" frameborder="0" allowfullscreen></iframe>',
				),
			),
			'vimeo'       => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*vimeo\.com\/([0-9]+)[^< ]*',
					'<iframe src="https://player.vimeo.com/video/$1?title=0&amp;byline=0&amp;portrait=0&amp;wmode=transparent" width="' . $w . '" height="' . $h . '" frameborder="0"></iframe>'),
			),
			'metacafe'    => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*metacafe\.com\/watch\/([0-9]+)\/([a-z0-9_]+)[^< ]*',
					'<iframe width="' . $w . '" height="' . $h . '" src="https://www.metacafe.com/embed/$1/$2/" frameborder="0" allowfullscreen></iframe>',
				),
			),
			'vine'        => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*vine\.co\/v\/([A-Za-z0-9_-]+)[^< ]*',
					'<iframe class="vine-embed" src="https://vine.co/v/$1/embed/simple?audio=1" width="' . $w . '" height="480px" frameborder="0"></iframe>',
				),
			),

			'instagram'   => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*instagram\.com\/p\/([A-Za-z0-9_-]+)[^< ]*',
					'<iframe src="https://www.instagram.com/p/$1/embed/captioned/" width="' . $w . '" height="' . $w . '" frameborder="0" scrolling="no" allowtransparency="true" class="instaframe"></iframe>',
				),
			),

			'twitter'   => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*twitter\.com\/([A-Za-z0-9_-]+)\/status\/([A-Za-z0-9_-]+)[^< ]*',
					'<iframe id="twitter-widget-0" scrolling="no" frameborder="0" allowtransparency="true" allowfullscreen="true" src="https://platform.twitter.com/embed/Tweet.html?id=$2" data-tweet-id="$2" width="' . $w . '" height="' . $w . '" class="instaframe"></iframe>',
				),
			),


			'dailymotion' => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*dailymotion\.com\/video\/([A-Za-z0-9]+)[^< ]*',
					'<iframe frameborder="0" width="' . $w . '" height="' . $h . '" src="https://www.dailymotion.com/embed/video/$1?wmode=transparent"></iframe>',
				),
			),

			'mailru'      => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*my.mail.ru\/mail\/([\-\_\/.a-zA-Z0-9]+)[^< ]*',
					'<iframe src="https://videoapi.my.mail.ru/videos/embed/mail/$1" width="' . $w . '" height="' . $h . '" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>',
				),
			),

			'soundcloud'  => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*soundcloud\.com\/([-\%_\/.a-zA-Z0-9]+\/[-\%_\/.a-zA-Z0-9]+)[^< ]*',
					'<iframe width="100%" height="450" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url=https://soundcloud.com/$1&amp;auto_play=false&amp;hide_related=false&amp;show_comments=true&amp;show_user=true&amp;show_reposts=false&amp;visual=true"></iframe>',
				),
			),

			'spotify'  => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*open.spotify\.com\/([-\%_\/.a-zA-Z0-9]+\/[-\%_\/.a-zA-Z0-9]+)[^< ]*',
					'<iframe src="https://open.spotify.com/embed/$1" width="' . $w . '" height="' . $h . '" frameborder="0" allowtransparency="true" ></iframe>',
				),
			),

			'facebook'    => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*facebook\.com\/video\.php\?\S*v=([A-Za-z0-9_-]+)[^< ]*',
					'<div class="fb-video" data-allowfullscreen="true" data-href="https://www.facebook.com/video.php?v=$1&type=1"></div>',
				),
				array(
					'https{0,1}:\/\/w{0,3}\.*facebook\.com\/([A-Z.a-z0-9_-]+)\/videos\/([A-Za-z0-9_-]+)[^< ]*',
					'<div class="fb-video" data-allowfullscreen="true"  data-href="/$1/videos/$2/?type=1"></div>',
				),
				array(
					'https{0,1}:\/\/w{0,3}\.*facebook\.com\/watch\/\?\S*v=([A-Za-z0-9]+)[^< ]*',
					'<iframe src="https://www.facebook.com/plugins/video.php?height=3144&href=https://www.facebook.com/watch/?v=$1&show_text=false&width=560" width="' . $w . '" height="' . $h . '" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowFullScreen="true"></iframe>',
				),
			),

			'image'       => array(
				array(
					'(https*:\/\/[-\[\]\{\}\(\)\%_\/.a-zA-Z0-9+]+\.(png|jpg|jpeg|gif|bmp))[^< ]*',
					'<img src="$1" style="max-width:' . $w2 . ';height:' . $h2 . ';display:block;" />',
				),
			),

			'xhamster'    => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*xhamster\.com\/movies\/([0-9]+)\/(.*?)[^< ]*',
					'<iframe src="http://xhamster.com/xembed.php?video=$1" width="' . $w . '" height="' . $h . '" scrolling="no" allowfullscreen></iframe>',
				),
			),
			'tiktok'        => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*tiktok\.com\/([A-Za-z0-9-\@]+)\/video\/([0-9]+)[^< ]*',
					'<iframe src="https://www.tiktok.com/embed/v2/$2" width="' . $w . '" height="800px" scrolling="no" allowfullscreen></iframe>',
				),
			),
			'okru'        => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*ok\.ru\/video\/([A-Za-z0-9]+)[^< ]*',
					'<iframe width="' . $w . '" height="' . $h . '" src="http://ok.ru/videoembed/$1" frameborder="0" allowfullscreen></iframe>',
				),
			),

			'coub'        => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*coub.com\/view\/([\-\_\/.a-zA-Z0-9]+)[^< ]*',
					'<iframe src="//coub.com/embed/$1?muted=true&autostart=true&originalSize=false&hideTopBar=false&startWithHD=false" allowfullscreen="true" frameborder="0" width="' . $w . '" height="' . $h . '"></iframe>',
				),
			),

			'vidme'       => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*vid\.me\/([A-Za-z0-9_-]+)[^< ]*',
					'<iframe src="https://vid.me/e/$1" width="' . $w . '" height="' . $h . '" frameborder="0" allowfullscreen webkitallowfullscreen mozallowfullscreen scrolling="no"></iframe>',
				),
			),

			'gfycat'      => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*gfycat\.com\/([A-Z.a-z0-9_]+)[^< ]*',
					'<iframe src="https://gfycat.com/ifr/$1" frameborder="0" scrolling="no" width="' . $w . '" height="' . $h . '" allowfullscreen></iframe>',
				),
			),

			'twitch'      => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*twitch\.tv\/([A-Za-z0-9]+)[^< ]*',
					'<iframe src="https://player.twitch.tv/?channel=$1"  frameborder="0" allowfullscreen="true" scrolling="no" height="378" width="620"></iframe>',
				),
			),

			'drive'       => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*drive\.google\.com\/file\/d\/([\-\_\/.a-zA-Z0-9]+)\/view[^< ]*',
					'<iframe src="https://drive.google.com/file/d/$1/preview" width="' . qa_html($w) . '" height="' . qa_html($h) . '"></iframe>',
				),
			),

			'rutube'      => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*rutube\.ru\/video\/([A-Za-z0-9_-]+)[^< ]*',
					'<iframe width="' . $w . '" height="' . $h . '" src="https://rutube.ru/play/embed/$1" frameBorder="0" allow="clipboard-write" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>',
				),
			),

			'xvideos' => array(
				array(
					'https{0,1}:\/\/w{0,3}\.*xvideos.com\/video([A-Z.a-z0-9_-]+)\/([A-Za-z0-9_-]+)[^< ]*',
					'<iframe width="' . $w . '" height="' . $h . '" src="https://www.xvideos.com/embedframe/$1" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowfullscreen></iframe>',
				),
			),
			'mp4'=>array(
				array(
					'(https*:\/\/[-\%_\/.a-zA-Z0-9+]+\.(mp4))[^< ]*',
					'<video id="my-video" class="video-js vjs-theme-forest" controls preload="auto" width="960" height="540" data-setup="{}" poster="" ><source src="$1" type="video/mp4" /></video>'
				)
			),
			'mp4'=>array(
				array(
					'(https*:\/\/[-\%_\/.a-zA-Z0-9+]+\.(mp3))[^< ]*',
					'<audio id="my-video" class="video-js vjs-theme-forest" controls preload="auto" width="960" height="540" data-setup="{}" poster="" ><source src="$1" type="audio/mp3" /></audio>'
				)
			),

			'gmap'        => array(
				array(
					'(https*:\/\/maps.google.com\/?[^< ]+)',
					'<iframe width="' . qa_opt('embed_gmap_width') . '" height="' . qa_opt('embed_gmap_height') . '" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="$1&amp;ie=UTF8&amp;output=embed"></iframe><br /><small><a href="$1&amp;ie=UTF8&amp;output=embed" style="color:#0000FF;text-align:left">View Larger Map</a></small>', 'gmap',
				),
			),
		);

		foreach ($types as $t => $ra) {
			foreach ($ra as $r) {

				$text = preg_replace('/<a[^>]+>' . $r[0] . '<\/a>/i', $r[1], $text);
				$text = preg_replace('/(?<![\'"=])' . $r[0] . '/i', $r[1], $text);
			}
		}
		return $text;
	}

function king_upload_to_cloud($localPath, $filename, $type = 'aws') {
    require_once QA_INCLUDE_DIR . 's3/aws.phar';

    $cloudOpts = [
        'aws' => [
            'key' => qa_opt('aws_key'),
            'secret' => qa_opt('aws_secret'),
            'region' => qa_opt('aws_region'),
            'bucket' => qa_opt('aws_bucket'),
            'endpoint' => null,
            'path_style' => false
        ],
        'wasabi' => [
            'key' => qa_opt('wasabi_key'),
            'secret' => qa_opt('wasabi_secret'),
            'region' => qa_opt('wasabi_region'),
            'bucket' => qa_opt('wasabi_bucket'),
            'endpoint' => 'https://s3.wasabisys.com',
            'path_style' => true
        ]
    ];

    $conf = $cloudOpts[$type];
    $clientParams = [
        'region' => $conf['region'],
        'version' => 'latest',
        'credentials' => [
            'key' => $conf['key'],
            'secret' => $conf['secret']
        ]
    ];

    if ($conf['endpoint']) {
        $clientParams['endpoint'] = $conf['endpoint'];
        $clientParams['use_path_style_endpoint'] = $conf['path_style'];
    }

    $s3Client = new Aws\S3\S3Client($clientParams);

    $result = $s3Client->putObject([
        'Bucket' => $conf['bucket'],
        'Key' => $filename,
        'SourceFile' => $localPath
    ]);

    @unlink($localPath);
    return $result['ObjectURL'];
}

function king_apply_watermark($image, $width, $height) {
    $watermark_path = QA_INCLUDE_DIR . 'watermark/watermark.png';
    if (!file_exists($watermark_path)) return $image;

    $watermark = imagecreatefrompng($watermark_path);
    $w = imagesx($watermark);
    $h = imagesy($watermark);
    $pos = strtolower(qa_opt('watermark_position'));

    switch ($pos) {
        case 'topleft': $x = 0; $y = 0; break;
        case 'topright': $x = $width - $w; $y = 0; break;
        case 'bottomleft': $x = 0; $y = $height - $h; break;
        case 'bottomright': $x = $width - $w; $y = $height - $h; break;
        case 'center': $x = ($width - $w) / 2; $y = ($height - $h) / 2; break;
        case 'bottomcenter': $x = ($width - $w) / 2; $y = $height - $h; break;
        default: $x = 10; $y = ($height - $h) / 2;
    }

    imagecopy($image, $watermark, $x, $y, 0, 0, $w, $h);
    imagedestroy($watermark);
    return $image;
}

function king_convert_to_webp($sourcePath, $destPath, $quality = 90) {
	$imageType = exif_imagetype($sourcePath);
	switch ($imageType) {
		case IMAGETYPE_JPEG:
			$image = imagecreatefromjpeg($sourcePath);
			break;
		case IMAGETYPE_PNG:
			$image = imagecreatefrompng($sourcePath);
			// Convert PNG transparency to white background for webp
			$bg = imagecreatetruecolor(imagesx($image), imagesy($image));
			$white = imagecolorallocate($bg, 255, 255, 255);
			imagefilledrectangle($bg, 0, 0, imagesx($image), imagesy($image), $white);
			imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
			imagedestroy($image);
			$image = $bg;
			break;
		case IMAGETYPE_GIF:
			$image = imagecreatefromgif($sourcePath);
			break;
		case IMAGETYPE_WEBP:
			// Already webp, just copy
			return copy($sourcePath, $destPath);
		default:
			return false;
	}
	$result = imagewebp($image, $destPath, $quality);
	imagedestroy($image);
	return $result;
}

function king_urlupload($imageUrl, $waterk = null, $resize = null) {
    // Increase timeout for this operation
    set_time_limit(180);
    
    $opts = ['http' => [
        'header' => "User-Agent:MyAgent/1.0\r\n",
        'timeout' => 60 // Add timeout
    ]];
    $context = stream_context_create($opts);
    
    // Add timeout and error handling
    $fileContent = @file_get_contents($imageUrl, false, $context);
    if ($fileContent === false) {
        error_log("Failed to download image from: " . $imageUrl);
        return false;
    }

    // Detect file extension
    $ext = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
    $isVideo = ($ext === 'mp4');
    $format = $isVideo ? 'mp4' : 'webp';
    $filename = uniqid('', true) . '.' . $format;

    // Prepare upload path
    $folder = 'uploads/' . date("Y") . '/' . date("m") . '/';
    $destDir = QA_INCLUDE_DIR . $folder;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }
    $localPath = $destDir . $filename;

    // Limit file size (50MB)
    $maxFileSize = 50 * 1024 * 1024;
    if (strlen($fileContent) > $maxFileSize) {
        error_log("File too large: " . strlen($fileContent) . " bytes");
        return false;
    }

    // Write securely
    if (@file_put_contents($localPath, $fileContent, LOCK_EX) === false || !file_exists($localPath)) {
        error_log("Failed to write file: " . $localPath);
        return false;
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $localPath);
    finfo_close($finfo);

    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'video/mp4',
    ];

    if (!in_array($mimeType, $allowedMimeTypes)) {
        @unlink($localPath);
        error_log("Invalid MIME type: " . $mimeType);
        return false;
    }

    // Handle MP4 (video) - no processing
    if ($mimeType === 'video/mp4') {
        if (qa_opt('enable_aws')) {
            $url = king_upload_to_cloud($localPath, $filename, 'aws');
            return king_insert_uploads($url, 'mp4', null, null, 'aws');
        } elseif (qa_opt('enable_wasabi')) {
            $url = king_upload_to_cloud($localPath, $filename, 'wasabi');
            return king_insert_uploads($url, 'mp4', null, null, 'wasabi');
        } else {
            return king_insert_uploads($folder . $filename, 'mp4', null, null);
        }
    }

    // Handle images - OPTIMIZED
    $imageInfo = @getimagesize($localPath);
    if ($imageInfo === false) {
        @unlink($localPath);
        error_log("Invalid image file");
        return false;
    }
    
    list($CurWidth, $CurHeight) = $imageInfo;
    
    if ($CurWidth <= 0 || $CurHeight <= 0) {
        @unlink($localPath);
        return false;
    }

    $NewWidth = $CurWidth;
    $NewHeight = $CurHeight;

    // Only resize if needed
    if ($resize && ($CurWidth > $resize || $CurHeight > $resize)) {
        $scale = min($resize / $CurWidth, $resize / $CurHeight);
        $NewWidth = max(1, (int)($scale * $CurWidth));
        $NewHeight = max(1, (int)($scale * $CurHeight));
    }

    // Create image resource based on type
    $imageType = $imageInfo[2];
    $source = null;
    
    switch ($imageType) {
        case IMAGETYPE_WEBP:
            $source = @imagecreatefromwebp($localPath);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($localPath);
            break;
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($localPath);
            break;
        case IMAGETYPE_GIF:
            $source = @imagecreatefromgif($localPath);
            break;
    }
    
    if ($source === false || $source === null) {
        @unlink($localPath);
        error_log("Failed to create image resource");
        return false;
    }

    // Only resize if dimensions changed
    if ($NewWidth != $CurWidth || $NewHeight != $CurHeight) {
        $resized = imagecreatetruecolor($NewWidth, $NewHeight);
        
        // Preserve transparency for PNG/GIF
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $NewWidth, $NewHeight, $transparent);
        }
        
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $NewWidth, $NewHeight, $CurWidth, $CurHeight);
        imagedestroy($source);
        $source = $resized;
    }

    // Apply watermark if enabled
    if (qa_opt('watermark_default_show') && $waterk) {
        $source = king_apply_watermark($source, $NewWidth, $NewHeight);
    }

    // Save as WebP
    $webpPath = $localPath;
    if (!imagewebp($source, $webpPath, 85)) { // Reduced quality from 100 to 85
        imagedestroy($source);
        @unlink($localPath);
        error_log("Failed to save WebP");
        return false;
    }
    
    imagedestroy($source);

    // Cloud or local upload
    if (qa_opt('enable_aws')) {
        $url = king_upload_to_cloud($webpPath, $filename, 'aws');
        return king_insert_uploads($url, 'webp', $NewWidth, $NewHeight, 'aws');
    } elseif (qa_opt('enable_wasabi')) {
        $url = king_upload_to_cloud($webpPath, $filename, 'wasabi');
        return king_insert_uploads($url, 'webp', $NewWidth, $NewHeight, 'wasabi');
    } else {
        return king_insert_uploads($folder . $filename, 'webp', $NewWidth, $NewHeight);
    }
}

function king_uploadthumb($ImageName, $TempSrc, $ImageType) {
    $folder = 'uploads/' . date("Y") . '/' . date("m") . '/';
    $destDir = QA_INCLUDE_DIR . $folder;
    if (!is_dir($destDir)) mkdir($destDir, 0777, true);

    $NewName = rand(0, 999999) . '-' . basename($ImageName);
    $localPath = $destDir . $NewName;

    switch (strtolower($ImageType)) {
        case 'image/png': $source = imagecreatefrompng($TempSrc); break;
        case 'image/gif': $source = imagecreatefromgif($TempSrc); break;
        case 'image/webp': $source = imagecreatefromwebp($TempSrc); break;
        case 'image/jpeg':
        case 'image/pjpeg': $source = imagecreatefromjpeg($TempSrc); break;
        default: return false;
    }

    list($CurWidth, $CurHeight) = getimagesize($TempSrc);
    if ($CurWidth <= 0 || $CurHeight <= 0) return false;

    $MaxSize = 800;
    $scale = min($MaxSize / $CurWidth, $MaxSize / $CurHeight);
    $NewWidth = ceil($scale * $CurWidth);
    $NewHeight = ceil($scale * $CurHeight);

    $resized = imagecreatetruecolor($NewWidth, $NewHeight);
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $NewWidth, $NewHeight, $CurWidth, $CurHeight);

    if (qa_opt('watermark_default_show')) {
        $resized = king_apply_watermark($resized, $NewWidth, $NewHeight);
    }

    imagejpeg($resized, $localPath, 90);
    imagedestroy($resized);
    imagedestroy($source);

    if (qa_opt('enable_aws')) {
        $url = king_upload_to_cloud($localPath, 'thumb_' . $ImageName, 'aws');
        return [
            'id' => king_insert_uploads($url, $ImageType, $NewWidth, $NewHeight, 'aws'),
            'path' => $url
        ];
    } elseif (qa_opt('enable_wasabi')) {
        $url = king_upload_to_cloud($localPath, 'thumb_' . $ImageName, 'wasabi');
        return [
            'id' => king_insert_uploads($url, $ImageType, $NewWidth, $NewHeight, 'wasabi'),
            'path' => $url
        ];
    } else {
        return [
            'id' => king_insert_uploads($folder . $NewName, $ImageType, $NewWidth, $NewHeight),
            'path' => $folder . $NewName
        ];
    }
}