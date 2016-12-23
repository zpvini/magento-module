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
class Uecommerce_Mundipagg_Model_Observer extends Uecommerce_Mundipagg_Model_Standard {
	/*
	 * Update status and notify customer or not
	 */
	private function _updateStatus($order, $state, $status, $comment, $notified) {

		try {
			$order->setState($state, $status, $comment, $notified);
			$order->save();

			return $this;

		} catch (Exception $e) {
			//Api
			$api = Mage::getModel('mundipagg/api');

			//Log error
			Mage::logException($e);

			//Mail error
			$api->mailError(print_r($e->getMessage(), 1));
		}
	}

	public function canceledOrder($event) {
		$order = $event->getOrder();
		$state = $order->getState();

		if ($state == Mage_Sales_Model_Order::STATE_CANCELED) {

			// if a order is canceled successfuly, offline retry data must be deleted if exists
			if (Uecommerce_Mundipagg_Model_Offlineretry::offlineRetryIsEnabled()) {
				$model = Mage::getModel('mundipagg/offlineretry');
				$incrementId = $order->getIncrementId();
				$offlineRetry = $model->loadByIncrementId($incrementId);

				if (is_null($offlineRetry->getId()) === false) {
					$helperLog = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
					$helperLog->setLogLabel("Order #{$incrementId} canceled");

					try {
						$offlineRetry->delete();
						$helperLog->info("Offline retry data deleted successfully.");

					} catch (Exception $e) {
						$helperLog->info("Offline retry data cannot be deleted: {$e}");
					}
				}
			}

			//cancel Mundi transactions via API
			$this->cancelOrderViaApi($order);
		}
	}

	private function cancelOrderViaApi(Mage_Sales_Model_Order $order) {
		$payment = $order->getPayment();
		$paymentMethod = $payment->getAdditionalInformation('PaymentMethod');
		$allowedPaymentMethods = array(
			'mundipagg_creditcardoneinstallment',
			'mundipagg_creditcard',
			'mundipagg_twocreditcards',
			'mundipagg_threecreditcards',
			'mundipagg_fourcreditcards',
			'mundipagg_fivecreditcards'
		);

		if (!in_array($paymentMethod, $allowedPaymentMethods)) {
			return;
		}

		$logHelper = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
		$api = new Uecommerce_Mundipagg_Model_Api();
		$url = "{$this->getUrl()}/Cancel";

		$incrementId = $order->getIncrementId();
		$orderKeys = (array)$payment->getAdditionalInformation('OrderKey');

		foreach ($orderKeys as $orderKey) {
			$data = array('OrderKey' => $orderKey);

			$logHelper->info("Order #{$incrementId} | Order canceled. Cancel via MundiPagg Api...");
			$api->sendRequest($data, $url);
		}

	}

	/**
	 * Update status
	 * */
	public function updateStatus($event) {
		$method = $event->getOrder()->getPayment()->getAdditionalInformation('PaymentMethod');
		$approvalRequestSuccess = Mage::getSingleton('checkout/session')->getApprovalRequestSuccess();

		if ($method == 'mundipagg_boleto' && $approvalRequestSuccess != 'cancel') {
			$comment = Mage::helper('mundipagg')->__('Waiting for Boleto Bancário payment');

			$this->_updateStatus($event->getOrder(), Mage_Sales_Model_Order::STATE_HOLDED, true, $comment, false);
		}
	}

	/**
	 * If were are not in a Mundipagg controller methods listed above we unset parcial
	 */
	public function sessionUpdate($observer) {
		$action = $observer['controller_action']->getFullActionName();

		if (
			$action != 'mundipagg_standard_redirect'
			&& $action != 'mundipagg_standard_installments'
			&& $action != 'mundipagg_standard_installmentsandinterest'
			&& $action != 'mundipagg_standard_partial'
			&& $action != 'mundipagg_standard_partialPost'
			&& $action != 'mundipagg_standard_success'
		) {
			Mage::getSingleton('checkout/session')->unsetData('approval_request_success');
			Mage::getSingleton('checkout/session')->unsetData('authorized_amount');
		}
	}

