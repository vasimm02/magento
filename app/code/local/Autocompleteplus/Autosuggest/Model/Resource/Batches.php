<?php

class Autocompleteplus_Autosuggest_Model_Resource_Batches extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Resource initialization.
     */
    protected function _construct()
    {
        $this->_init('autocompleteplus_autosuggest/batches', 'id');
    }
}
