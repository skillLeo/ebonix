<?php
/*

	File: king-include/king-page-questions.php
	Description: Controller for page listing recent questions


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

	require_once QA_INCLUDE_DIR.'king-db/selects.php';
	require_once QA_INCLUDE_DIR.'king-app/format.php';
	require_once QA_INCLUDE_DIR.'king-app/q-list.php';

	$categoryslugs=qa_request_parts(1);
	$countslugs=count($categoryslugs);
	$sort=($countslugs && !QA_ALLOW_UNINDEXED_QUERIES) ? null : qa_get('sort');
	$time=($countslugs && !QA_ALLOW_UNINDEXED_QUERIES) ? null : qa_get('time');
	$format=($countslugs && !QA_ALLOW_UNINDEXED_QUERIES) ? null : qa_get('format');
	$filter = ($countslugs && !QA_ALLOW_UNINDEXED_QUERIES) ? null : qa_get('filter');
	$start=qa_get_start();
	$userid=qa_get_logged_in_userid();


//	Get list of questions, plus category information

	switch ($sort) {
		case 'hot':
			$selectsort='hotness';
			break;

		case 'votes':
			$selectsort='netvotes';
			break;

		case 'answers':
			$selectsort='acount';
			break;

		case 'views':
			$selectsort='views';
			break;

		default:
			$selectsort='created';
			break;
	}
	switch ($time) {
		case 'week':
			$timez='week';
			break;

		case 'month':
			$timez='month';
			break;

		case 'year':
			$timez='year';
			break;
		default:
			$timez='all';
			break;
	}

	list($questions, $categories, $categoryid, $fcount)=qa_db_select_with_pending(
		qa_db_qs_selectspec_home($userid, $selectsort, $timez, $format, $filter, $start, $categoryslugs, null, false, false, qa_opt_if_loaded('page_size_qs')),
		qa_db_category_nav_selectspec($categoryslugs, false, false, true),
		$countslugs ? qa_db_slugs_to_category_id_selectspec($categoryslugs) : null,
		qa_db_qs_selectspec_home($userid, $selectsort, $timez, $format, $filter, 0, $categoryslugs, null, false, false, 9999999, true),
	);
	
	if ($countslugs) {
		if (!isset($categoryid)) {
			return include QA_INCLUDE_DIR.'king-page-not-found.php';
		}

		$categorytitlehtml=qa_html($categories[$categoryid]['title']);
		$nonetitle=qa_lang_html_sub('main/no_questions_in_x', $categorytitlehtml);

	} else {
		$nonetitle=qa_lang_html('main/no_questions_found');
	}
	if ($filter) { // Ascending order
		$questions = array_reverse($questions);
	}

	$categorypathprefix=QA_ALLOW_UNINDEXED_QUERIES ? 'home/' : null; // this default is applied if sorted not by recent
	$feedpathprefix=null;

	$linkparams = array();

	if (isset($sort)) {
		$linkparams['sort'] = $sort;
	}
	
	if (isset($time)) {
		$linkparams['time'] = $time;
	}
	
	if (isset($format)) {
		$linkparams['format'] = $format;
	}
	if (isset($filter)) {
		$linkparams['filter'] = $filter;
	}
	switch ($sort) {
		case 'hot':
			$sometitle=$countslugs ? qa_lang_html_sub('main/hot_qs_in_x', $categorytitlehtml) : qa_lang_html('main/hot_qs_title');
			$feedpathprefix=qa_opt('feed_for_hot') ? 'hot' : null;
			break;

		case 'votes':
			$sometitle=$countslugs ? qa_lang_html_sub('main/voted_qs_in_x', $categorytitlehtml) : qa_lang_html('main/voted_qs_title');
			break;

		case 'answers':
			$sometitle=$countslugs ? qa_lang_html_sub('main/answered_qs_in_x', $categorytitlehtml) : qa_lang_html('main/answered_qs_title');
			break;

		case 'views':
			$sometitle=$countslugs ? qa_lang_html_sub('main/viewed_qs_in_x', $categorytitlehtml) : qa_lang_html('main/viewed_qs_title');
			break;

		default:
			$sometitle=$countslugs ? qa_lang_html_sub('main/recent_qs_in_x', $categorytitlehtml) : qa_lang_html('main/recent_qs_title');
			$categorypathprefix='home/';
			$feedpathprefix=qa_opt('feed_for_questions') ? 'home' : null;
			break;
	}


//	Prepare and return content for theme
$count = count($fcount);
	$qa_content=qa_q_list_page_content(
		$questions, // questions
		qa_opt('page_size_qs'), // questions per page
		$start, // start offset
		$count, // total count
		$sometitle, // title if some questions
		$nonetitle, // title if no questions
		$categories, // categories for navigation
		$categoryid, // selected category id
		true, // show question counts in category navigation
		$categorypathprefix, // prefix for links in category navigation
		$feedpathprefix, // prefix for RSS feed paths
		false, // suggest what to do next
		$linkparams, // extra parameters for page links
		$linkparams // category nav params
	);
	$qa_content['class']=' full-page';
	$qa_content['sside']=true;

		$qa_content['navigation']['sub']=qa_qs_sub_navigation($sort, $categoryslugs);

if (!$questions) {
	$qa_content['custom'] = '<div class="nopost"><i class="far fa-frown-open fa-4x"></i> '.qa_lang_html('main/no_unselected_qs_found').'</div>';
}

	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/