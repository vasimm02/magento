<?php

class Autocompleteplus_Autosuggest_Model_Config extends Mage_Core_Model_Abstract
{
    protected $_helper = false;
    protected $_apiEndpoint = false;

    /**
     * Define XML Config paths and states.
     */
    const XML_SEARCH_LAYERED_DISABLED = 0;
    const XML_SEARCH_LAYERED_ENABLED = 1;
    const XML_SEARCH_LAYERED_CONFIG = 'autocompleteplus/config/layered';
    const XML_API_ENDPOINT_CONFIG = 'default/autocompleteplus/config/api_endpoint';
    const XML_STORE_EMAIL_CONFIG = 'autocompleteplus/config/store_email';
    const XML_AUTHORIZATION_KEY_CONFIG = 'autocompleteplus_autosuggest/config/authorization_key';
    const XML_UUID_CONFIG = 'autocompleteplus_autosuggest/config/uuid';
    const XML_SITE_URL_CONFIG = 'autocompleteplus_autosuggest/config/site_url';
    const XML_IS_REACHABLE_CONFIG = 'autocompleteplus_autosuggest/config/is_reachable';
    const XML_ERROR_MESSAGE_CONFIG = 'autocompleteplus_autosuggest/config/error_message';

    /**
     * Fetch Magento Config Model.
     *
     * @return false|Mage_Core_Model_Config
     */
    protected function _getMageConfig()
    {
        return Mage::getModel('core/config');
    }

    /**
     * Fetch API Endpoint URL.
     *
     * @return string|Mage_Core_Model_Config_Element
     */
    public function getEndpoint()
    {
        if (!$this->_apiEndpoint) {
            $this->_apiEndpoint = Mage::getConfig()->getNode(self::XML_API_ENDPOINT_CONFIG);
        }

        return $this->_apiEndpoint;
    }

    /**
     * Fetch AA Helper.
     *
     * @return bool|Mage_Core_Helper_Abstract
     */
    protected function _getHelper()
    {
        if (!$this->_helper) {
            $this->_helper = Mage::helper('autocompleteplus_autosuggest');
        }

        return $this->_helper;
    }

    /**
     * Enable Layered Navigation.
     *
     * @param string $scope
     * @param int    $scopeId
     */
    public function enableLayeredNavigation($scope = 'default', $scopeId = 0)
    {
        $this->_getMageConfig()->saveConfig(self::XML_SEARCH_LAYERED_CONFIG, self::XML_SEARCH_LAYERED_ENABLED, $scope, $scopeId);
    }

    /**
     * Disable Layered Navigation.
     *
     * @param string $scope
     * @param int    $scopeId
     */
    public function disableLayeredNavigation($scope = 'default', $scopeId = 0)
    {
        $this->_getMageConfig()->saveConfig(self::XML_SEARCH_LAYERED_CONFIG, self::XML_SEARCH_LAYERED_DISABLED, $scope, $scopeId);
    }

    /**
     * Get Layered Navigation Status.
     *
     * @param $scopeId
     *
     * @return mixed
     */
    public function getLayeredNavigationStatus($scopeId)
    {
        return Mage::getStoreConfig(self::XML_SEARCH_LAYERED_CONFIG, $scopeId);
    }

    /**
     * Set Authorization Key.
     *
     * @param $key
     */
    public function setAuthorizationKey($key)
    {
        $this->_getMageConfig()->saveConfig(self::XML_AUTHORIZATION_KEY_CONFIG, $key);
    }

    /**
     * Get Authorization Key.
     *
     * @return mixed
     */
    public function getAuthorizationKey()
    {
        return Mage::getStoreConfig(self::XML_AUTHORIZATION_KEY_CONFIG);
    }

    /**
     * Set UUID.
     *
     * @param $uuid
     */
    public function setUUID($uuid)
    {
        $this->_getMageConfig()->saveConfig(self::XML_UUID_CONFIG, $uuid);
    }

    /**
     * Get UUID.
     *
     * @return mixed
     */
    public function getUUID()
    {
        return Mage::getStoreConfig(self::XML_UUID_CONFIG);
    }

    /**
     * Get UUID and Authorization key.
     *
     * @return array
     */
    public function getBothKeys()
    {
        return array('uuid' => $this->getUUID(), 'authkey' => $this->getAuthorizationKey());
    }

    /**
     * Set Site URL.
     *
     * @param $url
     */
    public function setSiteUrl($url)
    {
        $this->_getMageConfig()->saveConfig(self::XML_SITE_URL_CONFIG, $url);
    }

    /**
     * Get Site URL.
     *
     * @return mixed
     */
    public function getSiteUrl()
    {
        return Mage::getStoreConfig(self::XML_SITE_URL_CONFIG);
    }

    /**
     * Set Is Reachable.
     *
     * @param $reachable
     */
    public function setIsReachable($reachable)
    {
        $this->_getMageConfig()->saveConfig(self::XML_IS_REACHABLE_CONFIG, $reachable);
    }

    /**
     * Get Is Reachable.
     *
     * @return mixed
     */
    public function isReachable()
    {
        return Mage::getStoreConfig(self::XML_IS_REACHABLE_CONFIG);
    }

