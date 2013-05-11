<?php
/*
 * Provides an interface to Amazon API for fetching Amazon product data as a SimpleXMLElement in a CodeIgniter application.
 *
 * Based on work by Sameer Borate, see: http://www.codediesel.com/php/accessing-amazon-product-advertising-api-in-php/
 * You will need an Amazon public key, private key and an associates tag to use the API which you can request for free from Amazon.
 *
 * @package awsapi
 * @author Russell Michell russ at theruss dotcom 2011-2013
 *
 * Example Usage:
 *
 *	$api = new Awsapi();
 *	$opts = array(
 *		'public_key' => 'abcdEFGhijKLmNoPQ12345'
 *		'private_key' => 'aX1_+9908iHHjgklvdg7'
 *		'assoc_tag' => 'yyih-06'
 *	);
 *	$api->setOpts($opts);
 *	$asins = array(1,2,3,4,5,6,7,8,9,10);
 *	$items = $api->getItemsByAsin($asins);
 */
class Awsapi {

    // Signed AWS Request options
	protected $public_key = '';
    protected $private_key = '';
    protected $assoc_tag = '';				// Your Amazon associates tag, so you can get paid!
	protected $itrResponseCache;
	private static $api_rate_limit = 10;	// Do not change. Internal API rate-limit per "page" of results (see API docs)

	/*
	 * Simple setter for all the internal options for creating a signed AWS request and connecting to the product API
	 *
	 * @param array $opts
	 */
	public function setOpts($opts) {
		$this->public_key = $opts['public_key'];
		$this->private_key = $opts['private_key'];
		$this->assoc_tag = $opts['assoc_tag'];
	}

	/*
	 * Verifies a response from Amazon
	 *
	 * @param SimpleXMLElement $response
	 */
    private function verifyXmlResponse($response) {
        if ($response === false) {
            throw new Exception("Amazon Product API error: Could not connect to Amazon.");
        }
        else {
			// Fairly arbitrary test
            if (isset($response->Items->Item->ItemAttributes->Title)) {
                return ($response);
            }
            else {
                throw new Exception("Amazon Product API error: Invalid XML response.");
            }
        }
    }

	/*
	 * Queries Amazon for the desired data and returns a SimpleXML object.
	 *
	 * @param array $parameters
	 * @return SimpleXMLElement
	 */
    private function queryAmazon($parameters) {
		$method = "GET";
		$region = "com";
		$host = "ecs.amazonaws.".$region;
		$uri = "/onca/xml";

		$parameters["Service"]          = "AWSECommerceService";
		$parameters["AWSAccessKeyId"]   = $this->public_key;
		$parameters["AssociateTag"]     = $this->assoc_tag;
		$parameters["Timestamp"]        = gmdate("Y-m-d\TH:i:s\Z");
		$parameters["Version"]          = "2011-08-01";

		/*
		 * The params need to be sorted by the key, as Amazon does this at
		 * their end and then generates the hash of the same. If the params
		 * are not in order then the generated hash will be different from
		 * Amazon thus failing the authentication process.
		 */
		ksort($parameters);
		$canonicalized_query = array();

		foreach($parameters as $param=>$value) {
			$param = str_replace("%7E", "~", rawurlencode($param));
			$value = str_replace("%7E", "~", rawurlencode($value));
			$canonicalized_query[] = $param."=".$value;
		}

		$canonicalized_query = implode("&", $canonicalized_query);
		$string_to_sign = $method."\n".$host."\n".$uri."\n".$canonicalized_query;

		// Calculate the signature using HMAC, SHA256 and base64-encoding
		$signature = base64_encode(hash_hmac("sha256",$string_to_sign,$this->private_key,true));
		// Encode the signature for the request
		$signature = str_replace("%7E", "~", rawurlencode($signature));
		// Create request
		$request = "http://".$host.$uri."?".$canonicalized_query."&Signature=".$signature;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $request);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

		$xml_response = curl_exec($ch);

