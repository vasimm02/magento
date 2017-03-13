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

/**
 * Used in creating options for Yes|No config value selection.
 */
class Autocompleteplus_Autosuggest_Block_Adminhtml_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set Template.
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('autocompleteplus/system/config/button.phtml');
    }

    /**
     * Return element html.
     *
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        return $this->_toHtml();
    }

    public function getUUID()
    {
        $config = Mage::getModel('autocompleteplus_autosuggest/config');

        return $config->getUUID();
    }

    /**
     * Return ajax url for button.
     *
     * @return string
     */
    public function getAjaxCheckUrl()
    {
        return Mage::helper('adminhtml')->getUrl('autocompleteplus/products/updateemail');
    }

    /**
     * Generate button html.
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')

            ->setData(array(
                'id' => 'autocompleteplus_button',
                'label' => $this->helper('adminhtml')->__('Update'),
                'onclick' => 'javascript:updateautocomplete(); return false;',
            ));

        return $button->toHtml();
    }
}
