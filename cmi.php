<?php
    //@ini_set('display_errors', 'on');

	class Cmi extends PaymentModule
{
	private	$_html = '';
	private $_postErrors = array();
	private $_postWarnings = array();

	public function __construct()
	{
		$this->name = 'cmi';
		$this->version = '1.0.0';
		$this->tab = 'payments_gateways';
		$this->need_instance = 1;
		$this->controllers = array('payment', 'validation');
		$this->bootstrap = true;
		$this->module_key = '3a6587e8877a665f590245c45874f65';
		
		parent::__construct();

		$this->author = 'CMI';
		
		$this->displayName = $this->l('CMI');
		$this->description = $this->l('Paiements par carte de bancaire avec le CMI');

		$this->confirmUninstall = $this->l('Êtes-vous sur de vouloir désinstaller?');

		if (Configuration::get('merchid') == "")
			$this->warning = $this->l('Invalid CMI merchant ID');
		
	}

	/*
	 *
	 * INSTALL / UNINSTALL
	 *
	*/
	public function install()
	{
		Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'bpi_secuaccess` (
			`id_user` varchar(20) NOT NULL, `date_creation` datetime) ENGINE=MyISAM  DEFAULT CHARSET=utf8');
			
		if (!Configuration::get('BPI_TYPE_DISPLAY')) {
			Configuration::updateValue('BPI_TYPE_DISPLAY', 1);
		}
		
