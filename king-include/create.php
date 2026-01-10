<?php

    require_once 'king-base.php';
    require_once QA_INCLUDE_DIR . 'king-db/selects.php';
    require_once QA_INCLUDE_DIR . 'king-app/options.php';
    require_once QA_INCLUDE_DIR . 'king-db.php';
    require_once QA_INCLUDE_DIR . 'king-app/users.php';
    require QA_INCLUDE_DIR . 'stripe/init.php';

// This is your test secret API key.
\Stripe\Stripe::setApiKey(qa_opt('stripe_skey'));

$jsonStr = file_get_contents('php://input'); 
$jsonObj = json_decode($jsonStr); 
 
if ( $jsonObj->request_type == 'create_payment_intent' ){ 

try {
    // retrieve JSON from POST body
    $jsonStr = file_get_contents('php://input');
    $jsonObj = json_decode($jsonStr);
    $type = !empty($jsonObj->price)?$jsonObj->price:'';
    if ( qa_opt('enable_membership') ) {
        $usd = qa_opt('plan_usd_'.$type);
    } else {
        $usd = $type;
    }
    $uid = qa_get_logged_in_userid();
    $amount = ( $usd * 100 );
    // Create a PaymentIntent with amount and currency
    $paymentIntent = \Stripe\PaymentIntent::create([
        'currency' => qa_opt('currency'),
        'amount' => $amount,
        'description' => $type,
        'metadata' => [
            'user_id' => $uid,
        ],
        'automatic_payment_methods' => [
            'enabled' => true,
        ],
    ]);


    $output = array(
                'status' => 'success',
                'id' => $paymentIntent->id, 
                'clientSecret'=>$paymentIntent->client_secret
            );

    echo json_encode($output);
} catch (Error $e) {
    http_response_code(500);
    $api_error = $e->getMessage();
    echo json_encode(['error' => $e->getMessage()]);
}
} elseif( $jsonObj->request_type == 'create_customer' ) { 
    $payment_intent_id = !empty($jsonObj->payment_intent_id)?$jsonObj->payment_intent_id:''; 
    $name = !empty($jsonObj->name)?$jsonObj->name:''; 
    $email = !empty($jsonObj->email)?$jsonObj->email:''; 

    // Add customer to stripe 
    try {   
        $customer = \Stripe\Customer::create(array(  
            'name' => $name,  
            'email' => $email 
        ));  
    }catch(Exception $e) {   
        $api_error = $e->getMessage();   
    } 
     
    if(empty($api_error) && $customer){ 
        try { 
            // Update PaymentIntent with the customer ID 
            $paymentIntent = \Stripe\PaymentIntent::update($payment_intent_id, [
                'customer' => $customer->id,
                
            ]); 
        } catch (Exception $e) {  
            // log or do what you want 
        } 
         
        $output = [ 
            'id' => $payment_intent_id, 
            'customer_id' => $customer->id 
        ]; 
        echo json_encode($output); 
    }else{ 
        http_response_code(500); 
        echo json_encode(['error' => $api_error]); 
    } 
} elseif ( $jsonObj->request_type == 'payment_insert' ){ 
    $payment_intent = !empty($jsonObj->payment_intent)?$jsonObj->payment_intent:''; 
    $customer_id = !empty($jsonObj->customer_id)?$jsonObj->customer_id:''; 

    // Retrieve customer info 
    try {   
        $customer = \Stripe\Customer::retrieve($customer_id);  
    }catch(Exception $e) {   
        $api_error = $e->getMessage();   
    } 
} 

