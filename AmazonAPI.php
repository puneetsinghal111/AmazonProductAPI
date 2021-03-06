<?php
/**
 *  Amazon Product API Library
 *
 *  @author Marc Littlemore
 *  @link 	http://www.marclittlemore.com
 *
 */ 

class AmazonAPI
{
	private $m_amazonUrl = '';
	private $m_locale = 'uk';
	private $m_retrieveArray = false;
	private $m_useSSL = false;

	// AWS endpoint for each locale
	private $m_localeTable = array(
		'ca'	=>	'webservices.amazon.ca/onca/xml',
		'cn'	=>	'webservices.amazon.cn/onca/xml',
		'de'	=>	'webservices.amazon.de/onca/xml',
		'es'	=>	'webservices.amazon.es/onca/xml',
		'fr'	=>	'webservices.amazon.fr/onca/xml',
		'it'	=>	'webservices.amazon.it/onca/xml',
		'jp'	=>	'webservices.amazon.jp/onca/xml',
		'uk'	=>	'webservices.amazon.co.uk/onca/xml',
		'us'	=>	'webservices.amazon.com/onca/xml',
	);

	// API key ID
	private $m_keyId		= NULL;

	// API Secret Key
	private $m_secretKey	= NULL;

	// AWS associate tag
	private $m_associateTag = NULL;
	
	// Valid names that can be used for search
	private $mValidSearchNames = array(
		'All','Apparel','Appliances','Automotive','Baby','Beauty','Blended','Books','Classical','DVD','Electronics','Grocery','HealthPersonalCare','HomeGarden','HomeImprovement','Jewelry','KindleStore','Kitchen','Lighting','Marketplace','MP3Downloads','Music','MusicTracks','MusicalInstruments','OfficeProducts','OutdoorLiving','Outlet','PetSupplies','PCHardware','Shoes','Software','SoftwareVideoGames','SportingGoods','Tools','Toys','VHS','Video','VideoGames','Watches',
	);

	private $mErrors = array();

	public function __construct( $keyId, $secretKey, $associateTag )
	{
		// Setup the AWS credentials
		$this->m_keyId			= $keyId;
		$this->m_secretKey		= $secretKey;
		$this->m_associateTag	= $associateTag;

		// Set UK as locale by default
		$this->SetLocale( 'uk' );
	}

	/**
	 * Enable or disable SSL endpoints
	 *
	 * @param	useSSL 		True if using SSL, false otherwise
	 * 
	 * @return	None
	 */
	public function SetSSL( $useSSL = true )
	{
		$this->m_useSSL = $useSSL;
	}

	/**
	 * Enable or disable retrieving items array rather than XML
	 *
	 * @param	retrieveArray	True if retrieving as array, false otherwise.
	 * 
	 * @return	None
	 */
	public function SetRetrieveAsArray( $retrieveArray = true )
	{
		$this->m_retrieveArray	= $retrieveArray;
	}

	/**
	 * Sets the locale for the endpoints
	 *
	 * @param	locale		Set to a valid AWS locale - see link below.
	 * @link 	http://docs.amazonwebservices.com/AWSECommerceService/latest/DG/Locales.html
	 * 
	 * @return	None
	 */
	public function SetLocale( $locale )
	{
		// Check we have a locale in our table
		if ( !array_key_exists( $locale, $this->m_localeTable ) )
		{
			// If not then just assume it's US
			$locale = 'us';
		}
		
		// Set the URL for this locale
		$this->m_locale = $locale;

		// Check for SSL
		if ( $this->m_useSSL )
			$this->m_amazonUrl = 'https://' . $this->m_localeTable[$locale];
		else
			$this->m_amazonUrl = 'http://' . $this->m_localeTable[$locale];
	}
	
	/**
	 * Return valid search names
	 *
	 * @param	None
	 * 
	 * @return	Array 	Array of valid string names
	 */
	public function GetValidSearchNames()
	{
		return( $this->mValidSearchNames );
	}

	/**
	 * Return data from AWS
	 *
	 * @param	url			URL request
	 * 
	 * @return	mixed		SimpleXML object or false if failure.
	 */
	private function MakeRequest( $url )
	{
		// Check if curl is installed
		if ( !function_exists( 'curl_init' ) )
		{
			$this->AddError( "Curl not available" );
			return( false );
		}

		// Use curl to retrieve data from Amazon
		$session = curl_init( $url );
		curl_setopt( $session, CURLOPT_HEADER, false );
		curl_setopt( $session, CURLOPT_RETURNTRANSFER, true );
		$response = curl_exec( $session );

		$error = NULL;
		if ( $response === false )
			$error = curl_error( $session );

		curl_close( $session );

		// Have we had an error?
		if ( !empty( $error ) )
		{
			$this->AddError( "Error downloading data : $url : " . $error );
			return( false );
		}

		// Interpret data as XML
		$parsedXml = simplexml_load_string( $response );
		
		return( $parsedXml );
	}
	