    /**
     * Set Error Message.
     *
     * @param $message
     */
    public function setErrorMessage($message)
    {
        $this->_getMageConfig()->saveConfig(self::XML_ERROR_MESSAGE_CONFIG, $message);
    }

    /**
     * Get Error Message.
     *
     * @return mixed
     */
    public function getErrorMessage()
    {
        return Mage::getStoreConfig(self::XML_ERROR_MESSAGE_CONFIG);
    }

    /**
     * Get Module Version.
     *
     * @return mixed
     */
    public function getModuleVersion()
    {
        return Mage::getConfig()->getModuleConfig('Autocompleteplus_Autosuggest')->version;
    }

    /**
     * Generate Config for AutocompletePlus.
     *
     * @param string $UUID
     * @param string $key
     *
     * @return $this
     *
     * @throws Zend_Http_Client_Exception
     */
    public function generateConfig($UUID = null, $key = null)
    {

        $client = new Varien_Http_Client();
        $fileIo = new Varien_Io_File();
        $fileIo->open(array('path' => Mage::getBaseDir()));
        $robotsTxtContent = $fileIo->read('robots.txt');

        $config = array(
            'adapter' => 'Zend_Http_Client_Adapter_Curl',
            'curloptions' => array(
                CURLOPT_RETURNTRANSFER => 1,
            ),
        );

        $params = array(
            'site'       => $this->_getHelper()->getConfigDataByFullPath('web/unsecure/base_url'),
            'email'      => Mage::getStoreConfig(self::XML_STORE_EMAIL_CONFIG),
            'f'          => $this->_getHelper()->getVersion(),
            'multistore' => $this->_getHelper()->getMultiStoreDataJson(),
        );

        if ($UUID && $key) {
            $params['uuid'] = $UUID;
            $params['key']  = $key;
        }

        $client->setUri($this->getEndpoint().'/install')
            ->setMethod('POST')
            ->setConfig($config);

        $client->setParameterPost($params);

        try {
            $response = $client->request();

            if ($response->isSuccessful()) {
                $responseData = json_decode($response->getBody(), true);

                /*
                 * Validate uuid exists
                 */
                if (isset($responseData['uuid']) && strlen($responseData['uuid']) > 50) {
                    Mage::log('Registration failed - please check response below', null, 'autocomplete.log', true);
                    $this->_sendError('Could not get license string.');

                    return false;
                } elseif (!isset($responseData['uuid'])) {
                    Mage::log('Registration failed - please check response below', null, 'autocomplete.log', true);
                    $this->_sendError('Could not get license string.');

                    return false;
                }

                $siteMapUrl = 'Sitemap:http://magento.instantsearchplus.com/ext_sitemap?u='.$responseData['uuid'].PHP_EOL;
                if ($fileIo->fileExists('robots.txt')) {
                    if (strpos($robotsTxtContent, $siteMapUrl) === false) {
                        if ($fileIo->isWriteable('robots.txt')) {
                            $fileIo->write('robots.txt', $robotsTxtContent.$siteMapUrl);
                        } else {
                            //write message that file is not writteble
                            $this->_sendError('File '.$fileIo->pwd().DS.'robots.txt is not writable.');
                        }
                    }
                } else {
                    //create file
                    if ($fileIo->isWriteable(Mage::getBaseDir())) {
                        //create robots sitemap
                        $fileIo->write('robots.txt', $siteMapUrl);
                    } else {
                        //write message that directory is not writeable
                        $this->_sendError('Directory '.Mage::getBaseDir().' is not writable..');
                    }
                }
            }
        } catch (Exception $e) {
            $responseData['uuid'] = 'failed';
            $errorMessage = $e->getMessage();
            Mage::logException($e);
            Mage::log('Install failed with a message: '.$e->getMessage(), null, 'autocomplete.log', true);
            $this->_sendError($e->getMessage());
        }

        $this->setAuthorizationKey($responseData['authentication_key']);
        $this->setUUID($responseData['uuid']);
        $this->setSiteUrl(Mage::getBaseUrl());
        $this->setIsReachable($responseData['is_reachable']);
        $this->setErrorMessage(isset($errorMessage) ? $errorMessage : '');

        Mage::dispatchEvent('autocompleteplus_autosuggest_config_creation_after',
            array('config' => $this, 'response' => $response, 'responseData' => $responseData));

        return $this;
    }

    /**
     * Send error to API.
     *
     * @param string $message
     *
     * @return Zend_Http_Response
     *
     * @throws Zend_Http_Client_Exception
     */
    protected function _sendError($message = 'No Message Provided')
    {
        $errClient = new Varien_Http_Client();
        $errClient->setUri($this->getEndpoint().'/install_error')
            ->setMethod('POST');

        $errClient->setParameterPost(array(
            'site' => Mage::getBaseUrl(),
            'msg' => $message,
            'email' => Mage::getStoreConfig(self::XML_STORE_EMAIL_CONFIG),
            'multistore' => $this->_getHelper()->getMultiStoreDataJson(),
            'f' => $this->getModuleVersion(),
        ));

        return $errClient->request();
    }

    /**
     * Support deprecated functionality - needed? Or can we change the install scripts?
     */
    public function getCollection()
    {
        return new Varien_Object(array(
            'authkey' => $this->getAuthorizationKey(),
            'licensekey' => $this->getUUID(),
        ));
    }
}
