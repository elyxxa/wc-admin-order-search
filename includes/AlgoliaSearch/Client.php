<?php

namespace WC_Product_Search_Admin\AlgoliaSearch;

/**
 * This is a compatibility wrapper for the Algolia Client.
 * Instead of extending, we'll delegate to the original client class
 */
class Client
{
    /**
     * @var \WC_Order_Search_Admin\AlgoliaSearch\Client
     */
    private $client;

    /**
     * Constructor that creates a new Algolia client.
     *
     * @param string     $applicationID the application ID you have in your admin interface
     * @param string     $apiKey        a valid API key for the service
     * @param array|null $hostsArray    the list of hosts that you have received for the service
     * @param array      $options
     *
     * @throws \Exception
     */
    public function __construct($applicationID, $apiKey, $hostsArray = null, $options = array())
    {
        $this->client = new \WC_Order_Search_Admin\AlgoliaSearch\Client($applicationID, $apiKey, $hostsArray, $options);
    }

    /**
     * Forward method calls to the original client.
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->client, $method], $args);
    }

    /**
     * Forward static methods to the original Client class.
     */
    public static function generateSecuredApiKey($privateApiKey, $query, $userToken = null)
    {
        return \WC_Order_Search_Admin\AlgoliaSearch\Client::generateSecuredApiKey($privateApiKey, $query, $userToken);
    }

    /**
     * Initialize an index.
     */
    public function initIndex($indexName)
    {
        return $this->client->initIndex($indexName);
    }
}
