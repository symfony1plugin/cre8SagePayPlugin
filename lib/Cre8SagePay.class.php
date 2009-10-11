<?php

class Cre8SagePay
{
  
  private $basket = array();
  
  protected $parameters = array();
  
  private $supportedFieldsInAppConfig = array(
    'VendorTxCode',
    'Amount',
    'Currency',
    'Description',
    'SuccessURL',
    'FailureURL'
  );
  
  public function __construct($VendorTxCode, $parameters = array())
  {
    sfProjectConfiguration::getActive()->loadHelpers("Url");
    $this->parameters = $parameters;
    $this->setUpParameters();
    $this->VendorTxCode = $VendorTxCode;
    
  }
  
  public function __get($key) {
    if(isset($this->parameters[$key])) {
      return $this->parameters[$key];
    } else {
      return '';
    }
  }
  
  public function __set($name, $value) {
    $this->parameters[$name] = $value;
  }
  
  private function getEncryptionKey() {
    return sfConfig::get('app_cre8SagePay_encryption_key');
  }
  
  public function getVendorName() {
    return sfConfig::get('app_cre8SagePay_Vendor');
  }
  
  public function getTxType() {
    return sfConfig::get('app_cre8SagePay_TxType');
  }
  
  public function getVPSProtocol() {
    return sfConfig::get('app_cre8SagePay_VPSProtocol', 2.23);
  }
  
  public function getUrl() {
    return sfConfig::get('app_cre8SagePay_url');
  }
  
  private function formatCurrency($value) {
  	return number_format($value, 2, '.', '');
  }
  
  private function serializeBasket() {
  	$retVal  = count($this->basket);
  	foreach ($this->basket as $item)
  	{
  		$retVal .= ':' .  $item[0] . ':'; 	// DESCRIPTION
  		$retVal .=  $item[1] . ':'; 		// QUANTITY
  		$retVal .=  $item[2] . ':';         // ITEM PRICE
  		$retVal .= $item[3] . ':'; 		    // TAX
  		$retVal .= $item[4] . ':'; 		    // TOTAL ITEM PRICE (INCLUDING VAT)
  		$retVal .= $item[5]; 				// TOTAL ITEM PRICE * QUANTITY			
  	}
  	return $retVal;
  }
  
  static public function simpleXor($InString, $Key) {
    // Initialise key array
    $KeyList = array();
    // Initialise out variable
    $output = "";
    
    // Convert $Key into array of ASCII values
    for($i = 0; $i < strlen($Key); $i++){
      $KeyList[$i] = ord(substr($Key, $i, 1));
    }
  
    // Step through string a character at a time
    for($i = 0; $i < strlen($InString); $i++) {
      // Get ASCII code from string, get ASCII code from key (loop through with MOD), XOR the two, get the character from the result
      // % is MOD (modulus), ^ is XOR
      $output.= chr(ord(substr($InString, $i, 1)) ^ ($KeyList[$i % strlen($Key)]));
    }
  
    // Return the result
    return $output;
  }
  
  /* Base 64 Encoding function **
  ** PHP does it natively but just for consistency and ease of maintenance, let's declare our own function **/
  private function base64Encode($plain) {
    // Initialise output variable
    $output = "";
    
    // Do encoding
    $output = base64_encode($plain);
    
    // Return the result
    return $output;
  }
  
  /* Base 64 decoding function **
  ** PHP does it natively but just for consistency and ease of maintenance, let's declare our own function **/
  static public function base64Decode($scrambled) {
    // Initialise output variable
    $output = "";
    
    // Fix plus to space conversion issue
    $scrambled = str_replace(" ","+",$scrambled);
    
    // Do encoding
    $output = base64_decode($scrambled);
    
    // Return the result
    return $output;
  }
  
