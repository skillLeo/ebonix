<?php
/*

	File: king-include/king-page-admin-categories.php
	Description: Controller for admin page for editing categories


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
require_once QA_INCLUDE_DIR.'king-db/selects.php';
require_once QA_INCLUDE_DIR.'king-db/admin.php';

ini_set('user_agent', 'Mozilla/5.0');

//	Check admin privileges (do late to allow one DB query)

if (!qa_admin_check_privileges2($qa_content))
	return $qa_content;

//	Process saving options

$savedoptions=false;
$securityexpired=false;
$bundle_id = '19106207';

$king_key = qa_opt('king_key');

$enavato_itemid =  '7877877';
$label = '';
	$code = qa_post_text('king_key');
	$personalToken = "R5QWDaq9cwBv5BtwYFDzVLzaCZEeQzUS";
	$userAgent = "Purchase code verification on http://localhost/env/";
if (!empty($code)) {



// Surrounding whitespace can cause a 404 error, so trim it first
	$code = trim($code);

// Make sure the code looks valid before sending it to Envato
	if (!preg_match("/^([a-f0-9]{8})-(([a-f0-9]{4})-){3}([a-f0-9]{12})$/i", $code)) {
		$label='Invalid code';
	}

// Build the request
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => "https://api.envato.com/v3/market/author/sale?code={$code}",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 20,

		CURLOPT_HTTPHEADER => array(
			"Authorization: Bearer {$personalToken}",
			"User-Agent: {$userAgent}"
		)
	));

// Send the request with warnings supressed
	$response = @curl_exec($ch);

// Handle connection errors (such as an API outage)
// You should show users an appropriate message asking to try again later
	if (curl_errno($ch) > 0) { 
		$label='Error connecting to API: ' . curl_error($ch);
}

// If we reach this point in the code, we have a proper response!
// Let's get the response code to check if the purchase code was found
$responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// HTTP 404 indicates that the purchase code doesn't exist
if ($responseCode === 404) {
	$label='The purchase code was invalid';
}

// Anything other than HTTP 200 indicates a request or API error
// In this case, you should again ask the user to try again later
if ($responseCode !== 200) {
	$label='Failed to validate code due to an error: HTTP {'.$responseCode.'}';
}

// Parse the response into an object with warnings supressed
$body = @json_decode($response);

// Check for errors while decoding the response (PHP 5.3+)
if ($body === false && json_last_error() !== JSON_ERROR_NONE) {
	$label='Error parsing response';
}

// Now we can check the details of the purchase code
// At this point, you are guaranteed to have a code that belongs to you
// You can apply logic such as checking the item's name or ID


if ( isset($body->item->id) ) {
	if ( $body->item->id == $enavato_itemid || $body->item->id == $bundle_id ) {
		$label='DONE !';
		qa_set_option('king_key', qa_post_text('king_key'));
	} else {
		$label='Invalid Purchase code !';
	}
} else {
	$label='Missing Purchase Code !';
}


}
if (qa_clicked('dosaveoptions')) {
	if (!qa_check_form_security_code('admin/categories', qa_post_text('code')))
		$securityexpired=true;

	else {
		$savedoptions=false;
	}
}



//	Prepare content for theme

$qa_content=qa_content_prepare();

$qa_content['title']=qa_lang_html('admin/admin_title').' - '.qa_lang_html('admin/categories_title');
$qa_content['error']=$securityexpired ? qa_lang_html('admin/form_security_expired') : qa_admin_page_error();

$qa_content['form']=array(
	'tags' => 'method="post" action="'.qa_path_html(qa_request()).'"',

	'ok' => $savedoptions ? qa_lang_html('admin/options_saved') : null,

	'style' => 'tall',

	'fields' => array(
		'intro' => array(
			'label' => $label,
			'type' => 'static',
		),
		'name' => array(
			'id' => 'king_key',
			'tags' => 'name="king_key" id="king_key" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"',
			'label' => 'King Media Purchase Code',
			'value' => qa_html(isset($code) ? $code : @$king_key),
			'error' => qa_html(@$errors['king_key']),
		),				
	),

	'buttons' => array(
		'save' => array(
			'tags' => 'name="dosaveoptions" id="dosaveoptions"',
			'label' => qa_lang_html('main/save_button'),
		),

	),

	'hidden' => array(
		'code' => qa_get_form_security_code('admin/categories'),
	),
);

$qa_content['navigation']['sub']=qa_admin_sub_navigation();


return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/