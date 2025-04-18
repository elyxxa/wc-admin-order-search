<?php

namespace WC_Product_Search_Admin\AlgoliaSearch;

/**
 * This is a compatibility wrapper for the Algolia ClientContext.
 */
class ClientContext
{
    /**
     * @var \WC_Order_Search_Admin\AlgoliaSearch\ClientContext
     */
    private $context;

    /**
     * @param string $applicationID
     * @param string $apiKey
     * @param array|null $hostsArray
     * @param bool $placesEnabled
     * @param \WC_Order_Search_Admin\AlgoliaSearch\FailingHostsCache|null $failingHostsCache
     */
    public function __construct($applicationID, $apiKey, $hostsArray = null, $placesEnabled = false, $failingHostsCache = null)
    {
        $this->context = new \WC_Order_Search_Admin\AlgoliaSearch\ClientContext($applicationID, $apiKey, $hostsArray, $placesEnabled, $failingHostsCache);
    }

    /**
     * Forward any method call to the real context object.
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->context, $method], $args);
    }

    /**
     * Magic getter to access all properties of the original context.
     */
    public function __get($name)
    {
        return $this->context->$name;
    }

    /**
     * Magic setter to update properties of the original context.
     */
    public function __set($name, $value)
    {
        $this->context->$name = $value;
    }
}