		if($xml_response !== false) {
			// Parse XML and return a SimpleXML object, if you would rather like raw xml then just return the $xml_response.
			$parsed_xml = @simplexml_load_string($xml_response);
			return $parsed_xml ? $parsed_xml : false;
		}
		return false;
    }

    /*
	 * Queries API on the desired search-parameter
	 *
	 * @param $search
	 * @param $category
	 * @param string $searchType ('UPC','TITLE')
	 * @param number $page
	 * @return SimpleXMLObject
	 */
	public function searchProducts($search,$category,$searchType="UPC",$page=1) {
        switch($searchType) {
            case "UPC" :
			$parameters = array(
				"Operation"	=> "ItemLookup",
				"ItemId"        => $search,
				"SearchIndex"   => $category,
				"IdType"        => "UPC",
				"ResponseGroup" => "Medium"
			);
			break;
            case "TITLE" :
			$parameters = array(
				"Operation"     => "ItemSearch",
				"Title"         => $search,
				"SearchIndex"   => $category,
				"ItemPage"		=> $page,
				"ResponseGroup" => "Medium"
			);
			break;
        }

        $xml_response = $this->queryAmazon($parameters);
        return $this->verifyXmlResponse($xml_response);
    }

    /*
	 * Queries API on the desired UPC Code
	 * @see
	 *
	 * @param string $upc_code
	 * @param string $product_type
	 * @return SimpleXMLObject
	 */
	public function getItemByUpc($upc_code, $product_type) {
        $parameters = array(
			"Operation"     => "ItemLookup",
			"ItemId"        => $upc_code,
			"SearchIndex"   => $product_type,
			"IdType"        => "UPC",
			"ResponseGroup" => "Medium"
		);

        $xml_response = $this->queryAmazon($parameters);
        return $this->verifyXmlResponse($xml_response);
    }

	/*
	 * Queries API on a single ASIN
	 * @see http://docs.aws.amazon.com/AWSECommerceService/latest/DG/ItemLookup.html
	 *
	 * @param number $asin
	 * @return SimpleXMLObject
	 */
	public function getItemByAsin($asin) {
        $parameters = array(
			"Operation"     => "ItemLookup",
			"ItemId"        => $asin,
			"ResponseGroup" => "Medium"
		);

        $xml_response = $this->queryAmazon($parameters);
        return $this->verifyXmlResponse($xml_response);
    }

	/*
	 * Queries API for multiple ASIN's (Internal API limit is 10)
	 * @see http://docs.aws.amazon.com/AWSECommerceService/latest/DG/ItemLookup.html
	 *
	 * Returns multiple items according to the array of asin's passed.
	 * Note: This is a self referencing method
	 * Note: API is rate-limited by allowing only 10 items to be fetched at a time.
	 * Note: Single API results comprise an array, whereas multiple result are returned as an object
	 *
	 * @param array $asins An array of ASIN's (Can be any number, then method becomes self-referencing once >10 ASIN's are passed)
	 * @return SimpleXML object
	 */
    public function getItemsByAsin($asins) {
		if(count($asins) > (int)self::$api_rate_limit) {
			// Chunk the request and re-call self:;getItemsByAsin() for as many times as necesary.
			$chunks = array_chunk($asins, self::$api_rate_limit, true);
			$response = array();
			foreach($chunks as $chunk) {
				// Merge SimpleXMLElement objects
				// No infinite loops becuase we're always passing what's expected the "second" time around.
				$response[] = (array)$this->getItemsByAsin($chunk);
			}
			return (object)$response;
		}
		else {
			$csv = implode(',',$asins);
			$parameters = array(
				"Operation"     => "ItemLookup",
				"IdType"        => "ASIN",
				"ItemId"        => $csv,
				"ResponseGroup" => "Medium"
			);
			$xml_response = $this->queryAmazon($parameters);
			$res = $this->verifyXmlResponse($xml_response);
			return $res?$res->Items:$res;
		}
    }

    /*
	 * Queries API for a single item according to a keyword
	 * @see
	 *
	 * @param string $keyword
	 * @param string $product_type
	 * @return SimpleXMLObject
	 */
	public function getItemByKeyword($keyword, $product_type) {
        $parameters = array(
			"Operation"   => "ItemSearch",
			"Keywords"    => $keyword,
			"SearchIndex" => $product_type
		);

        $xml_response = $this->queryAmazon($parameters);
        return $this->verifyXmlResponse($xml_response);
    }
}