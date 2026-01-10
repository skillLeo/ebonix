<?php

/*
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

class king_hotposts {

	public $voteformcode;


	public function allow_template( $template ) {
		$allow = false;

		switch ( $template ) {
			case 'home':
			case 'question':
			case 'custom':
				$allow = true;
				break;
		}

		return $allow;
	}


	public function allow_region( $region ) {
		return in_array( $region, array( 'side', 'main' ) );
	}
	public function widget_option($wextra) {
		$warray = ( null !== $wextra ) ? unserialize($wextra) : array();
		$number = isset($warray['number']) ? $warray['number'] : '6';
		$woptions = array(
			'pnumber' => array(
				'tags' => 'name="wextra[number]"',
				'label' => qa_lang_html('misc/post_number'),
				'type' => 'text',
				'value' => $number,
			));

		return $woptions;
	}

	public function output_widget( $region, $place, $themeobject, $template, $request, $qa_content, $wtitle = null, $wextra = null ) {
		require_once QA_INCLUDE_DIR . 'king-db/selects.php';


		$userid   = qa_get_logged_in_userid();
		$cookieid = qa_cookie_get();
		$categoryslugs=QA_ALLOW_UNINDEXED_QUERIES ? qa_request_parts(1) : null;
		$warray = ( null !== $wextra ) ? unserialize($wextra) : array();

		$number = isset($warray['number']) ? $warray['number'] : '6';

		$posts =  qa_db_single_select( qa_db_qs_selectspec($userid, 'hotness', 0, $categoryslugs, null, false, false, $number));

		$title = isset( $wtitle ) ? $wtitle : qa_lang_html( 'main/related_qs_title' );

		if ( 'side' == $region ) {
			$themeobject->output(
				'<DIV CLASS="ilgilit">',
				'<div class="widget-title">',
				$title,
				'</div>'
			);
		} elseif ( 'main' == $region ) {
			$themeobject->output(
				'<DIV CLASS="ilgilit under-content">',
				'<div class="widget-title">',
				$title,
				'</div>'
			);
		}

		$themeobject->output( '<div CLASS="ilgili">' );

		foreach ( $posts as $post ) {
			$themeobject->output( get_simple_post( $post ) );
		}

		$themeobject->output( '</div>' );
		$themeobject->output( '</DIV>' );
	}
}

/*
Omit PHP closing tag to help avoid accidental output
 */