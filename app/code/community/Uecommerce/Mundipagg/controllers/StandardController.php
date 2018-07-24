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
class Uecommerce_Mundipagg_StandardController extends Mage_Core_Controller_Front_Action
{

    /**
     * Order instance
     */
    protected $_order;

    public function getOrder()
    {
        if ($this->_order == null) {
        }

        return $this->_order;
    }

    /**
     * Get block instance
     *
     * @return
     */
    protected function _getRedirectBlock()
    {
        return $this->getLayout()->createBlock('standard/redirect');
    }

    public function getStandard()
    {
        return Mage::getSingleton('mundipagg/standard');
    }

    protected function _expireAjax()
    {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
        }
    }

    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    /**
     * Partial payment
         *
     */
    public function partialAction()
    {
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
    public function partialPostAction()
    {
        $postData = $this->getRequest()->getPost('payment', array());

        if ($postData == false) {
            $this->_redirect();

            return;
        }

        /* @var Uecommerce_Mundipagg_Model_Standard $standard */
        $standard = Mage::getModel('mundipagg/standard');

        try {
            $route = $standard->retryAuthorization($this->getOnepage(), $postData);
            $this->_redirect($route);
        } catch (Exception $e) {
            /* @var Uecommerce_Mundipagg_Helper_CheckoutSession $session */
            $sessionHelper = Mage::helper('mundipagg/checkoutSession');
            $sessionHelper->getInstance()->addError($sessionHelper->__('Unable to authorize'));

            $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
            $log->debug($e->getMessage());

            $this->_redirect('/checkout/onepage/');
        }
    }

    /**
     * Cancel page
     */
    public function cancelAction()
    {
        $this->cancelOrder();

        //Render
        $this->loadLayout();
        $this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('mundipagg/standard_cancel'));
        $this->renderLayout();
    }

    /**
     * Force Cancel page
     */
    public function fcancelAction()
    {
        $this->cancelOrder();

        //Render
        $this->loadLayout();
        $this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('mundipagg/standard_fcancel'));
        $this->renderLayout();
    }

    /*
	* Cancel order and set quote as inactive
	*/
    private function cancelOrder()
    {
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

    // ------------------------------------------------------------
    private function orderRestAction($orderId)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        if (!$order || !$order->getPayment()) {
            return array('status_code' => 404);
        }

        return array('message' => $order->getPayment()->getAdditionalInformation());
    }

    private function getKeyFromAuthorizationHeader($basicAuth)
    {
        /***
         * @fixme improve this part, few readable lines are better than just an ugly one
         */
        return explode(':', base64_decode(explode(' ', $basicAuth)[1]))[0];
    }

    private function isNotAuthorizedRequest($basicAuth)
    {
        if (!$basicAuth) {
            return true;
        }

        $basicAuthKey = $this->getKeyFromAuthorizationHeader($basicAuth);

        if ($basicAuthKey !== Mage::getModel('mundipagg/standard')->getMerchantKey()) {
            return true;
        }

        return false;
    }

    private function wrongRestApiUsage($params)
    {
        /***
         * @fixme validation not working
         */
        if (count($params) !== 1) {
            return true;
        }

        return false;
    }

    private function manageRestRequest($params)
    {
        $endpoint = key($params);
        $id = $params[$endpoint];
        $restAction = strtolower($endpoint) . 'RestAction';

        $result = $this->$restAction($id);

        return $result;
    }

    private function isInvalidRequest()
    {
        $endpoint = key($this->getRequest()->getParams()) . 'RestAction';

        if ($this->getRequest()->isPost()) {
            return array('status_code' => 404);
        }

        if ($this->isNotAuthorizedRequest(Mage::app()->getRequest()->getHeader('Authorization'))) {
            return array('status_code' => 401);
        }

        if ($this->wrongRestApiUsage($this->getRequest()->getParams())) {
            return array('status_code' => 404);
        }

        if (!method_exists($this, $endpoint)) {
            return array('status_code' => 404);
        }

        return false;
    }

    public function restAction()
    {
        $invalid = $this->isInvalidRequest();

        if ($invalid) {
            $this->getResponse()
                ->setHeader('Content-type', 'application/json')
                ->setHeader('HTTP/1.0', $invalid['status_code'], true);
            return;
        }

        try {
            $result = $this->manageRestRequest($this->getRequest()->getParams());
        } catch (Exception $e) {
            $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
            $log->error($e->getMessage());
        }

        if (isset($result['status_code'])) {
            $this->getResponse()
                ->setHeader('Content-type', 'application/json')
                ->setHeader('HTTP/1.0', $result['status_code'], true);
            return;
        }

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setHeader('HTTP/1.0', $result['status_code'], true)
            ->setBody(Mage::helper('core')->jsonEncode($result['message']));
    }
    // ------------------------------------------------------------

    /**
     * Success page (also used for Mundipagg return page for payments like "debit" and "boleto")
     */
    public function successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $approvalRequestSuccess = $session->getApprovalRequestSuccess();
        $statusWithError = Uecommerce_Mundipagg_Model_Enum_CreditCardTransactionStatusEnum::WITH_ERROR;

        if ($approvalRequestSuccess == $statusWithError) {
            $lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
            $order = Mage::getModel('sales/order')->load($lastOrderId);

            try {
                Uecommerce_Mundipagg_Model_Standard::transactionWithError($order);
            } catch (Exception $e) {
                $log = new Uecommerce_Mundipagg_Helper_Log(__METHOD__);
                $log->error($e->getMessage());
                $log->info("Current order status: " . $order->getStatusLabel());
            }

            $approvalRequestSuccess = 'success';
        }

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
        } elseif ($approvalRequestSuccess == 'cancel') {
            $this->_redirect('mundipagg/standard/cancel');
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


    public function installmentsandinterestAction()
    {
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
    public function installmentsAction()
    {
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
                        if ($val >= $parcelaDe and $val <= $parcelaAte) {
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

    /**
     * @todo Verificar este método.
     */
    public function indexAction()
    {
        $order = Mage::getModel('sales/order')
                ->loadByIncrementId($this->getRequest()->getParam('order'));
        $payment = $order->getPayment();
        $info = $payment->getAdditionalInformation();
    }

    /** Returns info about module files and integrity */
    public function versionAction()
    {
        //auth
        $standardModel = Mage::getmodel('mundipagg/standard');
        $merchantKey = $standardModel->getmerchantKey();
        $merchantKeyHashEncoded = base64_encode(hash('sha512',$merchantKey));
        $urlToken = Mage::app()->getRequest()->getParam('token');

        if ($urlToken !== $merchantKeyHashEncoded) {
            header('HTTP/1.0 401 Unauthorized');
            $this->getResponse()->setBody('Unauthorized');
            return;
        }

        //gathering environment and module info
        $modmanFilePath = './app/code/community/Uecommerce/Mundipagg/etc/modman';
        $integrityCheckFile = './app/code/community/Uecommerce/Mundipagg/etc/integrityCheck';

        $installType = 'package';

        if (is_dir('./.modman')) {
            $installType = 'modman';
        }

        $info = [
            'modmanFilePath' => $modmanFilePath,
            'integrityCheckFile' => $integrityCheckFile,
            'phpVersion' => phpversion(),
            'magentoVersion' => Mage::getVersion(),
            'moduleVersion' => Mage::helper('mundipagg')->getExtensionVersion(),
            'installType' => $installType
        ];

        //integrity check
        $integrityData = json_decode(file_get_contents($integrityCheckFile),true);
        $files = $this->generateModuleFilesMD5s($modmanFilePath);

        $newFiles = [];
        $unreadableFiles = [];
        $alteredFiles = [];


        //validating files
        foreach ($files as $fileName => $md5) {
            if (
                $fileName === './app/code/community/Uecommerce/Mundipagg/etc/integrityCheck') {
                //skip validation of integrityCheck file
                continue;
            }
            if ($md5 === false) {
                $unreadableFiles[] = $fileName;
                continue;
            }
            if(isset($integrityData[$fileName])) {
                if ($md5 != $integrityData[$fileName]) {
                    $alteredFiles[] = $fileName;
                }
                continue;
            }
            $newFiles[$fileName] = $md5;
        }

        echo "<h3>Module info</h3>";
        echo '<pre>';
        print_r($info);
        echo '</pre>';
        echo json_encode($info);



        if (count($newFiles) > 0) {
            echo "<h3 style='color:red'>Warning! New files were added to module directories!</h3>";
            echo '<pre>';
            print_r($newFiles);
            echo '</pre>';
            echo json_encode($newFiles);
        }

        if (count($alteredFiles) > 0) {
            echo "<h3 style='color:red'>Warning! Module files were modified!</h3>";
            echo '<pre>';
            print_r($alteredFiles);
            echo '</pre>';
            echo json_encode($alteredFiles);
        }

        if (count($unreadableFiles) > 0) {
            echo "<h3 style='color:red'>Warning! Module files become unreadable!</h3>";
            echo '<pre>';
            print_r($unreadableFiles);
            echo '</pre>';
            echo json_encode($unreadableFiles);
        }

        echo '<h3>File List ('.count($files).')</h3><pre>';
        print_r($files);
        echo '</pre>';
        echo json_encode($files);

        echo '<h3>phpnfo()</h3>';
        phpinfo();

    }

    protected function dirCheckSum($dir)
    {
        $files = scandir($dir);
        $md5 = [];
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file = $dir . DIRECTORY_SEPARATOR . $file;

                $checkMethod = 'fileCheckSum';
                if (is_dir($file)) {
                    $checkMethod = 'dirCheckSum';
                }

                $md5[$file] = $this->$checkMethod($file);
            }
        }
        return $md5;
    }

    public function generateModuleFilesMD5s($modmanFilePath) {

        $modmanRawData = file_get_contents($modmanFilePath);

        $rawLines = explode("\n",$modmanRawData);
        $lines = [];
        foreach ($rawLines as $rawLine) {
            if (
                substr($rawLine,0,1) === '#' ||
                strlen($rawLine) === 0
            ) {
                continue;
            }
            $lines[] = array_values(array_filter(explode(' ',$rawLine)));
        }
        foreach ($lines as $index => $line) {
            $elementName = './' . $line[1];
            $checkMethod = 'fileCheckSum';
            if (is_dir($elementName)) {
                $checkMethod = 'dirCheckSum';
            }
            $lines[$index][] = $this->filterFileCheckSum($this->$checkMethod($elementName));
        }
        $files = [];
        foreach($lines as $line) {
            $files = array_merge($files,end($line));
        }

        //removing modman base files from generated hashs.
        foreach($files as $filePath => $md5) {
            if (strpos($filePath,'./.modman/') !== false) {
                unset($files[$filePath]);
            }
        }

        return $files;
    }

    protected function filterFileCheckSum($checkSumArray)
    {
        if(count($checkSumArray) === 1) {
            return $checkSumArray;
        }
        $data = serialize($checkSumArray);
        $data = explode('";s:32:"',$data);
        $currentFile = null;
        $currentMd5 = null;
        $files = [];
        foreach ($data as $line) {
            $raw = explode('"',$line);
            if( $currentFile ) {
                $files[$currentFile] = $raw[0];
                $currentFile = end($raw);
                continue;
            }
            $currentFile = end($raw);
        }
        return $files;
    }

    protected function filterFileMd5($pathArray)
    {
        $files = [];
        foreach ($pathArray as $path => $md5) {
            if (!is_dir($path)) {
                $files[$path] = $md5;
            }
            else {
                $files = array_merge($files,$this->filterFileMd5($path));
            }
        }
        return $files;
    }

    protected function fileCheckSum($file)
    {
        return  [
            $file => md5_file($file)
        ];
    }
}
