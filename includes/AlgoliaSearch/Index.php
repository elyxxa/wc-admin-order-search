<?php

namespace WC_Product_Search_Admin\AlgoliaSearch;

/**
 * This is a compatibility wrapper for the Algolia Index.
 */
class Index
{
    /**
     * @var \WC_Order_Search_Admin\AlgoliaSearch\Index
     */
    private $index;

    /**
     * Constructor that wraps the original Index.
     *
     * @param string $indexName
     * @param Client $client
     */
    public function __construct($indexName, $client)
    {
        $this->index = new \WC_Order_Search_Admin\AlgoliaSearch\Index($indexName, $client);
    }

    /**
     * Forward all method calls to the real index.
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->index, $method], $args);
    }

    /**
     * Implementation of clearObjects which is used in our framework but maps to clearIndex in Algolia
     *
     * @param bool $forwardToReplicas
     * @return mixed
     */
    public function clearObjects($forwardToReplicas = false)
    {
        // In Algolia SDK, this is named clearIndex()
        return $this->index->clearIndex();
    }

    /**
     * Implementation of addObjects
     *
     * @param array $objects
     * @return mixed
     */
    public function addObjects($objects)
    {
        return $this->index->addObjects($objects);
    }

    /**
     * Implementation of deleteObject
     *
     * @param string $objectId
     * @return mixed
     */
    public function deleteObject($objectId)
    {
        return $this->index->deleteObject($objectId);
    }

    /**
     * Implementation of setSettings
     *
     * @param array $settings
     * @param bool $forwardToReplicas
     * @return mixed
     */
    public function setSettings($settings, $forwardToReplicas = false)
    {
        return $this->index->setSettings($settings, $forwardToReplicas);
    }
}