	/**
	 * Remove all non MundiPagg payment methods and MundiPagg Boleto from partial payment page
	 */
	public function removePaymentMethods($observer) {
		$event = $observer->getEvent();
		$method = $event->getMethodInstance();
		$result = $event->getResult();
		$isPartial = Mage::getSingleton('checkout/session')->getApprovalRequestSuccess();

		if ($isPartial == 'partial') {
			switch ($method->getCode()) {
				case 'mundipagg_creditcardoneinstallment':
				case 'mundipagg_creditcard':
				case 'mundipagg_twocreditcards':
				case 'mundipagg_threecreditcards':
				case 'mundipagg_fourcreditcards':
				case 'mundipagg_fivecreditcards':
					$active = Mage::getStoreConfig('payment/' . $method->getCode() . '/active');

					if ($active == '1') {
						$result->isAvailable = true;
					} else {
						$result->isAvailable = false;
					}
					break;
				case 'mundipagg_boleto':
					$result->isAvailable = false;
					break;
				default:
					$result->isAvailable = false;
					break;
			}
		}
	}

	public function removeInterest($observer) {
		$session = Mage::getSingleton('admin/session');

		if ($session->isLoggedIn()) {
			$quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
		} else {
			$quote = Mage::getSingleton('checkout/session')->getQuote();
		}

		$quote->setMundipaggInterest(0.0);
		$quote->setMundipaggBaseInterest(0.0);
		$quote->setTotalsCollectedFlag(false)->collectTotals();
		$quote->save();
	}

	/**
	 * Check if recurrency product is in cart in order to show only Mundipagg Credit Card payment
	 */
	public function checkForRecurrency($observer) {
		$session = Mage::getSingleton('checkout/session');
		$recurrent = $session->getMundipaggRecurrency();

		if ($recurrent) {
			$instance = $observer->getMethodInstance();
			$result = $observer->getResult();

			switch ($instance->getCode()) {
				case 'mundipagg_boleto':
				case 'mundipagg_debit':
				case 'mundipagg_creditcardoneinstallment':
				case 'mundipagg_twocreditcards':
				case 'mundipagg_threecreditcards':
				case 'mundipagg_fourcreditcards':
				case 'mundipagg_fivecreditcards':
					$result->isAvailable = false;
					break;
				case 'mundipagg_creditcard':
					$result->isAvailable = true;
					break;
				default:
					$result->isAvailable = false;
					break;
			}
		}
	}

	/**
	 * Add discount amount in the quote when partial payment
	 *
	 * @param type $observer
	 */
	public function addDiscountWhenPartial($observer) {
		$session = Mage::getSingleton('checkout/session');
		if (!$session->getApprovalRequestSuccess() == 'partial') {
			$request = Mage::app()->getRequest();
			if (Mage::app()->getRequest()->getActionName() != 'partialPost' && $request->getModuleName() != 'mundipagg' && $request->getControllerName() != 'standard') {
				return $this;
			}
		}
		$quote = $observer->getEvent()->getQuote();
		$quoteid = $quote->getId();

		$reservedOrderId = $quote->getReservedOrderId();

		if (!$reservedOrderId) {
			return $this;
		}

		$order = Mage::getModel('sales/order')->loadByIncrementId($reservedOrderId);

		if (!$order->getId()) {
			return $this;
		}

		$payment = $order->getPayment();

		$interestInformation = $payment->getAdditionalInformation('mundipagg_interest_information');
		$discountAmount = 0;

		if (isset($interestInformation)) {
			foreach ($interestInformation as $ii) {
				$discountAmount += (float)$ii->getValue();
			}
		}

		if ($quoteid) {
			$total = $quote->getBaseSubtotal();
			$quote->setSubtotal(0);
			$quote->setBaseSubtotal(0);

			$quote->setSubtotalWithDiscount(0);
			$quote->setBaseSubtotalWithDiscount(0);

			$quote->setGrandTotal(0);
			$quote->setBaseGrandTotal(0);


			$canAddItems = $quote->isVirtual() ? ('billing') : ('shipping');
			foreach ($quote->getAllAddresses() as $address) {

				$discountAmount -= $address->getShippingAmount();

				$address->setSubtotal(0);
				$address->setBaseSubtotal(0);

				$address->setGrandTotal(0);
				$address->setBaseGrandTotal(0);

				$address->collectTotals();

				$quote->setSubtotal((float)$quote->getSubtotal() + $address->getSubtotal());
				$quote->setBaseSubtotal((float)$quote->getBaseSubtotal() + $address->getBaseSubtotal());

				$quote->setSubtotalWithDiscount(
					(float)$quote->getSubtotalWithDiscount() + $address->getSubtotalWithDiscount()
				);
				$quote->setBaseSubtotalWithDiscount(
					(float)$quote->getBaseSubtotalWithDiscount() + $address->getBaseSubtotalWithDiscount()
				);

				$quote->setGrandTotal((float)$quote->getGrandTotal() + $address->getGrandTotal());
				$quote->setBaseGrandTotal((float)$quote->getBaseGrandTotal() + $address->getBaseGrandTotal());

				$quote->save();

				$quote->setGrandTotal($quote->getBaseSubtotal() - $discountAmount)
					->setBaseGrandTotal($quote->getBaseSubtotal() - $discountAmount)
					->setSubtotalWithDiscount($quote->getBaseSubtotal() - $discountAmount)
					->setBaseSubtotalWithDiscount($quote->getBaseSubtotal() - $discountAmount)
					->save();


				if ($address->getAddressType() == $canAddItems) {
					//echo $address->setDiscountAmount; exit;
					$address->setSubtotalWithDiscount((float)$address->getSubtotalWithDiscount() - $discountAmount);
					$address->setGrandTotal((float)$address->getGrandTotal() - $discountAmount);
					$address->setBaseSubtotalWithDiscount((float)$address->getBaseSubtotalWithDiscount() - $discountAmount);
					$address->setBaseGrandTotal((float)$address->getBaseGrandTotal() - $discountAmount);
					if ($address->getDiscountDescription()) {
						$address->setDiscountAmount(-($address->getDiscountAmount() - $discountAmount));
						$address->setDiscountDescription($address->getDiscountDescription() . ', Discount to Partial Payment');
						$address->setBaseDiscountAmount(-($address->getBaseDiscountAmount() - $discountAmount));
					} else {
						$address->setDiscountAmount(-($discountAmount));
						$address->setDiscountDescription('Discount to Partial Payment');
						$address->setBaseDiscountAmount(-($discountAmount));
					}
					$address->save();
				}
			}
			//echo $quote->getGrandTotal();

			foreach ($quote->getAllItems() as $item) {
				//We apply discount amount based on the ratio between the GrandTotal and the RowTotal
				$rat = $item->getPriceInclTax() / $total;
				$ratdisc = $discountAmount * $rat;
				$item->setDiscountAmount(($item->getDiscountAmount() + $ratdisc) * $item->getQty());
				$item->setBaseDiscountAmount(($item->getBaseDiscountAmount() + $ratdisc) * $item->getQty())->save();
			}
		}
	}

