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

 if (!defined('QA_VERSION')) {
    header('Location: ../');
    exit;
}

// Add these lines
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

require_once QA_INCLUDE_DIR.'king-app/format.php';
require_once QA_INCLUDE_DIR.'king-app/limits.php';
require_once QA_INCLUDE_DIR.'king-db/selects.php';
require_once QA_INCLUDE_DIR.'king-util/sort.php';
require_once QA_INCLUDE_DIR.'king-db/metas.php';
require_once QA_INCLUDE_DIR.'king-app/posts.php';

$in = array();

$followpostid     = qa_get('follow');
$in['categoryid'] = qa_clicked('doask') ? qa_get_category_field_value('category') : qa_get('cat');
$userid           = qa_get_logged_in_userid();
$handle           = qa_get_logged_in_handle();

list($categories, $followanswer, $completetags) = qa_db_select_with_pending(
	qa_db_category_nav_selectspec($in['categoryid'], true),
	isset($followpostid) ? qa_db_full_post_selectspec($userid, $followpostid) : null,
	qa_db_popular_tags_selectspec(0, QA_DB_RETRIEVE_COMPLETE_TAGS)
);

if (!isset($categories[$in['categoryid']])) {
	$in['categoryid'] = null;
}

if (@$followanswer['basetype'] != 'A') {
	$followanswer = null;
}

$permiterror = qa_user_maximum_permit_error('permit_post_q', QA_LIMIT_QUESTIONS);

if ($permiterror && qa_clicked('doask')) {
	$errors = array();
	$errors['permiterror'] = qa_lang_html('question/ask_limit');
	$response['status'] = 'error';
	$response['message'] = $errors;
	echo json_encode($response);
	exit;
}

if ($permiterror || ! qa_opt('king_leo_enable')) {
	$qa_content = qa_content_prepare();

	switch ($permiterror) {
		case 'login':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent = qa_insert_login_links(qa_lang_html('question/ask_must_login'), qa_request(), isset($followpostid) ? array('follow' => $followpostid) : null);
		break;

		case 'confirm':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent = qa_insert_login_links(qa_lang_html('question/ask_must_confirm'), qa_request(), isset($followpostid) ? array('follow' => $followpostid) : null);
		break;

		case 'limit':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent=qa_lang_html('question/ask_limit');
		break;

		case 'membership':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent=qa_insert_login_links(qa_lang_html('misc/mem_message'));
		$qa_content['custom'] = '<div class="nopost"><i class="fa-solid fa-fingerprint fa-4x"></i><p>'.$econtent.'</p><a href="'. qa_path_html( 'membership' ) .'" class="meme-button">'.qa_lang_html('misc/see_plans').'</a></div>';
		break;

		case 'approve':
		$qa_content['error']=qa_lang_html('users/no_permission');
		$econtent=qa_lang_html('question/ask_must_be_approved');
		break;

		default:
		$econtent=qa_lang_html('users/no_permission');
		$qa_content['error']=qa_lang_html('users/no_permission');
		break;
	}

	if (empty($qa_content['custom'] )) {
		$qa_content['custom'] = '<div class="nopost"><i class="fa-solid fa-circle-user fa-4x"></i>'.$econtent.'</div>';
	}
	return $qa_content;
}

$captchareason = qa_user_captcha_reason();
$in['title'] = qa_get_post_title('title');

if (qa_using_tags()) {
	$in['tags'] = qa_get_tags_field_value('tags');
}

