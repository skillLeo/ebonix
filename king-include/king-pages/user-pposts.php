<?php

require_once QA_INCLUDE_DIR.'king-db/selects.php';
require_once QA_INCLUDE_DIR.'king-app/format.php';

	
	$userid=qa_get_logged_in_userid();

	list($questions, $users)=qa_db_select_with_pending(
		ai_get_private_posts($userid, 'created', 0, null, null, 'Q_HIDDEN', true),
		QA_FINAL_EXTERNAL_USERS ? null : qa_db_user_favorite_users_selectspec($userid)
	);
	
	$usershtml=qa_userids_handles_html(QA_FINAL_EXTERNAL_USERS ? $questions : array_merge($questions, $users));

	
//	Prepare and return content for theme

	$qa_content=qa_content_prepare(true);
	if ( ! $questions) {
		$qa_content['custom'] = '<div class="nopost"><i class="far fa-frown-open fa-4x"></i> '.qa_lang('main/no_questions_found').'</div>';
	}
	$qa_content['title']=qa_lang_html('misc/pposts');
	

	$qa_content['q_list']=array(		
		'qs' => array(),
	);
	
	if (count($questions)) {
		$qa_content['q_list']['form']=array(
			'tags' => 'method="post" action="'.qa_self_html().'"',

			'hidden' => array(
				'code' => qa_get_form_security_code('vote'),
			),
		);
		
		$defaults=qa_post_html_defaults('Q');
			
		foreach ($questions as $question) {
			$qa_content['q_list']['qs'][]=qa_post_html_fields($question, $userid, qa_cookie_get(),
			$usershtml, null, qa_post_html_options($question, $defaults));
		}
		
			
	}	
	$qa_content['navigation']['sub']=qa_user_sub_navigation(qa_get_logged_in_handle(), 'pposts', true);
	$qa_content['class']=' full-page';
	$qa_content['profile'] = true;
	return $qa_content;







