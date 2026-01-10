<?php

require_once 'king-base.php';
require_once QA_INCLUDE_DIR . 'king-db/selects.php';
require_once QA_INCLUDE_DIR . 'king-app/options.php';
require_once QA_INCLUDE_DIR . 'king-app/users.php';

$enableSandbox = qa_opt('paypal_sandbox');
$pageurl = qa_opt('site_url');

$paypal_url = $enableSandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';

function verifyTransaction($data) {
    global $paypal_url;

    $req = 'cmd=_notify-validate';
    foreach ($data as $key => $value) {
        $value = urlencode(stripslashes($value));
        $value = preg_replace('/(.*[^%^0^D])(%0A)(.*)/i', '${1}%0D%0A${3}', $value); // IPN fix
        $req .= "&$key=$value";
    }

    $ch = curl_init($paypal_url);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
    $res = curl_exec($ch);

    if (!$res) {
        $errno = curl_errno($ch);
        $errstr = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error: [$errno] $errstr");
    }

    $info = curl_getinfo($ch);
    $httpCode = $info['http_code'];
    if ($httpCode != 200) {
        throw new Exception("PayPal responded with http code $httpCode");
    }

    curl_close($ch);

    return $res === 'VERIFIED';
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (verifyTransaction($_POST)) {
            $data = [
                'item_name' => $_POST['item_name'],
                'item_number' => $_POST['item_number'],
                'payment_status' => $_POST['payment_status'],
                'payment_amount' => $_POST['mc_gross'],
                'payment_currency' => $_POST['mc_currency'],
                'txn_id' => $_POST['txn_id'],
                'receiver_email' => $_POST['receiver_email'],
                'payer_email' => $_POST['payer_email'],
                'custom' => $_POST['custom'],
            ];

            if ($data['payment_status'] == 'Completed') {
                if (qa_opt('enable_membership')) {
                    king_insert_membership($data['item_number'], $data['payment_amount'], $data['custom'], $data['txn_id']);
                } else {
                    require_once QA_INCLUDE_DIR . 'king-db/metas.php';
                    $ocredit = qa_db_usermeta_get($data['custom'], 'credit');
                    $csize = !empty(qa_opt('credits_size')) ? qa_opt('credits_size') : 1;
                    $credit = $data['payment_amount'] * $csize;
                    $ocredit2 = $ocredit + $credit;
                    qa_db_usermeta_set($data['custom'], 'credit', $ocredit2);
                }
            }
        }
    }
} catch (Exception $e) {
    // Handle exceptions here if needed
}
?>
