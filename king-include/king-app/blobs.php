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

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}


	function qa_get_blob_url($blobid, $absolute=false)
/*
	Return the URL which will output $blobid from the database when requested, $absolute or relative
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		return qa_path('blob', array('qa_blobid' => $blobid), $absolute ? qa_opt('site_url') : null, QA_URL_FORMAT_PARAMS);
	}


	function qa_get_blob_directory($blobid)
/*
	Return the full path to the on-disk directory for blob $blobid (subdirectories are named by the first 3 digits of $blobid)
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		return rtrim(QA_BLOBS_DIRECTORY, '/').'/'.substr(str_pad($blobid, 20, '0', STR_PAD_LEFT), 0, 3);
	}


	function qa_get_blob_filename($blobid, $format)
/*
	Return the full page and filename of blob $blobid which is in $format ($format is used as the file name suffix e.g. .jpg)
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		return qa_get_blob_directory($blobid).'/'.$blobid.'.'.preg_replace('/[^A-Za-z0-9]/', '', $format);
	}


	function qa_create_blob($content, $format, $sourcefilename=null, $userid=null, $cookieid=null, $ip=null)
/*
	Create a new blob (storing the content in the database or on disk as appropriate) with $content and $format, returning its blobid.
	Pass the original name of the file uploaded in $sourcefilename and the $userid, $cookieid and $ip of the user creating it
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		require_once QA_INCLUDE_DIR.'king-db/blobs.php';

		$blobid=qa_db_blob_create(defined('QA_BLOBS_DIRECTORY') ? null : $content, $format, $sourcefilename, $userid, $cookieid, $ip);

		if (isset($blobid) && defined('QA_BLOBS_DIRECTORY'))
			if (!qa_write_blob_file($blobid, $content, $format))
				qa_db_blob_set_content($blobid, $content); // still write content to the database if writing to disk failed

		return $blobid;
	}


	function qa_write_blob_file($blobid, $content, $format)
/*
	Write the on-disk file for blob $blobid with $content and $format. Returns true if the write succeeded, false otherwise.
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		$written=false;

		$directory=qa_get_blob_directory($blobid);
		if (is_dir($directory) || mkdir($directory, fileperms(rtrim(QA_BLOBS_DIRECTORY, '/')) & 0777)) {
			$filename=qa_get_blob_filename($blobid, $format);

			$file=fopen($filename, 'xb');
			if (is_resource($file)) {
				if (fwrite($file, $content)>=strlen((string)$content))
					$written=true;

				fclose($file);

				if (!$written)
					unlink($filename);
			}
		}

		return $written;
	}


	function qa_read_blob($blobid)
/*
	Retrieve blob $blobid from the database, reading the content from disk if appropriate
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		require_once QA_INCLUDE_DIR.'king-db/blobs.php';

		$blob=qa_db_blob_read($blobid);

		if (isset($blob) && defined('QA_BLOBS_DIRECTORY') && !isset($blob['content']))
			$blob['content']=qa_read_blob_file($blobid, $blob['format']);

		return $blob;
	}


	function qa_read_blob_file($blobid, $format)
/*
	Read the content of blob $blobid in $format from disk. On failure, it will return false.
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		$filename = qa_get_blob_filename($blobid, $format);
		if (is_readable($filename))
			return file_get_contents($filename);
		else
			return null;
	}


	function qa_delete_blob($blobid)
/*
	Delete blob $blobid from the database, and remove the on-disk file if appropriate
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		require_once QA_INCLUDE_DIR.'king-db/blobs.php';

		if (defined('QA_BLOBS_DIRECTORY')) {
			$blob=qa_db_blob_read($blobid);

			if (isset($blob) && !isset($blob['content']))
				unlink(qa_get_blob_filename($blobid, $blob['format']));
		}

		qa_db_blob_delete($blobid);
	}


	function qa_delete_blob_file($blobid, $format)
/*
	Delete the on-disk file for blob $blobid in $format
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		unlink(qa_get_blob_filename($blobid, $format));
	}


	function qa_blob_exists($blobid)
/*
	Check if blob $blobid exists
*/
	{
		if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }

		require_once QA_INCLUDE_DIR.'king-db/blobs.php';

		return qa_db_blob_exists($blobid);
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/



function king_process_local_image($localPath, $relativePath, $isThumb = false, $maxSize = null) {
    if (!file_exists($localPath)) {
        error_log("Local file not found: " . $localPath);
        return false;
    }

    // Get image info
    $imageInfo = @getimagesize($localPath);
    if ($imageInfo === false) {
        error_log("Invalid image file: " . $localPath);
        return false;
    }
    
    list($CurWidth, $CurHeight) = $imageInfo;
    
    if ($CurWidth <= 0 || $CurHeight <= 0) {
        return false;
    }

    $NewWidth = $CurWidth;
    $NewHeight = $CurHeight;

    // Resize if needed
    if ($maxSize && ($CurWidth > $maxSize || $CurHeight > $maxSize)) {
        $scale = min($maxSize / $CurWidth, $maxSize / $CurHeight);
        $NewWidth = max(1, (int)($scale * $CurWidth));
        $NewHeight = max(1, (int)($scale * $CurHeight));
    }

    // Process based on whether it's a thumbnail or full image
    $filename = basename($relativePath);
    if ($isThumb) {
        $filename = 'thumb_' . $filename;
    }
    
    $folder = dirname($relativePath) . '/';
    $destDir = QA_INCLUDE_DIR . $folder;
    $outputPath = $destDir . $filename;

    // Create image resource
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
        error_log("Failed to create image resource from: " . $localPath);
        return false;
    }

    // Resize if dimensions changed
    if ($NewWidth != $CurWidth || $NewHeight != $CurHeight) {
        $resized = imagecreatetruecolor($NewWidth, $NewHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $NewWidth, $NewHeight, $CurWidth, $CurHeight);
        imagedestroy($source);
        $source = $resized;
    }

    // Apply watermark if enabled and it's a thumbnail
    if ($isThumb && qa_opt('watermark_default_show')) {
        $source = king_apply_watermark($source, $NewWidth, $NewHeight);
    }

    // Save as WebP
    if (!imagewebp($source, $outputPath, 85)) {
        imagedestroy($source);
        error_log("Failed to save WebP: " . $outputPath);
        return false;
    }
    
    imagedestroy($source);

    // Upload to cloud or keep local
    if (qa_opt('enable_aws')) {
        $url = king_upload_to_cloud($outputPath, $filename, 'aws');
        return king_insert_uploads($url, 'webp', $NewWidth, $NewHeight, 'aws');
    } elseif (qa_opt('enable_wasabi')) {
        $url = king_upload_to_cloud($outputPath, $filename, 'wasabi');
        return king_insert_uploads($url, 'webp', $NewWidth, $NewHeight, 'wasabi');
    } else {
        return king_insert_uploads($folder . $filename, 'webp', $NewWidth, $NewHeight);
    }
}