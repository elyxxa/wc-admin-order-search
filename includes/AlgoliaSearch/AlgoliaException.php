<?php

namespace WC_Product_Search_Admin\AlgoliaSearch;

/**
 * This is a compatibility wrapper for the Algolia AlgoliaException.
 * We directly extend Exception here for simplicity since the exception
 * is caught and reported but not usually inspected in detail.
 */
class AlgoliaException extends \Exception
{
    /**
     * Constructor that wraps the original AlgoliaException.
     *
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
