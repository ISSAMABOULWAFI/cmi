<?php

	class CmiConfirmModuleFrontController extends ModuleFrontController
{
    
    public function postProcess()
    {
		
		
$cart = new Cart((int)$_POST["oid"]);
$customer = new Customer((int)$cart->id_customer);

$currency_mad = new CurrencyCore(CurrencyCore::getIdByIsoCode("MAD"));
//$cart->id_currency = (int)$currency_mad->id;
$cart->id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
$cart->update();
$idStore = Configuration::get("merchid");

$SLKSecretkey = Configuration::get("secretkey");
$modeconfirmation = Configuration::get("confirmation_mode");



$payment = $this->module;





$postParams = array();
			foreach ($_POST as $key => $value){
				array_push($postParams, $key);
				//echo "<tr><td>" . $key ."</td><td>" . $value . "</td></tr>";
			}

			natcasesort($postParams);

			$hashval = "";
			foreach ($postParams as $param){
				//$paramValue = trim(html_entity_decode($_POST[$param], ENT_QUOTES, 'UTF-8'));
				$paramValue = html_entity_decode(preg_replace("/\n$/","",$_POST[$param]), ENT_QUOTES, 'UTF-8'); 
				$escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));

				$lowerParam = strtolower($param);
				if($lowerParam != "hash" && $lowerParam != "encoding" )	{
					$hashval = $hashval . $escapedParamValue . "|";
				}
			}

			$storeKey = $SLKSecretkey;
			$escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $storeKey));
			$hashval = $hashval . $escapedStoreKey;

			$calculatedHashValue = hash('sha512', $hashval);
			$actualHash = base64_encode (pack('H*',$calculatedHashValue));

			$retrievedHash = $_POST["HASH"];
			if($retrievedHash == $actualHash)	{
				
				



    $currency_special = NULL;



    $dont_touch_amount = false;

    $paymentMethod = 'cmi';

    $message = "Paiement avec CMI";

    $extraVars = array();

	
	if($_POST['amountCur'] == "") $_POST['amountCur'] = $_POST['amount'];

if($_POST["ProcReturnCode"] == "00")
{

	
   @$payment->validateOrderMtc((int)$cart->id, _PS_OS_PAYMENT_, floatval($_POST['amountCur']), $paymentMethod, $message, $extraVars, $currency_special, $dont_touch_amount, $customer->secure_key);
   
   if($modeconfirmation == "1")
	echo "ACTION=POSTAUTH";
else
	echo "APPROVED";


}
else
{
   @$payment->validateOrderMtc($cart->id, _PS_OS_ERROR_, floatval($_POST['amountCur']), $paymentMethod, $message, $extraVars, $currency_special, $dont_touch_amount, $customer->secure_key);
}


} else {

    $currency_special = null;

	$order = new Order();

    $dont_touch_amount = false;

    $paymentMethod = 'cmi';

    $message = null;

    $extraVars = array();
	
   @$payment->validateOrderMtc($cart->id, _PS_OS_ERROR_, floatval($_POST['amountCur']), $paymentMethod, $message, $extraVars, $currency_special, $dont_touch_amount, $customer->secure_key);

    echo "APPROVED";

}
exit;





}
}