	/**
	 * Search for items
	 *
	 * @param	keywords			Keywords which we're requesting
	 * @param	searchIndex			Name of search index (category) requested. NULL if searching all.
	 * @param	sortBySalesRank		True if sorting by sales rank, false otherwise.
	 * @param	condition			Condition of item. Valid conditions : Used, Collectible, Refurbished, All 
	 * 
	 * @return	mixed				SimpleXML object, array of data or false if failure.
	 */
	public function ItemSearch( $keywords, $searchIndex = NULL, $sortBySalesRank = true, $condition = 'New' )
	{
		// Set the values for some of the parameters.
		$operation = "ItemSearch";
		$responseGroup = "ItemAttributes,Offers,Images";
		
		//Define the request
		$request= $this->GetBaseUrl()
		   . "&Operation=" . $operation
		   . "&Keywords=" . $keywords
		   . "&ResponseGroup=" . $responseGroup
		   . "&Condition=" . $condition;

		// Assume we're searching in all if an index isn't passed
		if ( empty( $searchIndex ) )
		{
			// Search for all
			$request .= "&SearchIndex=All";
		}
		else
		{
			// Searching for specific index
			$request .= "&SearchIndex=" . $searchIndex;

			// If we're sorting by sales rank
			if ( $sortBySalesRank && ( $searchIndex != 'All' ) )
				$request .= "&Sort=salesrank";
		}

		// Need to sign the request now
		$signedUrl = $this->GetSignedRequest( $this->m_secretKey, $request );
		
		// Get the response from the signed URL
		$parsedXml = $this->MakeRequest( $signedUrl );
		if ( $parsedXml === false )
			return( false );
		
		if ( $this->m_retrieveArray )
		{
			$items = $this->RetrieveItems( $parsedXml );
		}
		else
		{
			$items = $parsedXml;
		}

		return( $items );
	}
	
	/**
	 * Lookup items from ASINs
	 *
	 * @param	asinList			Either a single ASIN or an array of ASINs
	 * @param	onlyFromAmazon		True if only requesting items from Amazon and not 3rd party vendors
	 * 
	 * @return	mixed				SimpleXML object, array of data or false if failure.
	 */
	public function ItemLookup( $asinList, $onlyFromAmazon = false )
	{
		// Check if it's an array
		if ( is_array( $asinList ) )
		{
			$asinList = implode( ',', $asinList );
		}
		
		// Set the values for some of the parameters.
		$operation = "ItemLookup";
		$responseGroup = "ItemAttributes,Offers,Reviews,Images,EditorialReview";
		
		// Determine whether we just want Amazon results only or not
		$merchantId = ( $onlyFromAmazon == true ) ? 'Amazon' : 'All';
		
		$reviewSort = '-OverallRating';
		//Define the request
		$request = $this->GetBaseUrl()
		   . "&ItemId=" . $asinList
		   . "&Operation=" . $operation
		   . "&ResponseGroup=" . $responseGroup
		   . "&ReviewSort=" . $reviewSort
		   . "&MerchantId=" . $merchantId;
		   
		// Need to sign the request now
		$signedUrl = $this->GetSignedRequest( $this->m_secretKey, $request );
		
		// Get the response from the signed URL
		$parsedXml = $this->MakeRequest( $signedUrl );
		if ( $parsedXml === false )
			return( false );
		
		if ( $this->m_retrieveArray )
		{
			$items = $this->RetrieveItems( $parsedXml );
		}
		else
		{
			$items = $parsedXml;
		}
		return( $items );
	}

