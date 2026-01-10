<?php
/*

	File: king-include/king-page/membership-me.php
	Description: Controller for page listing recent questions without upvoted/selected/any answers


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

	require_once QA_INCLUDE_DIR . 'king-db/selects.php';
	require_once QA_INCLUDE_DIR . 'king-app/format.php';
	require_once QA_INCLUDE_DIR . 'king-db/metas.php';
	$userid = qa_get_logged_in_userid();
	$now = date( 'Y-m-d' );

//	Prepare content for theme

	$qa_content = qa_content_prepare();

	if ( ! qa_opt('enable_membership') || ! qa_is_logged_in() ) {
		return $qa_content;
	}

	$qa_content['title'] = qa_lang_html('misc/layout_membership');

	$checkid = qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^membership WHERE userid=$ ORDER BY id DESC', $userid), true);
	if ($checkid) {
	
	$type = qa_opt('plan_n_'.$checkid['type']);
	$dr = ( '0' !== $type ) ? $type : '';
	}
	$output = '<h1>' . qa_lang_html('misc/me_membership') . '</h1>';
	$output .= '<div class="membership-me">';
	$output .= '<label>' . qa_lang_html('misc/plan_name') . '</label>';
	if ($checkid) {
	$output .= '<h3>'.qa_opt('plan_'.$checkid['type'].'_title').'</h3>';

	$output .= '<span>'.qa_html($dr).' '.qa_opt('plan_t_'.$checkid['type']).'</span>';
	}
	$output .= '<label>' . qa_lang_html('misc/plan_paid') . '</label>';
	if ($checkid) {
	$output .= '<h3>'.money_symbol().''.qa_opt('plan_usd_'.$checkid['type']).'</h3>';
	}
	$odate = qa_db_usermeta_get($userid, 'membership');

	$output .= '<label>' . qa_lang_html('misc/membership_ex') . '</label>';
	$output .= '<h3>' . $odate . '</h3>';

	$output .= '<label>' . qa_lang_html('misc/plan_status') . '</label>';
	if (!$odate) {
		$output .= '<h3></h3>';
	} elseif ( $now > $odate ) {
		$output .= '<h3>'.qa_lang_html('misc/membership_exed').'</h3>';
	} else {
		$output .= '<h3>'.qa_lang_html('misc/membership_active').'</h3>';
	}

	if ( qa_opt('ailimits') || qa_opt('ulimits') ) {
		$mp  = qa_db_usermeta_get( $userid, 'membership_plan' );
		$pl = null;
		if ($mp) {
			$pl = (INT)qa_opt('plan_'.$mp.'_lmt');
		} elseif (qa_opt('ulimits')) {
			$pl = (INT)qa_opt('ulimit');
		}
		$alm = (INT)qa_db_usermeta_get( $userid, 'ailmt' );
		if ($pl) {			
			$output .= '<label>'.qa_lang('misc/aicredits').'</label>';
			$output .= '<h3>'.$alm.' / '.$pl.'</h3>';
		}
	}


	$output .= '<a href="'.qa_path_html('membership').'" class="see-plans">'.qa_lang_html('misc/other_plans').'</a>';
	$output .= '</div>';
	$qa_content['custom'] = $output;

	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/