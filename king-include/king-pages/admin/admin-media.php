<?php
/*

	File: king-include/king-page-admin-approve.php
	Description: Controller for admin page showing new users waiting for approval


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

require_once QA_INCLUDE_DIR.'king-app/admin.php';
require_once QA_INCLUDE_DIR.'king-db/admin.php';
require_once QA_INCLUDE_DIR.'king-app/posts.php';


//	Check we're not using single-sign on integration

if (QA_FINAL_EXTERNAL_USERS)
	qa_fatal_error('User accounts are handled by external code');


//	Find most flagged questions, answers, comments

$userid=qa_get_logged_in_userid();
$start = qa_get_start();



list($count, $posts) = qa_db_select_with_pending(
	qa_db_selectspec_count(qa_db_media_selectspec()),
	qa_db_media_selectspec($start, 20)
);

$pagesize = 20;

$usercount = $count['count'];

//	Check admin privileges (do late to allow one DB query)

if (qa_get_logged_in_level()<QA_USER_LEVEL_MODERATOR) {
	$qa_content=qa_content_prepare();
	$qa_content['error']=qa_lang_html('users/no_permission');
	return $qa_content;
}



//	Check to see if any were approved or blocked here

$pageerror=qa_admin_check_clicks();


//	Prepare content for theme

$qa_content=qa_content_prepare();

$qa_content['title']=qa_lang_html('admin/approve_users_title');
$qa_content['error']=isset($pageerror) ? $pageerror : qa_admin_page_error();


$output = '';

			if (count($posts)) {
				$output .= '<table class="editusers-table">';
				$output .= '<tr><th>Media</th><th>Name</th><th>Format</th><th>Delete</th></tr>';		
				foreach ($posts as $post) {
					$img = king_get_uploads($post['id']);
					$idp = $post['id'] + 1;
					$output .= '<tr class="kingeditli" id="deletemedia-'.$post['id'].'">';
					if ( 'video/quicktime' === $post['format'] || 'video/mp4' === $post['format'] ) {
						$output .= '<td><video id="my-video" class="video-js vjs-theme-forest short-video edit-media" controls preload="auto" data-setup="{}" ><source src="'.qa_html($img['furl']).'" type="video/mp4" ng-show="input.prev"/></video></td>';
					} else {
						$output .= '<td><img src="'.qa_html($img['furl']).'" class="edit-media"/></td>';
					}
						$output .= '<td><b>'.basename($post['content']).'</b></td>';

					$output .= '<td>'.$post['format'].'</td>';
					$output .= '<td>';

					$output .= '<a href="#" onclick="return king_del_media(this, '.$post['id'].', '.$idp.');" class="king-edit-button">'.qa_lang_html('question/delete_button').'</a>';


					$output .= '</td>';
					$output .= '</tr>';
				}
				$output .= '</tr></table>';
			} else {
				$qa_content['title']=qa_lang_html('admin/no_unapproved_found');
			}
			$qa_content['custom']=$output;
			$qa_content['script_rel'][]='king-content/king-admin.js?'.QA_VERSION;
			$qa_content['script_var']['qa_warning_recalc']=qa_lang('admin/stop_recalc_warning');
			$qa_content['page_links'] = qa_html_page_links(qa_request(), $start, $pagesize, $usercount, qa_opt('pages_prev_next'));
			$qa_content['navigation']['sub']=qa_admin_sub_navigation();
			$qa_content['navigation']['kingsub']=king_sub_navigation();

			return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/
