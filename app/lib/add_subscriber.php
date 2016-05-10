<?php

function add_subscriber($blogurl, $apikey, $listids, $email, $additional_fields=array()) {

	$listids = is_array($listids) ? $listids : array($listids);
	$url = rtrim($blogurl,'/').'/wp-admin/admin-ajax.php?action=newsletters_api';

	$data_string = json_encode(array(
		'api_method'  => 'subscriber_add',
		'api_key'     => $apikey,
		'api_data'    => array_merge(array(
			'email'       => $email,
			'list_id'     => $listids
		), $additional_fields)
	));

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Content-Length: '.strlen($data_string)
	));

	$result = @json_decode(curl_exec($ch),true);
	curl_close($ch);

	if(!$result) return 2; // Already exists

	return isset($result['success']) ? (bool) $result['success'] : false;

}
