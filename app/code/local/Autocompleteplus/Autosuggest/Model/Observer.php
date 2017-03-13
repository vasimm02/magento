<?php

/**
 * InstantSearchPlus (Autosuggest).
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Mage
 *
 * @copyright  Copyright (c) 2014 Fast Simon (http://www.instantsearchplus.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Autocompleteplus_Autosuggest_Model_Observer extends Mage_Core_Model_Abstract
{
    const AUTOCOMPLETEPLUS_WEBHOOK_URI = 'https://acp-magento.appspot.com/ma_webhook';
    const API_UPDATE_URI = 'http://magento.autocompleteplus.com/update';
    const WEBHOOK_CURL_TIMEOUT_LENGTH = 2;

    protected $imageField;
    protected $standardImageFields = array();
    protected $currency;

    public function _construct()
    {
        $this->imageField = Mage::getStoreConfig('autocompleteplus/config/imagefield');
        if (!$this->imageField) {
            $this->imageField = 'thumbnail';
        }

        $this->standardImageFields = array('image', 'small_image', 'thumbnail');
        $this->currency = Mage::app()->getStore()->getCurrentCurrencyCode();
    }

    public function getConfig()
    {
        return Mage::getModel('autocompleteplus_autosuggest/config');
    }

    protected function _generateProductXml($product)
    {
        $catalog = new SimpleXMLElement('<catalog></catalog>');
        try {
            if (in_array($this->imageField, $this->standardImageFields)) {
                $productImage = Mage::helper('catalog/image')->init($product, $this->imageField);
            } else {
                $function = 'get'.$this->imageField;
                $productImage = $product->$function();
            }
        } catch (Exception $e) {
            $productImage = '';
        }
        $productUrl = Mage::helper('catalog/product')->getProductUrl($product->getId());
        $status = $product->isInStock();
        $stockItem = $product->getStockItem();
        if ($stockItem && $stockItem->getIsInStock() && $status) {
            $saleable = 1;
        } else {
            $saleable = 0;
        }

        // Add Magento Module Version attribute
        $catalog->addAttribute('version', $this->getConfig()->getModuleVersion());

        // Add Magento Version attribute
        $catalog->addAttribute('magento', Mage::getVersion());

        // Create product child
        $productChild = $catalog->addChild('product');

        $productChild->addAttribute('store', $product->getStoreId());
        $productChild->addAttribute('currency', $this->currency);
        $productChild->addAttribute('visibility', $product->getVisibility());
        $productChild->addAttribute('price', $this->_getPrice($product));
        $productChild->addAttribute('url', $productUrl);
        $productChild->addAttribute('thumbs', $productImage);
        $productChild->addAttribute('selleable', $saleable);
        $productChild->addAttribute('action', 'update');

        $productChild->addChild('description', '<![CDATA['.$product->getDescription().']]>');
        $productChild->addChild('short', '<![CDATA['.$product->getShortDescription().']]>');
        $productChild->addChild('name', '<![CDATA['.$product->getName().']]>');
        $productChild->addChild('sku', '<![CDATA['.$product->getSku().']]>');

        return $catalog->asXML();
    }

    public function catalog_product_save_after_depr($observer)
    {
        $helper = Mage::helper('autocompleteplus_autosuggest');
        $product = $observer->getProduct();
        $this->imageField = Mage::getStoreConfig('autocompleteplus/config/imagefield');
        if (!$this->imageField) {
            $this->imageField = 'thumbnail';
        }
        $this->standardImageFields = array('image', 'small_image', 'thumbnail');
        $this->currency = Mage::app()->getStore()->getCurrentCurrencyCode();
        $domain = Mage::getStoreConfig('web/unsecure/base_url');
        $key = $this->getConfig()->getUUID();

        $xml = $this->_generateProductXml($product);
        $data = array(
            'site' => $domain,
            'key' => $key,
            'catalog' => $xml,
        );
        $res = $this->_sendUpdate($data);
        Mage::log($res, null, 'autocomplete.log');
    }

    protected function _getPrice($product)
    {
        $price = 0;
        $helper = Mage::helper('autocompleteplus_autosuggest');
        if ($product->getTypeId() == 'grouped') {
            $helper->prepareGroupedProductPrice($product);
            $_minimalPriceValue = $product->getPrice();
            if ($_minimalPriceValue) {
                $price = $_minimalPriceValue;
            }
        } elseif ($product->getTypeId() == 'bundle') {
            if (!$product->getFinalPrice()) {
                $price = $helper->getBundlePrice($product);
            } else {
                $price = $product->getFinalPrice();
            }
        } else {
            $price = $product->getFinalPrice();
        }
        if (!$price) {
            $price = 0;
        }

        return $price;
    }

    protected function _sendUpdate($data)
    {
        $client = new Varien_Http_Client();
        $config = array(
            'adapter' => 'Zend_Http_Client_Adapter_Curl',
            'curloptions' => array(
                CURLOPT_RETURNTRANSFER => 1,
            ),
        );
        $client->setUri(self::API_UPDATE_URI)
            ->setMethod('POST')
            ->setConfig($config);

        if (!empty($data)) {
            $client->setParameterPost($data);
        }

        return $client->request();
    }

    public function catalog_product_save_after($observer)
    {
        date_default_timezone_set('Asia/Jerusalem');
        $product = $observer->getProduct();
        $origData = $observer->getProduct()->getOrigData();
        $storeId = $product->getStoreId();
        $productId = $product->getId();
        $sku = $product->getSku();
        if (is_array($origData) &&
            array_key_exists('sku', $origData)) {
            $oldSku = $origData['sku'];
            if ($sku != $oldSku) {
                $this->_writeproductDeletion($oldSku, $productId, $storeId, $product);
            }
        }
        $dt = strtotime('now');
        $simple_product_parents = ($product->getTypeID() == 'simple') ? Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId()) : array();

        $product_stores = ($storeId == 0 && method_exists($product, 'getStoreIds')) ? $product->getStoreIds() : array($storeId);

        try {
            foreach ($product_stores as $product_store) {
                $updates = Mage::getModel('autocompleteplus_autosuggest/batches')->getCollection()
                    ->addFieldToFilter('product_id', $productId)
                    ->addFieldToFilter('store_id', $product_store);

                $updates->getSelect()->limit(1);

                if ($updates && $updates->getSize() > 0) {
                    // @codingStandardsIgnoreLine
                    $row = $updates->getFirstItem();

                    $row->setUpdateDate($dt)
                        ->setAction('update');

                    // @codingStandardsIgnoreLine
                    $row->save();
                } else {
                    $batch = Mage::getModel('autocompleteplus_autosuggest/batches');
                    $batch->setProductId($productId)
                        ->setStoreId($product_store)
                        ->setUpdateDate($dt)
                        ->setAction('update')
                        ->setSku($sku);

                    // @codingStandardsIgnoreLine
                    $batch->save();
                }
                try {
                    $helper = Mage::helper('autocompleteplus_autosuggest');
                    $checksum = $helper->calculateChecksum($product);
                    $helper->updateSavedProductChecksum($productId, $sku, $product_store, $checksum);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
                // trigger update for simple product's configurable parent
                if (!empty($simple_product_parents)) {   // simple product has configural parent
                    foreach ($simple_product_parents as $configurable_product) {
                        $batches = Mage::getModel('autocompleteplus_autosuggest/batches')->getCollection()
                            ->addFieldToFilter('product_id', $configurable_product)
                            ->addFieldToFilter('store_id', $product_store);

                        $batches->getSelect()->limit(1);

                        // @codingStandardsIgnoreLine
                        $batch = $batches->getFirstItem();
                        if ($batch->getSize() > 0) {
                            $batch->setUpdateDate($dt)
                                ->setAction('update')
                                // @codingStandardsIgnoreLine
                                ->save();
                        } else {
                            $newBatch = Mage::getModel('autocompleteplus_autosuggest/batches');
                            $newBatch->setProductId($configurable_product)
                                ->setStoreId($product_store)
                                ->setUpdateDate($dt)
                                ->setAction('update')
                                ->setSku('ISP_NO_SKU')
                                // @codingStandardsIgnoreLine
                                ->save();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    protected function _writeproductDeletion($sku, $productId, $storeId, $product = null)
    {
        $dt = strtotime('now');
        try {
            try {
                $helper = Mage::helper('autocompleteplus_autosuggest');
                try {
                    if (!$product) {
                        $product = Mage::getModel('catalog/product')->load($productId);
                    }
                    $product_stores = ($storeId == 0 && method_exists($product, 'getStoreIds')) ? $product->getStoreIds() : array($storeId);
                } catch (Exception $e) {
                    Mage::logException($e);
                    $product_stores = array($storeId);
                }
                if ($sku == null) {
                    $sku = 'dummy_sku';
                }
                foreach ($product_stores as $product_store) {
                    $batches = Mage::getModel('autocompleteplus_autosuggest/batches')->getCollection()
                        ->addFieldToFilter('product_id', $configurable_product)
                        ->addFieldToFilter('store_id', $product_store);

                    // @codingStandardsIgnoreLine
                    $batch = $batches->getFirstItem();
                    if ($batch->getSize() > 0) {
                        $batch->setUpdateDate($dt)
                            ->setAction('update')
                            // @codingStandardsIgnoreLine
                            ->save();
                    } else {
                        $newBatch = Mage::getModel('autocompleteplus_autosuggest/batches');
                        $newBatch->setProductId($configurable_product)
                            ->setStoreId($product_store)
                            ->setUpdateDate($dt)
                            ->setAction('update')
                            ->setSku($sku)
                            // @codingStandardsIgnoreLine
                            ->save();
                    }
                    $helper->updateDeletedProductChecksum($productId, $sku, $product_store);
                }
            } catch (Exception $e) {
                Mage::logException($e);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function catalog_product_delete_before($observer)
    {
        $product = $observer->getProduct();
        $storeId = $product->getStoreId();
        $productId = $product->getId();
        $sku = $product->getSku();
        $this->_writeproductDeletion($sku, $productId, $storeId, $product);
    }

    public function adminSessionUserLoginSuccess()
    {
        $notifications = array();
        /** @var Autocompleteplus_Autosuggest_Helper_Data $helper */
        $helper = Mage::helper('autocompleteplus_autosuggest');
        $command = 'http://magento.autocompleteplus.com/ext_info?u='.$this->getConfig()->getUUID();
        $res = $helper->sendCurl($command);
        $result = json_decode($res);
        if (isset($result->alerts)) {
            foreach ($result->alerts as $alert) {
                $notification = array(
                    'type' => (string) $alert->type,
                    'message' => (string) $alert->message,
                    'timestamp' => (string) $alert->timestamp,
                );
                if (isset($alert->subject)) {
                    $notification['subject'] = (string) $alert->subject;
                }
                $notifications[] = $notification;
            }
        }
        if (!empty($notifications)) {
            Mage::getResourceModel('autocompleteplus_autosuggest/notifications')->addNotifications($notifications);
        }
        $this->sendNotificationMails();
    }

    public function sendNotificationMails()
    {
        /** @var Autocompleteplus_Autosuggest_Model_Mysql4_Notifications_Collection $notifications */
        $notifications = Mage::getModel('autocompleteplus_autosuggest/notifications')->getCollection();
        $notifications->addTypeFilter('email')->addActiveFilter();
        foreach ($notifications as $notification) {
            $this->_sendStatusMail($notification);
        }
    }

    /**
     * @param Autocompleteplus_Autosuggest_Model_Notifications $notification
     */
    protected function _sendStatusMail($notification)
    {
        /** @var Autocompleteplus_Autosuggest_Helper_Data $helper */
        $helper = Mage::helper('autocompleteplus_autosuggest');
        // Getting site owner email
        $storeMail = $helper->getConfigDataByFullPath('autocompleteplus/config/store_email');
        if ($storeMail) {
            $emailTemplate = Mage::getModel('core/email_template');
            $emailTemplate->loadDefault('autosuggest_status_notification');
            $emailTemplate->setTemplateSubject($notification->getSubject());
            // Get General email address (Admin->Configuration->General->Store Email Addresses)
            $emailTemplate->setSenderName(Mage::getStoreConfig('trans_email/ident_general/email'));
            $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/name'));
            $emailTemplateVariables['message'] = $notification->getMessage();
            $emailTemplate->send($storeMail, null, $emailTemplateVariables);
            $notification->setIsActive(0)
                ->save();
        }
    }

    /**
     * The generic webhook service caller.
     *
     * @param Varien_Event_Observer $observer
     */
    public function webhook_service_call()
    {
        $curl = new Varien_Http_Adapter_Curl();
        $curl->setConfig(array(
            'timeout' => static::WEBHOOK_CURL_TIMEOUT_LENGTH,
        ));
        $curl->write(Zend_Http_Client::GET, $this->_getWebhookObjectUri());
        $curl->read();
        $curl->close();
    }

    /**
     * Create the webhook URI.
     *
     * @return string
     */
    protected function _getWebhookObjectUri()
    {
        $helper = Mage::helper('autocompleteplus_autosuggest');
        $parameters = array(
            'event' => $this->getWebhookEventLabel(),
            'UUID' => $this->getConfig()->getUUID(),
            'key' => $this->getConfig()->getAuthorizationKey(),
            'store_id' => Mage::app()->getStore()->getStoreId(),
            'st' => $helper->getSessionId(),
            'cart_token' => $this->getQuoteId(),
            'serp' => '',
            'cart_product' => $this->getCartContentsAsJson(),
        );

        return static::AUTOCOMPLETEPLUS_WEBHOOK_URI.'?'.http_build_query($parameters, '', '&');
    }

    /**
     * Return a label for webhooks based on the current
     * controller route. This cannot be handled by layout
     * XML because the layout engine may not be init in all
     * future uses of the webhook.
     *
     * @return string|void
     */
    public function getWebhookEventLabel()
    {
        $request = Mage::app()->getRequest();
        $route = $request->getRouteName();
        $controller = $request->getControllerName();
        $action = $request->getActionName();
        if ($route != 'checkout') {
            return;
        }
        if ($controller == 'cart' && $action == 'index') {
            return 'cart';
        }
        if ($controller == 'onepage' && $action == 'index') {
            return 'checkout';
        }
        if ($controller == 'onepage' && $action == 'success') {
            return 'success';
        }
    }

    /**
     * Returns the quote id if it exists, otherwise it will
     * return the last order id. This only is set in the session
     * when an order has been recently completed. Therefore
     * this call may also return null.
     *
     * @return string|null
     */
    public function getQuoteId()
    {
        if ($quoteId = Mage::getSingleton('checkout/session')->getQuoteId()) {
            return $quoteId;
        }

        return $this->getOrder()->getQuoteId();
    }

    /**
     * Get the order associated with the previous quote id
     * used as a fallback when the quote is no longer available.
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();

        return Mage::getModel('sales/order')->load($orderId);
    }

    /**
     * JSON encode the cart contents.
     *
     * @return string
     */
    public function getCartContentsAsJson()
    {
        return json_encode($this->_getVisibleItems());
    }

    /**
     * Format visible cart contents into a multidimensional keyed array.
     *
     * @return array
     */
    protected function _getVisibleItems()
    {
        if ($cartItems = Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems()) {
            return $this->_buildCartArray($cartItems);
        }

        return $this->_buildCartArray($this->getOrder()->getAllVisibleItems());
    }

    /**
     * Return a formatted array of quote or order items.
     *
     * @param array $cartItems
     *
     * @return array
     */
    protected function _buildCartArray($cartItems)
    {
        $items = array();
        foreach ($cartItems as $item) {
            if ($item instanceof Mage_Sales_Model_Order_Item) {
                $quantity = (int) $item->getQtyOrdered();
            } else {
                $quantity = $item->getQty();
            }
            if (is_object($item->getProduct())) {    // Fatal error fix: Call to a member function getId() on a non-object
                $items[] = array(
                    'product_id' => $item->getProduct()->getId(),
                    'price' => $item->getProduct()->getFinalPrice(),
                    'quantity' => $quantity,
                    'currency' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                    'attribution' => $item->getAddedFromSearch(),
                );
            }
        }

        return $items;
    }
}