	/**
	 * Basic method to retrieve only requested item data as an array
	 *
	 * @param	responseXML		XML data to be passed
	 * 
	 * @return	Array			Array of item data. Empty array if not found
	 */
	private function RetrieveItems( $responseXml )
	{
		$items = array();
		if ( empty( $responseXml ) )
		{
			$this->AddError( "No XML response found from AWS." );
			return( $items );
		}

		if ( empty( $responseXml->Items ) )
		{
			$this->AddError( "No items found." );
			return( $items );
		}

		if ( $responseXml->Items->Request->IsValid != 'True' )
		{
			$errorCode = $responseXml->Items->Request->Errors->Error->Code;
			$errorMessage = $responseXml->Items->Request->Errors->Error->Message;
			$error = "API ERROR ($errorCode) : $errorMessage";
			$this->AddError( $error );
			return( $items );
		}

		// Get each item
		foreach( $responseXml->Items->Item as $responseItem )
		{
			$item = array();
			$item['asin'] = (string) $responseItem->ASIN;
			$item['url'] = (string) $responseItem->DetailPageURL;
			$item['rrp'] = ( (float) $responseItem->ItemAttributes->ListPrice->Amount ) / 100.0;
			$item['title'] = (string) $responseItem->ItemAttributes->Title;
			
			if ( $responseItem->OfferSummary )
			{
				$item['lowestPrice'] = ( (float) $responseItem->OfferSummary->LowestNewPrice->Amount ) / 100.0;
			}
			else
			{
				$item['lowestPrice'] = 0.0;
			}

			// Images
			$item['largeImage'] = (string) $responseItem->LargeImage->URL;
			$item['mediumImage'] = (string) $responseItem->MediumImage->URL;
			$item['smallImage'] = (string) $responseItem->SmallImage->URL;

			array_push( $items, $item );
		}

		return( $items );		
	}

	/**
	 * Determines the base address of the request
	 *
	 * @param	None
	 * 
	 * @return	string		Base URL of AWS request
	 */
	private function GetBaseUrl()
	{
		//Define the request
		$request=
		     $this->m_amazonUrl
		   . "?Service=AWSECommerceService"
		   . "&AssociateTag=" . $this->m_associateTag
		   . "&AWSAccessKeyId=" . $this->m_keyId;
		   
		return( $request );
	}
	
	/**
	  * This function will take an existing Amazon request and change it so that it will be usable 
	  * with the new authentication.
	  *
	  * @param string $secret_key - your Amazon AWS secret key
	  * @param string $request - your existing request URI
	  * @param string $access_key - your Amazon AWS access key
	  * @param string $version - (optional) the version of the service you are using
	  *
	  * @link http://www.ilovebonnie.net/2009/07/27/amazon-aws-api-rest-authentication-for-php-5/
	  */
	private function GetSignedRequest( $secret_key, $request, $access_key = false, $version = '2011-08-01')
	{
	    // Get a nice array of elements to work with
	    $uri_elements = parse_url($request);
	 
	    // Grab our request elements
	    $request = $uri_elements['query'];
	 
	    // Throw them into an array
	    parse_str($request, $parameters);
	 
	    // Add the new required paramters
	    $parameters['Timestamp'] = gmdate( "Y-m-d\TH:i:s\Z" );
	    $parameters['Version'] = $version;
	    if ( strlen($access_key) > 0 )
	    {
	        $parameters['AWSAccessKeyId'] = $access_key;
	    }   
	 
	    // The new authentication requirements need the keys to be sorted
	    ksort( $parameters );
	 
	    // Create our new request
	    foreach ( $parameters as $parameter => $value )
	    {
	        // We need to be sure we properly encode the value of our parameter
	        $parameter = str_replace( "%7E", "~", rawurlencode( $parameter ) );
	        $value = str_replace( "%7E", "~", rawurlencode( $value ) );
	        $request_array[] = $parameter . '=' . $value;
	    }   
	 
	    // Put our & symbol at the beginning of each of our request variables and put it in a string
	    $new_request = implode( '&', $request_array );
	 
	    // Create our signature string
	    $signature_string = "GET\n{$uri_elements['host']}\n{$uri_elements['path']}\n{$new_request}";
	 
	    // Create our signature using hash_hmac
	    $signature = urlencode( base64_encode( hash_hmac( 'sha256', $signature_string, $secret_key, true ) ) );
	 
	    // Return our new request
	    return "http://{$uri_elements['host']}{$uri_elements['path']}?{$new_request}&Signature={$signature}";
	}

	/**
	 * Adds error to an error array
	 *
	 * @param	error	Error string
	 * 
	 * @return	None
	 */
	private function AddError( $error )
	{
		array_push( $this->mErrors, $error );
	}

	/**
	 * Returns array of errors
	 *
	 * @param	None
	 * 
	 * @return	Array		Array of errors. Empty array if none found
	 */
	public function GetErrors()
	{
		return( $this->mErrors );
	}
}
?>