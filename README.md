# CI_AWSProductAPI

## Introduction

Provides an interface to Amazon API for fetching Amazon product data as a SimpleXMLElement in a CodeIgniter application.

Based on work by Sameer Borate, see: http://www.codediesel.com/php/accessing-amazon-product-advertising-api-in-php/
You will need a public key, private key and an associates tag to use the API which you can request for free from Amazon.

## Setup

* Save the __CI_AWSProductAPI__ folder into your CodeIgniter application's "libraries" folder.
* Load the class as usual in your own controllers:

	public function __construct() {
		$this->load->('CI_AWSProductAPI/CI_AWSProductAPI');
	}

* You will need a public key, private key and an associates tag to use the API which you can request for free from Amazon.

## Usage:

	$api = new CI_AWSProductAPI();
	$opts = array(
		'public_key' => 'abcdEFGhijKLmNoPQ12345'
		'private_key' => 'aX1_+9908iHHjgklvdg7'
		'assoc_tag' => 'yyih-06'
	);
	$api->setOpts($opts);
	$asins = array(1,2,3,4,5,6,7,8,9,10);
	$items = $api->getItemsByAsin($asins);