if (qa_clicked('doask')) {
    require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
	require_once QA_INCLUDE_DIR . 'king-app/post-update.php';
    require_once QA_INCLUDE_DIR . 'king-util/string.php';
	$in['postid'] = qa_post_text('uniqueid');
	$post=qa_db_select_with_pending(qa_db_full_post_selectspec($userid, $in['postid']));
    $categoryids = array_keys(qa_category_path($categories, @$in['categoryid']));
    $userlevel   = qa_user_level_for_categories($categoryids);

    $in['nsfw']   = qa_post_text('nsfw');
    $in['prvt']   = qa_post_text('prvt');
	qa_get_post_content('editor', 'content', $in['editor'], $in['content'], $in['format'], $in['text']);

	$errors = array();
    if (!qa_check_form_security_code('ask', qa_post_text('code'))) {
        $errors['page'] = qa_lang_html('misc/form_security_again');
    } else {
        $filtermodules = qa_load_modules_with('filter', 'filter_question');
        foreach ($filtermodules as $filtermodule) {
            $oldin = $in;
            $filtermodule->filter_question($in, $errors, null);
            qa_update_post_text($in, $oldin);
        }

        if (qa_using_categories() && count($categories) && (!qa_opt('allow_no_category')) && !isset($in['categoryid'])) {
            $errors['categoryid'] = qa_lang_html('question/category_required');
        } elseif (qa_user_permit_error('permit_post_q', null, $userlevel)) {
            $errors['categoryid'] = qa_lang_html('question/category_ask_not_allowed');
        }

        if ($captchareason) {
            require_once QA_INCLUDE_DIR . 'king-app/captcha.php';
            qa_captcha_validate_post($errors);
        }

		if ( isset( $errors['title'] ) ) {
			$errors['title'] = qa_lang_html('main/title_field');
		}

        if (empty($errors)) {
            $cookieid = isset($userid) ? qa_cookie_get() : qa_cookie_get_create();

			king_update_ai_post($in['postid'], $in['title'], isset($in['tags']) ? qa_tags_to_tagstring($in['tags']) : '', $in['nsfw'], 'I');

			$answers         = qa_post_get_question_answers( $in['postid'] );
			$commentsfollows = qa_post_get_question_commentsfollows( $in['postid'] );
			$closepost       = qa_post_get_question_closepost( $in['postid'] );

			if ( qa_using_categories() && isset($in['categoryid']) ){
				qa_question_set_category( $post, $in['categoryid'], $userid, $handle, $cookieid,
					$answers, $commentsfollows, $closepost, false );
			}
            if (isset($in['prvt'])) {
                qa_post_set_hidden($in['postid'], true, null);
            }
            $response['status'] = 'success';
            $response['message'] = qa_lang_html('misc/published');
			$response['url'] = qa_q_request($in['postid'], $in['title']);
			$response['message2'] = qa_lang_html('misc/seep');
        } else {
            $response['status'] = 'error';
            $response['message'] = $errors;
        }
        echo json_encode($response);
        exit;
    }
}

if (qa_is_logged_in() && ( qa_opt('ailimits') || qa_opt('ulimits') ) && qa_get_logged_in_level() <= QA_USER_LEVEL_ADMIN && qa_opt('enable_membership')) {
	$qa_content = qa_content_prepare();
	$mp  = qa_db_usermeta_get( $userid, 'membership_plan' );
	$pl = null;
	if ($mp) {
		$pl = (INT)qa_opt('plan_'.$mp.'_lmt');
	} elseif(qa_opt('ulimits')) {
		$pl = (INT)qa_opt('ulimit');
	}
	$alm = (INT)qa_db_usermeta_get( $userid, 'ailmt' );
	if ($alm >= $pl) {
		$qa_content['custom'] = '<div class="nopost"><i class="fa-solid fa-circle-user fa-4x"></i>'.qa_lang('misc/nocredits').'<p><a href="'.qa_path_html('membership').'">'.qa_lang('misc/buycredits').'</a></p></div>';
		return $qa_content;
	}
}

$qa_content = qa_content_prepare(false, array_keys(qa_category_path($categories, @$in['categoryid'])));

$qa_content['title'] = qa_lang_html('main/image');
$qa_content['error'] = @$errors['page'];

$field['label'] = qa_lang_html('question/q_content_label');
$field['error'] = qa_html(@$errors['content']);

$custom = qa_opt('show_custom_ask') ? trim(qa_opt('custom_ask')) : '';

