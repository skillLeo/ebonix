<?php
/*
Description: Upload ai image.

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


require_once QA_INCLUDE_DIR . 'king-db/selects.php';

$output = '';
$prompt = qa_post_text('prompt');


if (qa_opt('select_kingask') == 'kingstu') {
    $apiToken = qa_opt('king_sd_api'); // Replace with your actual API token 
    $apiUrl = "https://kingstudio.io/api/king-askai";
    $initialData = array(
            "prmpt" => $prompt,
    );
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiToken",
        "Accept: application/json",
        "Content-Type: application/json",
        
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($initialData));
    $response = curl_exec($ch);
    $userNames = json_decode($response, true);

    if (isset($userNames['success'])) {
        // Replace "google" and "google ai" with "king studio"
        $content = $userNames['success'];
        $content = str_replace(["Google", "Google's AI", "Google AI"], "King Studio", $content);
    
        $output = json_encode(array('success' => true, 'message' => $content));
    } else {
        $output = json_encode(array('success' => false, 'message' => 'Content not found'));
    }

} else {
    $openaiapi = qa_opt('king_leo_api');
    $url = 'https://api.openai.com/v1/chat/completions';

    $params = array(
        'model' => 'gpt-3.5-turbo',
        'messages' => array(
            array(
                'role' => 'user',
                'content' => $prompt,
            ),
        ),
        'stop' => '',
    );
    
    $params_json = json_encode($params);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $openaiapi",
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params_json);
    $response = curl_exec($ch);
    $response2 = json_decode($response, true);
    $output = json_encode(array('success' => true, 'message' => $response2['choices'][0]['message']['content']));
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        // Handle error accordingly
    }
    
    curl_close($ch);
}


			
echo "QA_AJAX_RESPONSE\n1\n";

echo $output."\n";
				


