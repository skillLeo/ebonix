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
require_once QA_INCLUDE_DIR . 'king-db/post-update.php';
require_once QA_INCLUDE_DIR . 'king-db/selects.php';

if ( qa_is_logged_in() ) {
	$userid = qa_get_logged_in_userid();
} else {
	$userid = qa_remote_ip_address();
}
$postid = qa_post_text('postid');
if ( $postid ) {
    qa_db_post_delete($postid);

    echo "QA_AJAX_RESPONSE\n1\n";
}
