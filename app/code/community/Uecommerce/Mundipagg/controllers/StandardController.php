<?php
/**
 * Uecommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Uecommerce EULA.
 * It is also available through the world-wide-web at this URL:
 * http://www.uecommerce.com.br/
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the extension
 * to newer versions in the future. If you wish to customize the extension
 * for your needs please refer to http://www.uecommerce.com.br/ for more information
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @copyright  Copyright (c) 2012 Uecommerce (http://www.uecommerce.com.br/)
 * @license    http://www.uecommerce.com.br/
 */

/**
 * Mundipagg Payment module
 *
 * @category   Uecommerce
 * @package    Uecommerce_Mundipagg
 * @author     Uecommerce Dev Team
 */
class Uecommerce_Mundipagg_StandardController extends Mage_Core_Controller_Front_Action {

	/**
	 * Order instance
	 */
	protected $_order;

	public function getOrder() {
		if ($this->_order == null) {

		}

		return $this->_order;
	}

	/**
	 * Get block instance
	 *
	 * @return
	 */
	protected function _getRedirectBlock() {
		return $this->getLayout()->createBlock('standard/redirect');
	}

	public function getStandard() {
		return Mage::getSingleton('mundipagg/standard');
	}

	protected function _expireAjax() {
		if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
			$this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
			exit;
		}
	}

	public function getOnepage() {
		return Mage::getSingleton('checkout/type_onepage');
	}

	/**
	 * Partial payment
	 */
	public function partialAction() {
		$paymentMethod = Mage::helper('payment')->getMethodInstance('mundipagg_creditcard');

		$session = Mage::getSingleton('checkout/session');

		$approvalRequestSuccess = $session->getApprovalRequestSuccess();

		if (!$session->getLastSuccessQuoteId() && $approvalRequestSuccess != 'partial') {
			$this->_redirect('checkout/cart');

			return;
		}

		$lastQuoteId = $session->getLastSuccessQuoteId();

		$session->setQuoteId($lastQuoteId);

		$quote = Mage::getModel('sales/quote')->load($lastQuoteId);

		$this->getOnepage()->setQuote($quote);
		$this->getOnepage()->getQuote()->setIsActive(true);
		$this->getOnepage()->getQuote()->save();

		if ($session->getLastRealOrderId()) {
			Mage::getSingleton('checkout/session')->setApprovalRequestSuccess('partial');

			$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());

			if ($order->getId()) {
				//Render
				$this->loadLayout();
				$this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('mundipagg/standard_partial'));
				$this->renderLayout();
			} else {
				$this->_redirect();
			}
		} else {
			$this->_redirect();
		}
	}

	/**
	 * Partial payment Post
	 */
	public function partialPostAction() {
		$session = Mage::getSingleton('checkout/session');

		// Post
		if ($data = $this->getRequest()->getPost('payment', array())) {
			try {
				$lastQuoteId = $session->getLastSuccessQuoteId();

				$session->setQuoteId($lastQuoteId);

				$quote = Mage::getModel('sales/quote')->load($lastQuoteId);

				$this->getOnepage()->setQuote($quote);
				$this->getOnepage()->getQuote()->setIsActive(true);

				// Get Reserved Order Id
				if ($reservedOrderId = $this->getOnepage()->getQuote()->getReservedOrderId()) {
					$session->setApprovalRequestSuccess('partial');

					$order = Mage::getModel('sales/order')->loadByIncrementId($reservedOrderId);

					if ($order->getStatus() == 'pending' OR $order->getStatus() == 'payment_review') {
						if (empty($data)) {
							return array('error' => -1, 'message' => Mage::helper('checkout')->__('Invalid data'));
						}

						if ($this->getOnepage()->getQuote()->isVirtual()) {
							$quote->getBillingAddress()->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
						} else {
							$quote->getShippingAddress()->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
						}

						$payment = $quote->getPayment();
						$payment->importData($data);

						$quote->save();

						switch ($data['method']):
							case 'mundipagg_creditcardoneinstallment':
								$onepage = Mage::getModel('mundipagg/creditcardoneinstallment');
								break;
							case 'mundipagg_creditcard':
								$onepage = Mage::getModel('mundipagg/creditcard');
								break;

							case 'mundipagg_twocreditcards':
								$onepage = Mage::getModel('mundipagg/twocreditcards');
								break;

							case 'mundipagg_threecreditcards':
								$onepage = Mage::getModel('mundipagg/threecreditcards');
								break;

							case 'mundipagg_fourcreditcards':
								$onepage = Mage::getModel('mundipagg/fourcreditcards');
								break;

							case 'mundipagg_fivecreditcards':
								$onepage = Mage::getModel('mundipagg/fivecreditcards');
								break;

							default:
								$this->_redirect('mundipagg/standard/partial');
								break;
						endswitch;

						$resultPayment = $onepage->doPayment($payment, $order);
						$approvalRequestSuccess = Mage::getSingleton('checkout/session')->getApprovalRequestSuccess();

						// Send new order email when not in admin and payment is success
						if ($approvalRequestSuccess == 'success') {
							if (Mage::app()->getStore()->getCode() != 'admin') {
								$order->sendNewOrderEmail();
							}
						}

						$info = $order->getPayment();

						// We record transaction(s)
						$json = json_encode($resultPayment['result']);
						$dataR = array();
						$dataR = json_decode($json, true);

						if (count($resultPayment['result']->CreditCardTransactionResultCollection->CreditCardTransactionResult) == 1) {
							$trans = $dataR['CreditCardTransactionResultCollection']['CreditCardTransactionResult'];

							$onepage->_addTransaction($order->getPayment(), $trans['TransactionKey'], Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, $trans);

						} else {
							$transactions = $dataR['CreditCardTransactionResultCollection']['CreditCardTransactionResult'];

							foreach ($transactions as $key => $trans) {
								$onepage->_addTransaction($order->getPayment(), $trans['TransactionKey'], Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, $trans);
							}

							// We can capture only if anti fraud is disabled and payment action is "AuthorizeAndCapture"
							$creditCardTransactionResultCollection = $resultPayment['result']->CreditCardTransactionResultCollection;

							if (
								count($creditCardTransactionResultCollection->CreditCardTransactionResult) > 1 &&
								$onepage->getAntiFraud() == 0 &&
								$onepage->getPaymentAction() == 'order'
							) {
								$resultCapture = $onepage->captureAndcreateInvoice($info);
							}
						}

						switch ($approvalRequestSuccess) {
							case 'success':
								$this->_redirect('mundipagg/standard/success');
								break;

							case 'partial':
								$this->_redirect('mundipagg/standard/partial');
								break;

							case 'cancel':
								$this->_redirect('mundipagg/standard/cancel');
								break;

							default:
								Mage::throwException("Unexpected approvalRequestSuccess: {$approvalRequestSuccess}");
						}

//						// Redirect
//						if ($approvalRequestSuccess == 'success') {
//							$this->_redirect('mundipagg/standard/success');
//						} else {
//							$this->_redirect('mundipagg/standard/partial');
//						}
					}
				}
			} catch (Exception $e) {
				//Log error
				Mage::logException($e);
			}
		} else {
			$this->_redirect();
		}
	}

	/**
	 * Cancel page
	 */
	public function cancelAction() {
		$this->cancelOrder();

		//Render
		$this->loadLayout();
		$this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('mundipagg/standard_cancel'));
		$this->renderLayout();
	}

	/**
	 * Force Cancel page
	 */
	public function fcancelAction() {
		$this->cancelOrder();

		//Render
		$this->loadLayout();
		$this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('mundipagg/standard_fcancel'));
		$this->renderLayout();
	}

	/*
	* Cancel order and set quote as inactive
	*/
	private function cancelOrder() {
		$session = Mage::getSingleton('checkout/session');

		if (!$session->getLastSuccessQuoteId()) {
			$this->_redirect('checkout/cart');

			return;
		}

		// Set quote as inactive
		Mage::getSingleton('checkout/session')
			->getQuote()
			->setIsActive(false)
			->setTotalsCollectedFlag(false)
			->setAuthorizedAmount()
			->save()
			->collectTotals();

		if ($session->getLastRealOrderId()) {
			$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());

			if ($order->getId() && $order->canCancel()) {
				$order->cancel()->save();
			}
		}

		$session->clear();
	}

	/**
	 * Success page (also used for Mundipagg return page for payments like "debit" and "boleto")
	 */
	public function successAction() {
		$session = Mage::getSingleton('checkout/session');
		$approvalRequestSuccess = $session->getApprovalRequestSuccess();

		$log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$log->info("teste: {$approvalRequestSuccess}");

		if (!$this->getRequest()->isPost() && ($approvalRequestSuccess == 'success' || $approvalRequestSuccess == 'debit')) {
			if (!$session->getLastSuccessQuoteId()) {
				$this->_redirect('checkout/cart');

				return;
			}

			$session->setQuoteId($session->getMundipaggStandardQuoteId(true));

			// Last Order Id
			$lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();

			// Set quote as inactive
			Mage::getSingleton('checkout/session')
				->getQuote()
				->setIsActive(false)
				->setTotalsCollectedFlag(false)
				->save()
				->collectTotals();

			// Load order
			$order = Mage::getModel('sales/order')->load($lastOrderId);

			if ($order->getId()) {
				Mage::register('current_order', Mage::getModel('sales/order')->load($lastOrderId));

				// Render
				$this->loadLayout();
				Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($lastOrderId)));
				$this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('mundipagg/standard_success'));
				$this->renderLayout();

				$session->clear();
			} else {
				// Redirect to homepage
				$this->_redirect('');
			}
		} else {
			// Get posted data
			$postData = $this->getRequest()->getPost();
			$api = Mage::getModel('mundipagg/api');

			// Process order
			$result = $api->processOrder($postData);

			// If result is empty we redirect to homepage
			if ($result === false) {
				$this->_redirect('');
			} else {
				$this->getResponse()->setBody($result);
			}
		}
	}

	public function installmentsandinterestAction() {
		$post = $this->getRequest()->getPost();
		$result = array();
		$installmentsHelper = Mage::helper('mundipagg/installments');

		if (isset($post['cctype'])) {
			$total = $post['total'];
			$cctype = $post['cctype'];
			if (!$total) {
				$total = null;
			}

			$installments = $installmentsHelper->getInstallmentForCreditCardType($cctype, $total);

			$result['installments'] = $installments;
			$result['brand'] = $cctype;

		} else {
			$installments = $installmentsHelper->getInstallmentForCreditCardType();
			$result['installments'] = $installments;
		}

		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}

	/**
	 * Get max number of installments for a value
	 */
	public function installmentsAction() {
		$val = $this->getRequest()->getParam('val');

		if (is_numeric($val)) {
			$standard = Mage::getSingleton('mundipagg/standard');

			$valorMinParcelamento = $standard->getConfigData('parcelamento_min');

			// Não ter valor mínimo para parcelar OU Parcelar a partir de um valor mínimo
			if ($valorMinParcelamento == 0) {
				$qtdParcelasMax = $standard->getConfigData('parcelamento_max');
			}

			// Parcelar a partir de um valor mínimo
			if ($valorMinParcelamento > 0 && $val >= $valorMinParcelamento) {
				$qtdParcelasMax = $standard->getConfigData('parcelamento_max');
			}

			// Por faixa de valores
			if ($valorMinParcelamento == '') {
				$qtdParcelasMax = $standard->getConfigData('parcelamento_max');

				$p = 1;

				for ($p = 1; $p <= $qtdParcelasMax; $p++) {
					if ($p == 1) {
						$de = 0;
						$parcelaDe = 0;
					} else {
						$de = 'parcelamento_de' . $p;
						$parcelaDe = $standard->getConfigData($de);
					}

					$ate = 'parcelamento_ate' . $p;
					$parcelaAte = $standard->getConfigData($ate);

					if ($parcelaDe >= 0 && $parcelaAte >= $parcelaDe) {
						if ($val >= $parcelaDe AND $val <= $parcelaAte) {
							$qtdParcelasMax = $p;
						}
					} else {
						$qtdParcelasMax = $p - 1;
					}
				}
			}

			$result['qtdParcelasMax'] = $qtdParcelasMax;
			$result['currencySymbol'] = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();

			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
		}
	}
}