  private function getToken($thisString) {
    // List the possible tokens
    $Tokens = array(
      "Status",
      "StatusDetail",
      "VendorTxCode",
      "VPSTxId",
      "TxAuthNo",
      "Amount",
      "AVSCV2", 
      "AddressResult", 
      "PostCodeResult", 
      "CV2Result", 
      "GiftAid", 
      "3DSecureStatus", 
      "CAVV" );
  
    // Initialise arrays
    $output = array();
    $resultArray = array();
    
    // Get the next token in the sequence
    for ($i = count($Tokens)-1; $i >= 0 ; $i--){
      // Find the position in the string
      $start = strpos($thisString, $Tokens[$i]);
  	// If it's present
      if ($start !== false){
        // Record position and token name
        $resultArray[$i]->start = $start;
        $resultArray[$i]->token = $Tokens[$i];
      }
    }
    
    // Sort in order of position
    sort($resultArray);
  	// Go through the result array, getting the token values
    for ($i = 0; $i<count($resultArray); $i++){
      // Get the start point of the value
      $valueStart = $resultArray[$i]->start + strlen($resultArray[$i]->token) + 1;
  	// Get the length of the value
      if ($i==(count($resultArray)-1)) {
        $output[$resultArray[$i]->token] = substr($thisString, $valueStart);
      } else {
        $valueLength = $resultArray[$i+1]->start - $resultArray[$i]->start - strlen($resultArray[$i]->token) - 2;
  	  $output[$resultArray[$i]->token] = substr($thisString, $valueStart, $valueLength);
      }      
  
    }
    // Return the ouput array
    return $output;
  }
  
  // Filters unwanted characters out of an input string.  Useful for tidying up FORM field inputs.
  private function cleanInput($strRawText,$strType) {
  
  	if ($strType=="Number") {
  		$strClean="0123456789.";
  		$bolHighOrder=false;
  	}
  	else if ($strType=="VendorTxCode") {
  		$strClean="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.";
  		$bolHighOrder=false;
  	}
  	else {
    		$strClean=" ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.,'/{}@():?-_&�$=%~<>*+\"";
  		$bolHighOrder=true;
  	}
  	
  	$strCleanedText="";
  	$iCharPos = 0;
  		
  	do
	{
  		// Only include valid characters
    	$chrThisChar=substr($strRawText,$iCharPos,1);
    	
    	if (strspn($chrThisChar,$strClean,0,strlen($strClean))>0) { 
    		$strCleanedText=$strCleanedText . $chrThisChar;
    	}
    	else if ($bolHighOrder==true) {
    		// Fix to allow accented characters and most high order bit chars which are harmless 
    		if (bin2hex($chrThisChar)>=191) {
    			$strCleanedText=$strCleanedText . $chrThisChar;
          }
       }
		
	  $iCharPos=$iCharPos+1;
	} while ($iCharPos<strlen($strRawText));
  		
    $cleanInput = ltrim($strCleanedText);
  	return $cleanInput;
  }
  
  private function setUpParameters()
  {
    foreach($this->supportedFieldsInAppConfig as $key) {
      if(! $this->$key && sfConfig::get('app_cre8SagePay_' . $key)) {
        if( ($key == 'SuccessURL') || ($key == 'FailureURL') ) {
          $this->$key = url_for(sfConfig::get('app_cre8SagePay_' . $key), array('absolute' => true));
        } else {
          $this->$key = sfConfig::get('app_cre8SagePay_' . $key);
        }
      }
    }
  }
  
  public function addProduct($name, $itemPrice, $quantity = 1, $tax = 15) {
  	$itemPrice = $this->formatCurrency($itemPrice);
  	$itemTax = $this->formatCurrency($itemPrice * ($tax / 100));
  	$itemPriceWithTax = $this->formatCurrency($itemPrice + $itemTax);
  	$this->basket[] = array($name, $quantity, $itemPrice, $itemTax, $itemPriceWithTax, $itemPriceWithTax * $quantity);
  }
  
  
  public function getLink() {
    return sfConfig::get('app_cre8SagePay_url', 'https://live.sagepay.com/gateway/service/vspform-register.vsp');
  }
  
  public function getCrypt() {
    $retVal = '';
    foreach($this->parameters as $key => $val) {
      $retVal .= '&' . $key . '=' . $val;
    }
	
    if($this->basket) {
      $retVal .= '&Basket=' . $this->serializeBasket();
    }
	
	$encoded = self::simpleXor($retVal, sfConfig::get('app_cre8SagePay_encryption_key'));
	return  $this->base64Encode($encoded);
  }
  
  static public function getResult($crypt)
  {
    $strDecoded= self::simpleXor(self::Base64Decode($crypt), sfConfig::get('app_cre8SagePay_encryption_key'));
	$arrayResults = array();
  	parse_str($strDecoded, $arrayResults);
  	return $arrayResults;
  }
}