		if (!parent::install() OR !$this->registerHook('paymentOptions') OR !$this->registerHook('paymentReturn') OR !$this->registerHook('adminOrder') OR !$this->registerHook('rightColumn')) {
			return false;
		}
		
		
		$orderState = new OrderState();
		$langs = Language::getLanguages();
		foreach ($langs AS $lang)
			$orderState->name[$lang['id_lang']] = 'Waiting for CMI validation';
		$orderState->name[2] = 'Attente de validation CMI';
		$orderState->invoice = true;
		$orderState->send_email = false;
		$orderState->logable = true;
		$orderState->color = '#3333FF';
		$orderState->save();
		Configuration::updateValue('BPI_ID_ORDERSTATE', intval($orderState->id));
		
	
		return true;
	}

	public function uninstall() {
		
		Configuration::deleteByName('merchid');
		Configuration::deleteByName('actionslk');
		Configuration::deleteByName('secretkey');
		Configuration::deleteByName('confirmation_mode');
		
		Db::getInstance()->Execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'bpi_secuaccess');
		return parent::uninstall();
	}
	

	
	/*
	 *
	 * HOOKS
	 *
	*/
	public function hookRightColumn($params) {
		
		global $cookie;		
		$context = $this->context;
		
		
		$context->smarty->assign('path', 'modules/'.$this->name);
		return $this->display(__FILE__, 'views/templates/front/logo.tpl');
	}
	
	public function hookLeftColumn($params) {
		
		return $this->hookRightColumn($params);
	}
	
	public function getContent() {
		
		global $cookie;
		$ht = '';
		if (isset($_POST['submitCmi_config'])) {
			
			if (empty($_POST['merchid']))
				$this->_postErrors[] = $this->l('Le CMI Merchant id est vide !');
			
			if (empty($_POST['actionslk']))
				$this->_postErrors[] = $this->l('l\'URL de la Gateway de paiement est vide !');
			
			if (empty($_POST['secretkey']))
				$this->_postErrors[] = $this->l('La Clé de hachage est vide !');
					
			
			if (!sizeof($this->_postErrors)) {
				
				Configuration::updateValue('merchid', trim($_POST['merchid']));
				Configuration::updateValue('actionslk', trim($_POST['actionslk']));
				Configuration::updateValue('secretkey', trim($_POST['secretkey']));
				Configuration::updateValue('confirmation_mode', $_POST['confirmation_mode']);
			}
			else
				//$html = '<div class="error">'.$this->l('Please fill the required fields').'</div>';
			$ht = $this->l('Please fill the required fields');
			

		}
		
		$smarty = false;
		$context = $this->context;
				
		
		
		$base_url = __PS_BASE_URI__;
		$bp_img = '//'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').$base_url.'modules/cmi/';
		
		
		$merchid = Configuration::get('merchid');
		$actionslk = Configuration::get('actionslk');	
		$secretkey = Configuration::get('secretkey');
		$confirmation_mode = Configuration::get('confirmation_mode');
		
		$context->smarty->assign(array(
			'legend' => $this->l('Configurer votre Module CMI'),
			'merchid' => $merchid,
			'cmi_form' => $_SERVER['REQUEST_URI'],
			'actionslk' => $actionslk,
			'secretkey' => $secretkey,
			'confirmation_mode' => $confirmation_mode,
			'bp_img' => $bp_img
			));
		
		$context->controller->addCSS($this->_path.'views/css/cmi.css', 'all');
		return $this->display(__FILE__, 'views/templates/admin/admin.tpl');
	}

	
	
 public function validateOrderMtc($id_cart, $id_order_state, $amountPaid, $paymentMethod = 'Unknown', $message = NULL, $extraVars = array(), $currency_special = NULL, $dont_touch_amount = false, $secure_key = false)

	{

		if (!$this->active)

			return ;



		$this->context->country->active = true;
		@$this->validateOrder($id_cart, $id_order_state, $amountPaid, $paymentMethod, $message, $extraVars, (int)$currency_special, false, $secure_key);	


	}	
	
	
 
	public function hookPaymentOptions($params)
	{
		
		$options = array();
		
		$amount = $this->context->cart->getOrderTotal(true, Cart::BOTH);
		$totalAmountTx = floatval($amount);
		$OrderTotal = $totalAmountTx;
				
		
		$currency_order = new Currency(intval($this->context->cart->id_currency));
		$currency_mad = new CurrencyCore(CurrencyCore::getIdByIsoCode("MAD"));
		//echo Tools::ps_round(Tools::convertPrice($totalAmountTx, $currency_mad),(int)$currency->decimals * _PS_PRICE_DISPLAY_PRECISION_);

		if(empty($currency_mad) || $currency_mad->active != 1)
			return ;

		// check currency of payment with default MAD currency for the module
		$symbolCurrency = "";
		$totalAmountTxCurrency = "";
		
		$order = new Order();

		
		if ($currency_order->id != $currency_mad->id)
		{
			$this->context->cart->id_currency = (int)$currency_order->id;
			$this->context->cart->update();
			
			//$context->currency = $currency_mad;
			$context = $this->context;
			
			$symbolCurrency = $currency_order->iso_code;
			$totalAmountTxCurrency = $totalAmountTx;
			//$totalAmountTx = floatval($context->cart->getOrderTotal(true, Cart::BOTH, null, null, false));
			$totalAmountTx = floatval(($this->context->cart->getOrderTotal(true, Cart::BOTH)*$currency_mad->conversion_rate) / $currency_order->conversion_rate) ;
		}else
			{
				$OrderTotal = "";
				
			}
			
					$url_retour_ok = $this->context->link->getPageLink('order-confirmation', true, null, 'id_cart=' . (int)$this->context->cart->id . '&id_module=' . $this->id . '&key=' . $this->context->customer->secure_key);
        $url_retour_ko = $this->context->link->getPageLink('order', true, null, 'step=3');
		
		
			if (strpos($url_retour_ok, 'https://') !== false || strpos($url_retour_ko, 'https://') !== false) {
			$set_secure_return = true;
			$set_secure_conf = true;
		} else {
			$set_secure_return = false;
			$set_secure_conf = false;
		}
		 $urlConfirmation = 'http://'.Tools::safeOutput($_SERVER['HTTP_HOST']).__PS_BASE_URI__.'?controller=history';
		
		
			$query = 'SELECT * FROM `' . _DB_PREFIX_ . 'address` WHERE `id_customer`= "' . $params['cookie']->id_customer . '"';
            $guest_user_detail = Db::getInstance()->ExecuteS($query);
			$billing_detail = new Address((int) ($params['cart']->id_address_invoice));
			$city = !empty($guest_user_detail[0]['city']) ? $guest_user_detail[0]['city'] : '';
            $country = !empty($guest_country[0]['name']) ? $guest_country[0]['name'] : '';
            $postcode = !empty($guest_user_detail[0]['postcode']) ? $guest_user_detail[0]['postcode'] : '';
			$billing_city = !empty($billing_detail->city) ? $billing_detail->city : '';
            $billing_country = !empty($billing_detail->country) ? $billing_detail->country : '';
            $billing_postcode = !empty($billing_detail->postcode) ? $billing_detail->postcode : '';
			$address =  $guest_user_detail[0]['address1'] . ' ' . $guest_user_detail[0]['address2'];
			 $phone_mobile = !empty($guest_user_detail[0]['phone_mobile']) ? $guest_user_detail[0]['phone_mobile'] : '';
			
			
			$conf_uri = $this->context->link->getModuleLink($this->name, 'confirm');
			
			
			
			
		$data = array(
			'clientid' => Configuration::get('merchid'),
			'lang' => $this->context->language->iso_code,
			'rnd' => microtime(),
			'storetype' => "3DPAYHOSTING",
			'hashAlgorithm' => "ver3",
			'TranType' => "PreAuth",
			'email' => trim($this->context->customer->email),
			'BillToName' => trim($this->context->customer->lastname),
			'BillToStreet1' => trim($address),
			'BillToCity' => trim($city),
			'BillToStateProv' => "",
			'BillToCountry' => trim($country),
			'BillToPostalCode' =>  trim($postcode),
			'BillToTelVoice' =>  trim($phone_mobile),
			'oid' => $this->context->cart->id,
			'refreshtime' => "5",
			'amount' => number_format($totalAmountTx, 2, '.', ''),
			'currency' => "504",
			'failUrl' => $urlConfirmation,
			'okurl' =>$urlConfirmation,
			'callbackUrl' => $conf_uri,
			'amountCur' => $OrderTotal,
			'symbolCur' => $currency_order->iso_code,
			'set_secure_return' => ($set_secure_return ? 'true' : 'false'),
			'encoding' => "UTF-8"
			);
		 
		 
		
			//echo htmlentities($address->address1);
			
			$postParams = array();
			foreach ($data as $key => $value){
				array_push($postParams	, $key);
			}

			natcasesort($postParams);

			$hashval = "";
			foreach ($postParams as $param){
				$paramValue = $data[$param];
				$escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));

				$lowerParam = strtolower($param);
				if($lowerParam != "hash" && $lowerParam != "encoding" )	{
					$hashval = $hashval . $escapedParamValue . "|";
				}
			}

			$storeKey = Configuration::get('secretkey');
			$escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $storeKey));
			$hashval = $hashval . $escapedStoreKey;

			//echo $hashval;
			$calculatedHashValue = hash('sha512', $hashval);
			$hash = base64_encode (pack('H*',$calculatedHashValue));
			
			$data['HASH'] = $hash;	
	
			$datas = array(
            'action' => Configuration::get('actionslk'),
            'inputs' => [
                'clientid' => array(
                    'name' => 'clientid',
                    'type' => 'hidden',
                    'value' => $data['clientid']
                ),
				 'lang' => array(
                    'name' => 'lang',
                    'type' => 'hidden',
                    'value' => $data['lang'],
                ),
				'rnd' => array(
                    'name' => 'rnd',
                    'type' => 'hidden',
                    'value' => $data['rnd'],
                ),
				'storetype' => array(
                    'name' => 'storetype',
                    'type' => 'hidden',
                    'value' => $data['storetype'],
                ),
				'hashAlgorithm' => array(
                    'name' => 'hashAlgorithm',
                    'type' => 'hidden',
                    'value' => $data['hashAlgorithm'],
                ),
				'TranType' => array(
                    'name' => 'TranType',
                    'type' => 'hidden',
                    'value' => $data['TranType'],
                ),
				'email' => array(
                    'name' => 'email',
                    'type' => 'hidden',
                    'value' => $data['email'],
                ),
				'BillToName' => array(
                    'name' => 'BillToName',
                    'type' => 'hidden',
                    'value' => $data['BillToName'],
                ),
				'BillToStreet1' => array(
                    'name' => 'BillToStreet1',
                    'type' => 'hidden',
                    'value' => $data['BillToStreet1'],
                ),
				'BillToCity' => array(
                    'name' => 'BillToCity',
                    'type' => 'hidden',
                    'value' => $data['BillToCity'],
                ),
				'BillToStateProv' => array(
                    'name' => 'BillToStateProv',
                    'type' => 'hidden',
                    'value' => $data['BillToStateProv'],
                ),
				'BillToCountry' => array(
                    'name' => 'BillToCountry',
                    'type' => 'hidden',
                    'value' => $data['BillToCountry'],
                ),
				'BillToPostalCode' => array(
                    'name' => 'BillToPostalCode',
                    'type' => 'hidden',
                    'value' => $data['BillToPostalCode'],
                ),
				'BillToTelVoice' => array(
                    'name' => 'BillToTelVoice',
                    'type' => 'hidden',
                    'value' => $data['BillToTelVoice'],
                ),
				'oid' => array(
                    'name' => 'oid',
                    'type' => 'hidden',
                    'value' => $data['oid'],
                ),
				'refreshtime' => array(
                    'name' => 'refreshtime',
                    'type' => 'hidden',
                    'value' => $data['refreshtime'],
                ),
				'amount' => array(
                    'name' => 'amount',
                    'type' => 'hidden',
                    'value' => $data['amount'],
                ),
				'currency' => array(
                    'name' => 'currency',
                    'type' => 'hidden',
                    'value' => $data['currency'],
                ),
				'failUrl' => array(
                    'name' => 'failUrl',
                    'type' => 'hidden',
                    'value' => $data['failUrl'],
                ),
				'okurl' => array(
                    'name' => 'okurl',
                    'type' => 'hidden',
                    'value' => $data['okurl'],
                ),
				'callbackUrl' => array(
                    'name' => 'callbackUrl',
                    'type' => 'hidden',
                    'value' => $data['callbackUrl'],
                ),
				'amountCur' => array(
                    'name' => 'amountCur',
                    'type' => 'hidden',
                    'value' => $data['amountCur'],
                ),
				'symbolCur' => array(
                    'name' => 'symbolCur',
                    'type' => 'hidden',
                    'value' => $data['symbolCur'],
                ),
				'encoding' => array(
                    'name' => 'encoding',
                    'type' => 'hidden',
                    'value' => $data['encoding'],
                ),
				'HASH' => array(
                    'name' => 'HASH',
                    'type' => 'hidden',
                    'value' => $data['HASH'],
                ),
				'set_secure_return' => array(
                	'name' => 'set_secure_return',
                	'type' => 'hidden',
                	'value' => ($set_secure_return ? 'true' : 'false')
                )
				]
			);
			
        $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l('CMI, Payer en toute sécurité avec votre carte bancaire.'))
            ->setAction($datas['action'])
            ->setInputs($datas['inputs']);
        $options[] = $option;
        

        
        return $options;
	} 
	
	
	

	
	public function hookPaymentReturn() {
		
		$smarty = false;
		$context = $this->context;	
		
		if (@$params['objOrder']->module != $this->name)
			return;
		
		if (Tools::getValue('error'))
			$context->smarty->assign('status', 'failed');
		else
			$context->smarty->assign('status', 'ok');
		return $this->display(__FILE__, 'payment_return.tpl');
	}
	
	
    /**
	 *
	 * CMI FUNCTIONS
	 *
	 */	
	public function displayWarnings() {
		
		$nbWarnings = sizeof($this->_postWarning);
		$this->_html .= '
		<div class="warn">
			<h3>'.($nbWarnings > 1 ? $this->l('There are') : $this->l('There is')).' '.$nbWarnings.' '.($nbWarnings > 1 ? $this->l('warnings') : $this->l('warning')).'</h3>
			<ul>';
		foreach ($this->_postWarning AS $warning)
			$this->_html .= '<li>'.$warning.'</li>';
		$this->_html .= '
			</ul>
		</div>';
	}
  	





	public function isSecure(&$fp_POU_requestProtocol)
	{
		$isSecure = false;
		$HTTPS = getenv('HTTPS');
		$HTTP_X_FORWARDED_PROTO = getenv('HTTP_X_FORWARDED_PROTO');
		$HTTP_X_FORWARDED_SSL = getenv('HTTP_X_FORWARDED_SSL');
		if (getenv('HTTPS') == 'on') {
			$isSecure = true;
		}
		elseif (!empty($HTTP_X_FORWARDED_PROTO) && $HTTP_X_FORWARDED_PROTO == 'https' || !empty($HTTP_X_FORWARDED_SSL) && $HTTP_X_FORWARDED_SSL == 'on') {
			$isSecure = true;
		}
		$fp_POU_requestProtocol = $isSecure ? 'https' : 'http';
		return $isSecure;
	}
	
	
}