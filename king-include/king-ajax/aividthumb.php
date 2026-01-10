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


if (qa_is_logged_in()) {
    $userid = qa_get_logged_in_userid();
} else {
    $userid = qa_remote_ip_address();
}

$thumbData = qa_post_text('thumb');
$postid = qa_post_text('postid');

if (isset($thumbData) && isset($postid)) {

    if (preg_match('/^data:image\/(\w+);base64,/', $thumbData, $type)) {
        $thumbData = substr($thumbData, strpos($thumbData, ',') + 1);
        $thumbData = base64_decode($thumbData);

        // Save directly to uploads directory
        $uploadsDir = QA_INCLUDE_DIR . 'uploads/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }
        $tmpFile = $uploadsDir . uniqid('thumb_') . '.webp';
        file_put_contents($tmpFile, $thumbData);

        $thumbid = king_urlupload($tmpFile);

        qa_db_query_sub(
            'UPDATE ^posts SET content = $ WHERE postid = $',
            $thumbid, $postid
        );
        unlink($tmpFile);

        echo "QA_AJAX_RESPONSE\n1\n";
        echo json_encode(['success' => true, 'thumburl' => $thumbid]) . "\n";
        exit;
    } else {
        echo "QA_AJAX_RESPONSE\n0\n";
        echo json_encode(['success' => false, 'message' => 'Invalid image data']) . "\n";
        exit;
    }
}
