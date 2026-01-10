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
require_once QA_INCLUDE_DIR . 'king-db/metas.php';

$modal = qa_post_text( 'modal' );
$id = qa_post_text( 'id' );

$userid = qa_get_logged_in_userid();
$query  = qa_db_usermeta_get( $userid, 'bookmarks' );
$result = $query ? unserialize( $query ) : '';

if ( qa_is_logged_in() && $id ) {
	

	if ( is_array( $result ) && in_array( $id, $result ) ) {
		if ( $result ) {
			$uid_key = array_search( $id, $result );
			unset( $result[$uid_key] );
			$arr = serialize( $result );
			qa_db_usermeta_set( $userid, 'bookmarks', $arr );

			echo "QA_AJAX_RESPONSE\n0\n";
		}
	} else {
		if ( $result ) {
			$post_users = $result;
		}

		if ( ! is_array( $post_users ) ) {
			$post_users = array();
		}

		if ( ! in_array( $id, $post_users ) ) {
			$post_users[] = $id;
			$arr          = serialize( $post_users );
		}
		qa_db_usermeta_set( $userid, 'bookmarks', $arr );

		echo "QA_AJAX_RESPONSE\n1\n";
	}
} elseif ( $modal ) {
	$posts = qa_db_single_select( qa_db_posts_selectspec($userid, $result) );
	if ($posts) {
		$out = '';
		$out .= '<div class="bm-posts">';
		foreach ($posts as $post) {
			$furl = qa_path_absolute(qa_q_request($post['postid'], $post['title']), null, null);
			$img = king_get_uploads($post['content']);
			$out .= '<div class="bm-post">';
			$out .= '<div class="bm-bg" style="background-image:url('.$img['furl'].');">';
			$out .= post_bookmark($post['postid'], 'modalbook king-readlater');
			$out .= '</div>';
			$out .= '<div class="bm-content">';
			$out .= '<a href="'.qa_html($furl).'" class="bm-title">'.qa_html($post['title']).'</a>';
			$out .= '</div>';
			$out .= '</div>';
		}
		$out .= '</div>';

		echo "QA_AJAX_RESPONSE\n1\n";

		echo $out."\n";
	}
}
