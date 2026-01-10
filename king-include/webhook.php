<?php
require_once 'king-base.php';
require_once QA_INCLUDE_DIR . 'king-db/selects.php';
require_once QA_INCLUDE_DIR . 'king-app/options.php';
require_once QA_INCLUDE_DIR . 'king-app/users.php';
require QA_INCLUDE_DIR . 'stripe/init.php';


// The library needs to be configured with your account's secret key.
// Ensure the key is kept out of any version control system you might be using.
$stripe = new \Stripe\StripeClient(qa_opt('stripe_skey'));

// This is your Stripe CLI webhook secret for testing your endpoint locally.
$endpoint_secret = qa_opt('webhook_key');

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
  $event = \Stripe\Webhook::constructEvent(
    $payload, $sig_header, $endpoint_secret
  );
} catch(\UnexpectedValueException $e) {
  // Invalid payload
  http_response_code(400);
  exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
  // Invalid signature
  http_response_code(400);
  exit();
}

// Handle the event
switch ($event->type) {
  case 'payment_intent.succeeded':
    $paymentIntent = $event->data->object;
    $transactionID = $paymentIntent->id; 
    $paidAmount = $paymentIntent->amount; 
    $paidAmount = ($paidAmount/100); 
    $paidCurrency = $paymentIntent->currency; 
    $payment_status = $paymentIntent->status;
    $type = $paymentIntent->description;
    $user_id = $paymentIntent->metadata['user_id'];
    if ($transactionID) {
        if ( qa_opt('enable_membership') ) {
            king_insert_membership($type, $paidAmount, $user_id, $transactionID );
        } else {
            require_once QA_INCLUDE_DIR . 'king-db/metas.php';
            $ocredit = qa_db_usermeta_get($user_id, 'credit');
            $csize = !empty( qa_opt('credits_size') ) ? qa_opt('credits_size') : 1;
            $credit = $paidAmount * $csize;
            $ocredit2 = $ocredit + $credit;
            qa_db_usermeta_set( $user_id, 'credit', $ocredit2 );

        }
    }
  default:
    echo 'Received unknown event type ' . $event->type;
}

http_response_code(200);