// ========== DEFINE MODELS (FIXED) ==========
$models = array(
	'sdn' => array(
		'enabled' => qa_opt('enable_sdn'),
		'label' => qa_lang('misc/sdn'),
	),
	'flux_pro' => array(
		'enabled' => qa_opt('enable_flux_pro'),
		'label' => qa_lang('misc/flux_pro'),
	),
	'sdream' => array(
		'enabled' => qa_opt('enable_sdream'),
		'label' => qa_lang('misc/sdream'),
	),
	'banana' => array(
		'enabled' => qa_opt('enable_banana'),
		'label' => qa_lang('misc/banana'),
	),
	'sd' => array(
		'enabled' => qa_opt('enable_sd'),
		'label' => qa_lang('misc/sd'),
	),
	'flux' => array(
		'enabled' => qa_opt('enable_flux'),
		'label' => qa_lang('misc/flux'),
	),
	'realxl' => array(
		'enabled' => qa_opt('enable_realxl'),
		'label' => qa_lang('misc/realxl'),
	),
	'imagen4' => array(
		'enabled' => qa_opt('enable_imagen4'),
		'label' => qa_lang('misc/imagen4'),
	),
	'fluxkon' => array(
		'enabled' => qa_opt('enable_fluxkon'),
		'label' => qa_lang('misc/fluxkon'),
	),
	'de' => array(
		'enabled' => qa_opt('enable_de'),
		'label' => qa_lang('misc/de'),
	),
	'de3' => array(
		'enabled' => qa_opt('enable_de3'),
		'label' => qa_lang('misc/de3'),
	),
	'decart_img' => array(
		'enabled' => qa_opt('enable_decart_img'),
		'label' => qa_lang('misc/decart_img'),
	),
	'luma_img' => array(
    'enabled' => qa_opt('enable_luma_img'),
    'label' => qa_lang('misc/luma_img'),
),
);

// Filter enabled models
$enabled_models = array();
foreach ($models as $key => $data) {
	if (!empty($data['enabled'])) {
		$enabled_models[$key] = $data['label'];
	}
}

$first_model_key = key($enabled_models);
$first_model_label = $enabled_models[$first_model_key] ?? 'No Model';

$context = '';
$context .= '<div id="chclass" class="' . qa_html($first_model_key) . '">';
$context .= '<div class="kingai-ext">';
$context .= '<div class="ail-settings">';

// Model dropdown
$context .= '<div class="king-dropdownup custom-select hveo">';
$context .= '<div class="king-sbutton kings-button" id="aimodelbtn" data-toggle="dropdown" aria-expanded="false" role="button">' . qa_html($first_model_label) . '</div>';
$context .= '<div class="king-dropdownc king-dropleft aimodels">';

// Render radio buttons
foreach ($enabled_models as $key => $label) {
	$checked = ($key === $first_model_key) ? 'checked' : '';
	$context .= '<label class="cradio"><input type="radio" name="aimodel" value="' . qa_html($key) . '" class="hide" ' . $checked . ' onclick="updateModelLabel(this)"><span>' . qa_html($label) . '</span></label>';
}

$context .= '</div></div>';
$context .= '<div id="newsthumb" class="dropzone king-poll-file aiupload"></div>';
$context .= '</div>';

// Size tabs
$context .= '<div id="desizes">';
$context .= '<ul class="nav nav-tabs" id="ssize">
	<li class="active"><a href="#aisizes" data-toggle="tab" aria-expanded="true">'.qa_lang('misc/aisizes').'</a></li>
	<li class="sdsize"><a href="#aistyles" data-toggle="tab" aria-expanded="false">'.qa_lang('misc/ai_filter').'</a></li>';
	if (qa_opt('enprompt')) {
		$context .= '<li class="sdsize"><a href="#nprompt" data-toggle="tab" aria-expanded="false">'.qa_lang('misc/ai_nprompt').'</a></li>';
	}
$context .= '</ul>';

$context .= '<div id="aisizes" role="tabpanel" class="tabcontent aistyles active">
<input type="radio" id="aisize9" name="aisize" value="1344x768" class="hide">
<label for="aisize9" class="ailabel sdsize" title="1344x768" data-toggle="tooltip"><i class="king-square s1"></i> '.qa_lang('misc/widescreen').' (16:9)</label>
<input type="radio" id="aisize4" name="aisize" value="1152x896" class="hide">
<label for="aisize4" class="ailabel sdsize" title="1152x896" data-toggle="tooltip"><i class="king-square s2"></i> '.qa_lang('misc/landscape').' (5:4)</label>
<input type="radio" id="aisize10" name="aisize" value="1792x1024" class="hide">
<label for="aisize10" class="ailabel desize3" title="1792x1024" data-toggle="tooltip"><i class="king-square s1"></i> '.qa_lang('misc/widescreen').' (7:4)</label>
<input type="radio" id="aisize1" name="aisize" value="512x512" class="hide">
<label for="aisize1" class="ailabel desize" title="512x512" data-toggle="tooltip"><i class="king-square"></i> '.qa_lang('misc/square').' (1:1)</label>
<input type="radio" id="aisize3" name="aisize" value="1024x1024" class="hide" checked>
<label for="aisize3" class="ailabel" title="1024x1024" data-toggle="tooltip"><i class="king-square"></i> '.qa_lang('misc/square').' (1:1)</label>
<input type="radio" id="aisize11" name="aisize" value="1024x1792" class="hide">
<label for="aisize11" class="ailabel desize3" title="1024x1792" data-toggle="tooltip"><i class="king-square s5"></i> '.qa_lang('misc/vertical').' (4:7)</label>
<input type="radio" id="aisize8" name="aisize" value="896x1152" class="hide">
<label for="aisize8" class="ailabel sdsize" title="896x1152" data-toggle="tooltip"><i class="king-square s4"></i> '.qa_lang('misc/portrait').' (4:5)</label>
<input type="radio" id="aisize5" name="aisize" value="832x1216" class="hide">
<label for="aisize5" class="ailabel sdsize aisize8" title="832x1216" data-toggle="tooltip"><i class="king-square s4"></i> '.qa_lang('misc/vertical').' (2:3)</label>
<input type="radio" id="aisize7" name="aisize" value="768x1344" class="hide">
<label for="aisize7" class="ailabel sdsize" title="768x1344" data-toggle="tooltip"><i class="king-square s5"></i> '.qa_lang('misc/long').' (9:16)</label>
</div>';