	public function catalogProductSaveBefore($event) {
		$product = $event->getProduct();
		$recurrentOption = (boolean)$product->getMundipaggRecurrent();

		if ($recurrentOption) {
			$isRequired = true;
		} else {
			$isRequired = false;
		}

		try {
			$attribute = new Mage_Eav_Model_Entity_Attribute();
			$attribute->loadByCode(Mage_Catalog_Model_Product::ENTITY, 'mundipagg_recurrences');
			$attribute->setIsRequired($isRequired);
			$attribute->save();

		} catch (Mage_Adminhtml_Exception $e) {
			$log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
			$helper = Mage::helper('mundipagg');

			$errMsg = "{$helper->__("Internal error")}: {$e->getMessage()}";
			$log->error($helper->__("Unable to save product configuration: {$e}"));

			throw new Mage_Adminhtml_Exception($errMsg);
		}

	}

	public function checkRecurrencyFrequency(Varien_Event_Observer $observer) {
		$product = $observer->getProduct();
		$recurrent = (boolean)$product->getData('mundipagg_recurrent');
		$frequency = $product->getData('mundipagg_frequency_enum');

		if ($recurrent === true && $frequency == '0') {
			$helper = Mage::helper('mundipagg');
			$errMsg = $helper->__('Recurrency frequency is required');

			Mage::throwException($errMsg);
		}
	}

	/**
	 * @param Varien_Event $event
	 */
	public function cartCheckRecurrency($event) {
		/* @var Mage_Checkout_Model_Cart $cart */
		$cart = $event->getCart();

		/* @var Mage_Sales_Model_Quote $quote */
		$quote = $cart->getQuote();

		/* @var Mage_Sales_Model_Resource_Quote_Item_Collection $items */
		$items = $quote->getAllItems();

		/* @var Mage_Sales_Model_Quote_Item $item */
		foreach ($items as $item) {

			/* @var Mage_Sales_Model_Quote_Item_Option $option */
			foreach ($item->getOptions() as $option) {
				/* @var Mage_Catalog_Model_Product $product */
				$product = $option->getProduct();
				$product->load($product->getId());

				if ($product->getMundipaggRecurrent()) {
					$this->setQuoteRecurrencyFlag(true);

					return;
				}
			}
		}

		$this->setQuoteRecurrencyFlag(false);
	}

	/**
	 * @param boolean $option
	 *
	 */
	private function setQuoteRecurrencyFlag($option) {
		$session = Mage::getSingleton('checkout/session');
		$session->setMundipaggRecurrency($option);
	}

}
