<?php
class king_favs{
	private $directory;
	private $urltoroot;
	public function load_module($directory, $urltoroot)
	{
		$this->directory=$directory;
		$this->urltoroot=$urltoroot;
	}
		public function suggest_requests() // for display in admin interface
		{
			return array(
				array(
					'title' => 'Favorites',
					'request' => 'aifavs',
						'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
					),
			);
		}
		public function match_request($request)
		{
			return $request == 'aifavs';
		}
		
		
		public function process_request($request)
		{
			$qa_content = qa_content_prepare();
			qa_set_template('aifavs');
			require_once QA_INCLUDE_DIR.'king-db/selects.php';
			require_once QA_INCLUDE_DIR.'king-app/format.php';

			$userid=qa_get_logged_in_userid();

			list($questions, $users, $tags, $categories)=qa_db_select_with_pending(
				qa_db_user_favorite_qs_selectspec($userid),
				QA_FINAL_EXTERNAL_USERS ? null : qa_db_user_favorite_users_selectspec($userid),
				qa_db_user_favorite_tags_selectspec($userid),
				qa_db_user_favorite_categories_selectspec($userid)
			);

			$usershtml=qa_userids_handles_html(QA_FINAL_EXTERNAL_USERS ? $questions : array_merge($questions, $users));

			$qa_content=qa_content_prepare(true);
			if (!isset($userid) || ! $questions) {
				$qa_content['custom'] = '<div class="nopost"><i class="fa-regular fa-heart fa-4x"></i> '.qa_lang('kingai_lang/nfound').'</div>';
			}
			$qa_content['title']=qa_lang_html('misc/my_favorites_title');


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

				foreach ($questions as $question)
					$qa_content['q_list']['qs'][]=qa_post_html_fields($question, $userid, qa_cookie_get(),
						$usershtml, null, qa_post_html_options($question, $defaults));
			}

			$qa_content['class']=' full-page';

			return $qa_content;
		}
	}