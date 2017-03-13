<?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition License
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magentocommerce.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_CatalogSearch
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://www.magentocommerce.com/license/enterprise-edition
 */


/**
 * Fulltext Collection
 *
 * @category    Mage
 * @package     Mage_CatalogSearch
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_CatalogSearch_Model_Resource_Fulltext_Collection extends Mage_Catalog_Model_Resource_Product_Collection
{
    /**
     * Retrieve query model object
     *
     * @return Mage_CatalogSearch_Model_Query
     */
    protected function _getQuery()
    {
        return Mage::helper('catalogsearch')->getQuery();
    }

    /**
     * Add search query filter
     *
     * @param string $query
     * @return Mage_CatalogSearch_Model_Resource_Fulltext_Collection
     */
    public function addSearchFilter($query)
    {
        Mage::getSingleton('catalogsearch/fulltext')->prepareResult();

        $this->getSelect()->joinInner(
            array('search_result' => $this->getTable('catalogsearch/result')),
            $this->getConnection()->quoteInto(
                'search_result.product_id=e.entity_id AND search_result.query_id=?',
                $this->_getQuery()->getId()
            ),
            array('relevance' => 'relevance')
        );

        return $this;
    }

    /**
     * Set Order field
     *
     * @param string $attribute
     * @param string $dir
     * @return Mage_CatalogSearch_Model_Resource_Fulltext_Collection
     */
    public function setOrder($attribute, $dir = 'desc')
    {
        if ($attribute == 'relevance') {
            $this->getSelect()->order("relevance {$dir}");
        } else {
            parent::setOrder($attribute, $dir);
        }
        return $this;
    }

    /**
     * Stub method for campatibility with other search engines
     *
     * @return Mage_CatalogSearch_Model_Resource_Fulltext_Collection
     */
    public function setGeneralDefaultQuery()
    {
        return $this;
    }
     public function addCategoriesFilter($cat_id)
    {
         $categories=Mage::getModel('catalog/category')->load($cat_id);
        $this->_productLimitationFilters['category_ids'] = $categories;

        if ($this->getStoreId() == Mage_Core_Model_App::ADMIN_STORE_ID) {
            $this->_applyZeroStoreProductLimitations();
        } else {
            $this->_applyProductLimitations();
        }

        return $this;
    }

    protected function _applyProductLimitations()
    {
        $this->_prepareProductLimitationFilters();
        $this->_productLimitationJoinWebsite();
        $this->_productLimitationJoinPrice();
        $filters = $this->_productLimitationFilters;

        // Addition: support for filtering multiple categories.
        if (!isset($filters['category_id']) && !isset($filters['category_ids']) && !isset($filters['visibility'])) {
            return $this;
        }

        $conditions = array(
            'cat_index.product_id=e.entity_id',
            $this->getConnection()->quoteInto('cat_index.store_id=?', $filters['store_id'])
        );
        if (isset($filters['visibility']) && !isset($filters['store_table'])) {
            $conditions[] = $this->getConnection()
                ->quoteInto('cat_index.visibility IN(?)', $filters['visibility']);
        }

        // Addition: support for filtering multiple categories.
        if (!isset($filters['category_ids'])) {
             $conditions[] = $this->getConnection()
                ->quoteInto('cat_index.category_id=?', $filters['category_id']);
            if (isset($filters['category_is_anchor'])) {
                $conditions[] = $this->getConnection()
                    ->quoteInto('cat_index.is_parent=?', $filters['category_is_anchor']);
            }
        } else {
            $conditions[] = $this->getConnection()->quoteInto('cat_index.category_id IN(' . implode(',', $filters['category_ids']) . ')', "");
        }

        $joinCond = join(' AND ', $conditions);
        $fromPart = $this->getSelect()->getPart(Zend_Db_Select::FROM);
        if (isset($fromPart['cat_index'])) {
            $fromPart['cat_index']['joinCondition'] = $joinCond;
            $this->getSelect()->setPart(Zend_Db_Select::FROM, $fromPart);
        }
        else {
            $this->getSelect()->join(
                array('cat_index' => $this->getTable('catalog/category_product_index')),
                $joinCond,
                array('cat_index_position' => 'position')
            );
        }

        $this->_productLimitationJoinStore();

        Mage::dispatchEvent('catalog_product_collection_apply_limitations_after', array(
            'collection'    => $this
        ));

        return $this;
    }

    protected function _applyZeroStoreProductLimitations()
    {
        $filters = $this->_productLimitationFilters;

        // Addition: supprot for filtering multiple categories.
        $categoryCondition = null;
        if (!isset($filters['category_ids'])) {
            $categoryCondition = $this->getConnection()->quoteInto('cat_pro.category_id=?', $filters['category_id']);
        } else {
            $categoryCondition = $this->getConnection()->quoteInto('cat_pro.category_id IN(' . implode(',', $filters['category_ids']) . ')', "");
        }

        $conditions = array(
            'cat_pro.product_id=e.entity_id',
            $categoryCondition
        );
        $joinCond = join(' AND ', $conditions);

        $fromPart = $this->getSelect()->getPart(Zend_Db_Select::FROM);
        if (isset($fromPart['cat_pro'])) {
            $fromPart['cat_pro']['joinCondition'] = $joinCond;
            $this->getSelect()->setPart(Zend_Db_Select::FROM, $fromPart);
        }
        else {
            $this->getSelect()->join(
                array('cat_pro' => $this->getTable('catalog/category_product')),
                $joinCond,
                array('cat_index_position' => 'position')
            );
        }

        return $this;
    }
}
