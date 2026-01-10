<?php
/*

	File: king-include/king-page/membership.php
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

require_once QA_INCLUDE_DIR.'king-db/selects.php';
require_once QA_INCLUDE_DIR.'king-app/format.php';


if (isset( $_POST['mplan'] ) && qa_check_form_security_code('paypal', qa_post_text('code'))) {

	$enableSandbox = qa_opt('paypal_sandbox');
	// PayPal settings. Change these to your account details and the relevant URLs
	// for your site.
	$pageurl = qa_opt('site_url');
	$paypalConfig = [
		'email' => qa_opt('paypal_email'),
		'return_url' => qa_path_absolute('membership', array('pay' => 'succes')),
		'cancel_url' => qa_path_absolute('membership', array('pay' => 'error')),
		'notify_url' => $pageurl.'king-include/paypal.php'
	];
	
	$paypalUrl = $enableSandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';


    $data = [];
    foreach ($_POST as $key => $value) {
        $data[$key] = stripslashes($value);
    }

    // Set the PayPal account.
    $data['business'] = $paypalConfig['email'];

    // Set the PayPal return addresses.
    $data['return'] = stripslashes($paypalConfig['return_url']);
    $data['cancel_return'] = stripslashes($paypalConfig['cancel_url']);
    $data['notify_url'] = stripslashes($paypalConfig['notify_url']);

    // Set the details about the product being purchased, including the amount
    // and currency so that these aren't overridden by the form data.
    $type = isset( $_POST['mplan'] ) ? $_POST['mplan'] : '';
    $uid = isset( $_POST['userid'] ) ? $_POST['userid'] : '';
    if ( qa_opt('enable_membership') ) {
        $usd = qa_opt('plan_usd_'.$type);
        $data['item_name'] = qa_opt('plan_'.$type.'_title');
    } else {
        $usd = $type;
        $data['item_name'] = 'buy credits';
    }

    $amount = $usd . '.00';

    
    $data['amount'] = $amount;
    $data['currency_code'] = qa_opt('currency');
    $data['item_number'] = $type;
    $data['custom'] = $uid;
    // Add any custom fields for the query string.
    //$data['custom'] = USERID;

    // Build the query string from the data.
    $queryString = http_build_query($data);

    header('location:' . $paypalUrl . '?' . $queryString);
    exit();


}


if ( qa_get( 'pay' ) ) {
	$sclass = 'step-3';
} else {
	$sclass = '';
}

$qa_content = qa_content_prepare();



$qa_content['title'] = qa_lang_html('misc/layout_membership');
$qa_content['script_src'][] = 'https://js.stripe.com/v3/';

$output = '<div class="king-membership '.$sclass.'" id="membership">';

$output .= '<div class="membership-up"><span class="active">1</span><span>2</span><span>3</span></div>';

if ( qa_opt('enable_membership') ) {
	$output .= '<div class="membership-plans">';
	if (qa_opt('plan_1')) {
		$output .= '<input type="radio" id="ms1" name="mperiod" value="1" onclick="memClick(this);" />
		<label for="ms1" class="membership-plan"><h3>'.qa_opt('plan_1_title').'</h3><span>'.( '0' !== qa_opt('plan_n_1') ? qa_opt('plan_n_1') : '' ).' '.qa_opt('plan_t_1').'</span><span>'.qa_opt('plan_1_desc').'</span><div>'.money_symbol().''.qa_opt('plan_usd_1').'</div></label>';
	}
	if (qa_opt('plan_2')) {
		$output .= '<input type="radio" id="ms2" name="mperiod" value="2" onclick="memClick(this);" />
		<label for="ms2" class="membership-plan"><h3>'.qa_opt('plan_2_title').'</h3><span>'.( '0' !== qa_opt('plan_n_2') ? qa_opt('plan_n_2') : '' ).' '.qa_opt('plan_t_2').'</span><span>'.qa_opt('plan_2_desc').'</span><div>'.money_symbol().''.qa_opt('plan_usd_2').'</div></label>';
	}
	if (qa_opt('plan_3')) {
		$output .= '<input type="radio" id="ms3" name="mperiod" value="3" onclick="memClick(this);" />
		<label for="ms3" class="membership-plan"><h3>'.qa_opt('plan_3_title').'</h3><span>'.( '0' !== qa_opt('plan_n_3') ? qa_opt('plan_n_3') : '' ).' '.qa_opt('plan_t_3').'</span><span>'.qa_opt('plan_3_desc').'</span><div>'.money_symbol().''.qa_opt('plan_usd_3').'</div></label>';
	}
	if (qa_opt('plan_4')) {
		$output .= '<input type="radio" id="ms4" name="mperiod" value="4" onclick="memClick(this);" />
		<label for="ms4" class="membership-plan unl"><h3>'.qa_opt('plan_4_title').'</h3><span>'.( '0' !== qa_opt('plan_n_4') ? qa_opt('plan_n_4') : '' ).' '.qa_opt('plan_t_4').'</span><span>'.qa_opt('plan_4_desc').'</span><div>'.money_symbol().''.qa_opt('plan_usd_4').'</div></label>';
	}
	$output .= '</div>';
} elseif ( qa_opt('enable_credits') ) {
	require_once QA_INCLUDE_DIR . 'king-db/metas.php';
	$output .= '<div class="membership-credits">';

	$cre = qa_db_usermeta_get(qa_get_logged_in_userid(), 'credit');
	$ucre = !empty( $cre ) ? $cre : 0;
	$csize = !empty( qa_opt('credits_size') ) ? qa_opt('credits_size') : 1;
	$credit = 10 * $csize;
	$formattedCredit = number_format($credit, 0, '.', ',');
	$output .= '<div class="kingcre-input">';
	$output .= '<label class="aiplabel">' . qa_lang_html( 'misc/credits' ) . '</label><h1>' . qa_html( $ucre ) . '</h1>';
	$output .= '<div class="creinput">
					<span>$</span>
					<input type="number" id="credit-box" oninput="calculateResult(this.value, '.$csize.')" name="price" value="10" max="1000" min="10" class="king-form-tall-text" autocomplete="off">
					<strong><i class="fa-solid fa-coins"></i> <div id="result">'.qa_html($formattedCredit).'</div></strong>
				</div>';
	$output .= '</div>';
	$output .= '</div>';
}

$act = '';
if ( ! qa_opt('enable_stripe') ) { 
	$act = 'active';
}


$output .= '<div class="membership-payments tab-content">';
if (qa_opt('enable_m_msg')) {
	$output .= '<div class="king-mem-m">'.qa_opt('membership_msg').'</div>';
}
$output .= '<ul class="nav-tabs" role="tablist" style="margin-top:20px;">';
if ( qa_opt('enable_stripe') ) { 
	$output .= '<li class="active"><a href="#payment-box" aria-controls="vidup" class="king-vidurl" role="tab" data-toggle="tab"><i class="fa-regular fa-credit-card"></i> '.qa_lang_html('misc/stripe').'</a></li>';
}
if ( qa_opt('enable_paypal') ) { 
	$output .= '<li class="'.qa_html($act).'"><a href="#mem-paypal" aria-controls="vidup" class="king-vidup" role="tab" data-toggle="tab"><i class="fa-brands fa-cc-paypal"></i> '.qa_lang_html('misc/paypal').'</a></li>';
}
$output .= '</ul>';

if ( qa_opt('enable_stripe') ) { 
	$output .= '<div id="payment-box" class="tab-pane active"
	data-consumer-key="'.qa_opt('stripe_pkey').'"
	data-create-order-url="' . current_url() . 'king-include/create.php"
	data-return-url="' . qa_path_absolute('membership', array('pay' => 'succes')) . '">
	<form id="payment-form">
	<input type="hidden" name="customer_name" class="king-form-tall-text" id="customer_name" value="'.qa_get_logged_in_user_field('handle').'">
	<input type="hidden" name="mplan" class="king-form-tall-text" id="mem_plan" value="">
	<input type="hidden" name="email" class="king-form-tall-text" id="email" value="'.qa_get_logged_in_user_field('email').'">

	<div id="payment-element">
	<!--Stripe.js injects the Payment Element-->
	</div>
	<button id="submit" class="mem-button"><span id="button-text">'.qa_lang_html('misc/paynow').'</span></button>
	<div class="loader hide" id="spinner"></div>
	<div id="payment-message" class="hide"></div>
	</form>
	<p id="card-error" role="alert"></p>
	</div>';
}
if ( qa_opt('enable_paypal') ) { 
	$output .= '<div id="mem-paypal" class="tab-pane '.qa_html($act).'">';
	$submit2 = qa_get_form_security_code('paypal');
	$output .= '<div class="mem-info"><i class="fa-brands fa-cc-paypal fa-2x" style="margin-bottom:20px;"></i><p>'.qa_lang_html('misc/paypal_info').'</p></div>';
	
	$output .= '<form class="paypal" action="" method="post" id="paypal_form">
	<input type="hidden" name="cmd" value="_xclick" />
	<input type="hidden" name="no_note" value="1" />
	<input type="hidden" name="lc" value="UK" />
	<input type="hidden" name="bn" value="PP-BuyNowBF:btn_buynow_LG.gif:NonHostedGuest" />
	<input type="hidden" name="first_name" value="Customers First Name" />
	<input type="hidden" name="last_name" value="Customers Last Name" />

	<input type="hidden" name="mplan" class="king-form-tall-text" id="memp_plan" value="">
	<input type="hidden" name="userid" value="'.qa_get_logged_in_userid().'">
	<input type="hidden" name="item_number" value="1" / >
	<input type="hidden" name="code" value="'.$submit2.'">
	<button type="submit" class="mem-button" name="submit" ><i class="fa-brands fa-paypal"></i> '.qa_lang_html('misc/paynow').'</button>
	</form>';
	$output .= '</div>';
	
}
$output .= '</div>';
if ( qa_opt('enable_membership') ) {
	$output .= '<button class="mem-next" id="memnext" onclick="memnext()" disabled><i class="fa-solid fa-arrow-right"></i></button>';

} else {
	$output .= '<button class="mem-next" id="memnext" onclick="cmemnext()" ><i class="fa-solid fa-arrow-right"></i></button>';
}


if ( qa_get( 'pay' ) === 'succes' ) {
	$output .= '<div class="mem-message"><p><i class="fa-regular fa-circle-check"></i></p><h3>'.qa_lang_html('misc/mems_message').'</h3></div>';
} else {
	$output .= '<div class="mem-message"><p><i class="fa-regular fa-circle-xmark"></i></p><h3>'.qa_lang_html('misc/mem_emessage').'</h3></div>';
}
$output .= '</div>';


$qa_content['custom'] = $output;

return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/