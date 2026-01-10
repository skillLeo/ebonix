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

$apiToken = qa_opt('king_sd_api'); // Replace with your actual API token
$apiUrl = "https://kingstudio.io/api/king-prompt";
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
$output = json_encode(array('success' => true, 'message' => $userNames['success']));

			
echo "QA_AJAX_RESPONSE\n1\n";

echo $output."\n";
				