if (qa_opt('enprompt')) {
	$context .= '<div id="nprompt" role="tabpanel" class="tabcontent aistyles">';
	$context .= '<textarea name="nprompt" id="n_prompt" rows="2" cols="40" class="king-form-tall-text" placeholder="'.qa_lang('misc/ai_nprompt').'"></textarea>';
	$context .= '</div>';
}

$context .= '<div id="aistyles" role="tabpanel" class="tabcontent aistyles">';
$styles = array('none', '3d-model', 'analog-film', 'anime', 'cinematic', 'comic-book', 'digital-art', 'fantasy-art', 'isometric', 'line-art', 'low-poly', 'neon-punk', 'origami', 'photographic', 'pixel-art');

foreach ($styles as $style) {
    $context .= '<input type="radio" id="aistyle_' . $style . '" name="aistyle" value="' . $style . '" class="hide">';
    $context .= '<label for="aistyle_' . $style . '" class="ailabel">' . $style . '</label>';
}
$context .= '</div></div></div>';

$context .= '<div id="ai-results">'.king_ai_posts($userid, 'aimg').'</div>';

if (qa_is_logged_in()) {
$cont = '';
if ( qa_opt( 'king_leo_enable' ) && qa_opt( 'enable_aivideo' ) ) {
	$cont .= '<ul class="king-nav-kingsub-list" id="nav-kingsub">';
	if ( qa_opt( 'king_leo_enable' ) ) {
		$cont .='<li class="king-nav-kingsub-item">';
		$cont .='<a href="' . qa_path_html( 'submitai' ) . '" class="king-nav-kingsub-selected"><i class="fa-regular fa-image"></i> ' . qa_lang_html( 'misc/king_ai' ) . '</a>';
		$cont .='</li>';
	}
	if ( qa_opt( 'enable_aivideo' ) ) {
		$cont .='<li class="king-nav-kingsub-item">';
		$cont .='<a href="' . qa_path_html( 'videoai' ) . '"><i class="fa-regular fa-circle-play"></i> ' . qa_lang_html( 'misc/king_aivid' ) . '</a>';
		$cont .='</li>';
	}
	$cont .='</ul>';
}

$cont .= '<div class="kingai-box active">
<div class="king-form-tall-error" id="ai-error" style="display: none;"></div>';
if ($custom) {
$cont .= '<div class="snote" >'.$custom.'</div>';
}
$cont .= '<div class="kingai-input">
			<textarea type="textarea" id="ai-box" class="aiinput" oninput="adjustHeight(this)" placeholder="'.qa_lang('misc/aiplace').'" maxlength="600" autocomplete="off" style="height: 44px;" rows="1"></textarea>';
$cont .= '<div class="kingai-buttons">';
if (qa_opt('eprompter')) {
    $showElement = qa_opt('oaprompter') ? (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN) : true;
    if ($showElement) {
        $cont .= '<button type="button" id="prompter" onclick="aipromter(this)" class="king-sbutton ai-create promter" data-toggle="tooltip" title="' . qa_lang('misc/prompter') . '" data-placement="left"><i class="fa-solid fa-feather"></i><div class="loader"></div></button>';
    }
}
$cont .= '<div class="king-sbutton" onclick="toggleSwitcher(\'.kingai-box\', this)" role="button"><i class="fa-solid fa-sliders"></i></div>';
$cont .= '<button type="button" id="ai-submit" class="ai-submit" onclick="return aigenerate(this);">
<span><i class="fa-solid fa-paper-plane"></i> '.qa_lang('misc/generate').'</span><div class="loader"></div></button>
			</div>
		</div>';

$cont .= $context;
$cont .= '</div>';
$qa_content['custom'] = $cont;	
$qa_content['custom'] .= '
<script>
(function(){
	function kingGetReusePayload(){
		var raw = null;
		try{ raw = sessionStorage.getItem("king_ai_reuse"); }catch(e){}
		if(!raw) return null;
		try{
			var data = JSON.parse(raw);
			try{ sessionStorage.removeItem("king_ai_reuse"); }catch(e){}
			return data;
		}catch(e){
			return null;
		}
	}

	function kingSetTextarea(id, val){
		var el = document.getElementById(id);
		if(!el) return;
		el.value = val || "";
		if(typeof adjustHeight === "function"){ try{ adjustHeight(el); }catch(e){} }
	}

	function kingSelectRadio(name, value){
		if(!value) return false;
		var input = document.querySelector(\'input[name="\' + name + \'"][value="\' + CSS.escape(value) + \'"]\');
		if(!input) return false;
		var label = input.closest("label");
		if(label && label.offsetParent === null) return false;
		input.checked = true;
		try{ input.dispatchEvent(new Event("change", {bubbles:true})); }catch(e){}
		try{ input.click(); }catch(e){}
		return true;
	}

	function kingSetImageModel(model){
		if(!model) return;
		var input = document.querySelector(\'input[name="aimodel"][value="\' + CSS.escape(model) + \'"]\');
		if(!input) return;
		input.checked = true;
		try{ input.dispatchEvent(new Event("change", {bubbles:true})); }catch(e){}
		try{ input.click(); }catch(e){}
		var btn = document.getElementById("aimodelbtn");
		if(btn){
			var lbl = input.closest("label");
			if(lbl){
				var t = (lbl.innerText || lbl.textContent || "").trim();
				if(t) btn.innerText = t;
			}
		}
		var ch = document.getElementById("chclass");
		if(ch) ch.className = model;
	}

	document.addEventListener("DOMContentLoaded", function(){
		var payload = kingGetReusePayload();
		if(!payload) return;
		if(payload.isVideo && parseInt(payload.isVideo, 10) === 1) return;
		if(payload.prompt) kingSetTextarea("ai-box", payload.prompt);
		if(payload.model) kingSetImageModel(payload.model);
		if(payload.size) kingSelectRadio("aisize", payload.size);
		if(payload.style) kingSelectRadio("aistyle", payload.style);
		if(payload.nprompt) kingSetTextarea("n_prompt", payload.nprompt);
		var box = document.getElementById("ai-box");
		if(box){ try{ box.focus(); }catch(e){} }
	});
})();
</script>
';

$qa_content['form'] = array(
	'tags'    => 'name="ask" method="post" action="' . qa_self_html() . '" id="ai-form"',
	'style'   => 'tall',
	'fields'  => array(
		'close'    => array(
			'type' => 'custom',
			'html' => '<span onclick="aipublish(this)" class="aisclose"><i class="fa-solid fa-xmark"></i></span>',
		),
		'errorc'    => array(
			'type' => 'custom',
			'html' => '<div id="error-container"></div>',
		),		
		'title'     => array(
			'label' => qa_lang_html('question/q_title_label'),
			'tags'  => 'name="title" id="title" autocomplete="off" minlength="'.qa_opt('min_len_q_title').'"  required',
			'value' => qa_html(@$in['title']),
			'error' => qa_html(@$errors['title']),
		),
		'similar'   => array(
			'type' => 'custom',
			'html' => '<span id="similar"></span>',
		),
		'uniqueid'  => array(
			'label' => '',
			'tags'  => 'name="uniqueid" id="uniqueid" class="hide"',
		),
	),
	'buttons' => array(
		'ask' => array(
			'tags'  => 'onclick="submitAiform(event);" id="submitButton"',
			'label' => qa_lang_html('question/ask_button'),
		),
	),
	'hidden'  => array(
		'code'   => qa_get_form_security_code('ask'),
		'doask'  => '1',
	),
);

script_options($qa_content);
if (!strlen($custom)) {
	unset($qa_content['form']['fields']['custom']);
}

if (qa_opt('do_ask_check_qs') || qa_opt('do_example_tags')) {
	$qa_content['script_rel'][] = 'king-content/king-ask.js?' . QA_VERSION;
	$qa_content['form']['fields']['title']['tags'] .= ' onchange="qa_title_change(this.value);"';
	if (strlen(@$in['title'])) {
		$qa_content['script_onloads'][] = 'qa_title_change(' . qa_js($in['title']) . ');';
	}
}
$qa_content['script_var']['leoai']=qa_path('submitai_ajax');

if (isset($followanswer)) {
	$viewer = qa_load_viewer($followanswer['content'], $followanswer['format']);
	$field = array(
		'type'  => 'static',
		'label' => qa_lang_html('question/ask_follow_from_a'),
		'value' => $viewer->get_html($followanswer['content'], $followanswer['format'], array('blockwordspreg' => qa_get_block_words_preg())),
	);
	qa_array_insert($qa_content['form']['fields'], 'title', array('follows' => $field));
}

if (qa_using_categories() && count($categories)) {
	$field = array(
		'label' => qa_lang_html('question/q_category_label'),
		'error' => qa_html(@$errors['categoryid']),
	);
	qa_set_up_category_field($qa_content, $field, 'category', $categories, $in['categoryid'], true, qa_opt('allow_no_sub_category'));
	if (!qa_opt('allow_no_category')) {
		$field['options'][''] = '';
	}
	qa_array_insert($qa_content['form']['fields'], 'similar', array('category' => $field));
}

if (qa_using_tags()) {
	$field = array(
		'error' => qa_html(@$errors['tags']),
	);
	qa_set_up_tag_field($qa_content, $field, 'tags', isset($in['tags']) ? $in['tags'] : array(), array(),
		qa_opt('do_complete_tags') ? array_keys($completetags) : array(), qa_opt('page_size_ask_tags'));
	qa_array_insert($qa_content['form']['fields'], null, array('tags' => $field));
}

if ( qa_opt('enable_nsfw') || qa_opt('enable_pposts') ) {
	$nsfw = '';
	$prvt = '';
	if ( qa_opt('enable_pposts') ) {
		$prvt = '<input name="prvt" id="king_prvt" type="checkbox" class="hide" value="'.qa_html(@$in['prvt']).'"><label for="king_prvt" class="king-nsfw"><i class="fa-solid fa-user-ninja"></i> '.qa_lang('misc/prvt').'</label>';
	}
	if ( qa_opt('enable_nsfw') ) {
		$nsfw = '<input name="nsfw" id="king_nsfw" type="checkbox" value="'.qa_html(@$in['nsfw']).'"><label for="king_nsfw" class="king-nsfw">'.qa_lang_html('misc/nsfw').'</label>';
	}
	$field = array(
		'type' => 'custom',
		'html' => ''.$prvt.$nsfw.''
	);
	qa_array_insert($qa_content['form']['fields'], null, array('nsfw' => $field));
}

if (!isset($userid)) {
	qa_set_up_name_field($qa_content, $qa_content['form']['fields'], @$in['name']);
}

if ($captchareason) {
	require_once QA_INCLUDE_DIR . 'king-app/captcha.php';
	qa_set_up_captcha_field($qa_content, $qa_content['form']['fields'], @$errors, qa_captcha_reason_note($captchareason));
}

} else {
	$cont2  = '<div class="kingai-input">';
	$cont2 .= '<textarea type="textarea" id="ai-box" class="aiinput" data-toggle="modal" data-target="#loginmodal" placeholder="'.qa_lang('misc/aiplace').'" maxlength="600" autocomplete="off" style="height: 44px;" rows="1"></textarea>';
	$cont2 .= '<div class="kingai-buttons">';
	$cont2 .= '<div class="king-sbutton" data-toggle="modal" data-target="#loginmodal" aria-toggle="true" role="button"><i class="fa-solid fa-sliders"></i></div>';
	$cont2 .= '<button type="button" id="ai-submit" class="ai-submit" data-toggle="modal" data-target="#loginmodal">
<span><i class="fa-solid fa-paper-plane"></i> '.qa_lang('misc/generate').'</span><div class="loader"></div></button>';
	$cont2 .= '</div>';
	$cont2 .= '</div>';
	$qa_content['custom'] = $cont2;
}
$qa_content['class']=' ai-create';
$qa_content['focusid'] = 'ai-box';

return $qa_content;