<?php
if('POST' != $_SERVER['REQUEST_METHOD']) {
	header($_SERVER['SERVER_PROTOCOL'].' 500 Error while checking request', true, 500);
	die('500 Error while checking request');

}


	require_once(dirname(__FILE__).'/../../config/config.inc.php');
	/** Call init.php to initialize context */
	require_once(dirname(__FILE__).'/../../init.php');
	include_once(dirname(__FILE__).'/cmi.php');
	
	$id_cart 	= Tools::getValue('id_client');
	$testEtat	= strtolower(Tools::getValue('etat'));
	$id_trans	= Tools::getValue('id_trans');
	$mode		= Tools::getValue('mode');
	$amount		= str_replace(',','.',Tools::getValue('montant'));
	$devise		= Tools::getValue('devise');
	$divers		= Tools::getValue('divers');
	$langue		= Tools::getValue('langue');
	$feedback	= '';
	
	$orderState = _PS_OS_PAYMENT_;
	
	if (!Tools::getValue('etat'))
		$orderState  = _PS_OS_ERROR_;
	
	if (!Tools::getValue('divers'))
		$orderState  = _PS_OS_ERROR_;
	else
		$hashControl = Tools::getValue('divers');
	
	if (!$id_cart)
		exit();		
	$cart = new Cart($id_cart);
	if (!$cart->id)
		exit();	
		
	$customer = new Customer(intval($cart->id_customer));
	
	global $cookie;	
	//global $cart;	
	if (!Order::getOrderByCartId($cart->id)) {
		
			if(isset($_REQUEST["mode"]) &&
			Tools::getValue('mode')=="test"){
				
				$feedback = 'Order Create IN TEST';	
			} else {
				
				$feedback = 'Order Create';	
			}	
			$feedback.= " Transaction ".Tools::getValue('id_trans');			
	}
	
	if($id_order = Order::getOrderByCartId((int)$cart->id))
		$order = new Order((int)$id_order);
		
	$order_id = $id_order;	
		
	$payment = new cmi();
		
	/*
	 *
	 *	Check caller
	 *
	 */
	if(!$payment->Is_authorizedIp($_SERVER['REMOTE_ADDR'])) {
		
		die('INVALID SOURCE '.$_SERVER['REMOTE_ADDR']);
	}
		
	/*
	 *
	 *	Check customer secure key
	 *
	 */
	if ($customer->secure_key != $hashControl) {
		
		$orderState = _PS_OS_CANCELED_;
		die('hash control invalid (data do not come from Cmi)')."\n";
	}
	
	/*
	 *
	 *	Check merchant Id returned by Cmi
	 *
	 */
	if((($payment->get_bouticId() !== Tools::getValue('id_boutique')) ||
	!$payment->get_bouticId() ||
	!Tools::getValue('id_boutique') ||
	Tools::getValue('id_boutique') == '')) {
		
		//die('INVALID MERCHANT ID '.Tools::getValue('id_boutique').' FOR THIS BOUTIC !');
		$feedback.= 'Attention : INVALID MERCHANT ID '.Tools::getValue('id_boutique').' FOR THIS BOUTIC !';
	}
		
		
	
	
	switch (strtolower(Tools::getValue('mode'))) {
		
		case '':
		case ' ':
		case 'test':
			if ($order_id == false) {
				
				/* order has not been processed yet */
				switch (strtolower(Tools::getValue('etat'))) {
					
					case 'ok':
					case 'test':
					
						/* payment OK */
						$order = $payment->saveOrder($cart, _PS_OS_PAYMENT_, $_POST);
						if (number_format($order->total_paid, 2) != number_format($order->total_paid_real, 2)) {
							
							/* amount paid not equals initial amount. */
							die('Le montant payé est différent du montant intial');
						}
						else
							/* response to server */
							die('Payment validated !');

					case 'ko':
					
						/* payment KO */
						$order = $payment->saveOrder($cart, Configuration::get('PS_OS_ERROR'), $_POST);
						die('Payment KO !');

					case 'attente':
					
						die('Prestashop awaiting for your final response !! ');

					default:
						die('NOT RECOGNIZED STATUS !! '.Tools::getValue('etat'));
				}
			} else {
				
				/* order already registered */
				$order = new Order((int)$order_id);
				$old_state = $order->getCurrentState();

				switch ($old_state) {
					
					case Configuration::get('PS_OS_ERROR'):
					case Configuration::get('CMI_STATUS_REFUSED_DEBUG'):
					case Configuration::get('PS_OS_CANCELED'):
						if (Tools::getValue('etat') == 'ok') {
							
							if (Tools::getValue('num_abo') &&
							Tools::getValue('num_abo') > 0) {
								
								$order = $payment->saveOrder($cart, _PS_OS_PAYMENT_, $_POST);
								$msg = 'payment_ok_on_multi_payments';
							} else {
								
								/* order saved with failed status while payment is successful */
								if (number_format($order->total_paid, 2) != number_format($order->total_paid_real, 2)) {
									
									/* amount paid not equals initial amount. */
									die('Le montant payé est différent du montant intial');
								} else
									die('Error: payment success received from platform while order is in a failed status, cart #'.$cart->id.'.');
								$msg = 'payment_ko_on_order_ok';
							}
						} else {
							
							/* just display a failure confirmation message */
							$msg = 'payment_ko_already_done';
						}
						die($msg);
					case Configuration::get('PS_OS_PAYMENT'):
					case Configuration::get('CMI_STATUS_ACCEPTED_DEBUG'):
					case ($old_state == Configuration::get('PS_OS_OUTOFSTOCK')):

						if (Tools::getValue('etat') == 'ok') {
							
							if (Tools::getValue('num_abo') &&
							Tools::getValue('num_abo') > 0) {
								
								$order = $payment->saveOrder($cart, _PS_OS_PAYMENT_, $_POST);
								$msg = 'payment_ok_on_multi_payments';
							} else
								/* just display a confirmation message */
								$msg = 'payment_ok_already_done';
						} else {
							
							if (Tools::getValue('num_abo') &&
							Tools::getValue('num_abo') > 0) {
								
								$order = $payment->saveOrder($cart, Configuration::get('PS_OS_ERROR'), $_POST);
								$msg = 'payment_ko_on_multi_payments';
							} else {
								
								/* order saved with success status while payment failed */
								$msg = 'payment_ko_on_order_ok';
							}
						}
						die($msg);

					default:
						die('NOT RECOGNIZED STATUS '.Tools::getValue('etat').' !! ');
				}
			}
			break;
		case 'r':
			/* just display a confirmation message */
			die('REFUND TRANSACTION '.Tools::getValue('id_trans'));
	}
//}

?>