<?php

namespace WC_Product_Search_Admin\Algolia\Index;

class IndexSettings
{
    /**
     * @var array
     */
    private $settings = [];

    /**
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * @param array $attributesForFaceting
     *
     * @return $this
     */
    public function setAttributesForFaceting(array $attributesForFaceting)
    {
        $this->settings['attributesForFaceting'] = $attributesForFaceting;

        return $this;
    }

    /**
     * @param array $searchableAttributes
     *
     * @return $this
     */
    public function setSearchableAttributes(array $searchableAttributes)
    {
        $this->settings['searchableAttributes'] = $searchableAttributes;

        return $this;
    }

    /**
     * @param array $customRanking
     *
     * @return $this
     */
    public function setCustomRanking(array $customRanking)
    {
        $this->settings['customRanking'] = $customRanking;

        return $this;
    }

    /**
     * @param array $attributesToRetrieve
     *
     * @return $this
     */
    public function setAttributesToRetrieve(array $attributesToRetrieve)
    {
        $this->settings['attributesToRetrieve'] = $attributesToRetrieve;

        return $this;
    }

    /**
     * @param array $attributesToHighlight
     *
     * @return $this
     */
    public function setAttributesToHighlight(array $attributesToHighlight)
    {
        $this->settings['attributesToHighlight'] = $attributesToHighlight;

        return $this;
    }

    /**
     * @param array $disableTypoToleranceOnAttributes
     *
     * @return $this
     */
    public function setDisableTypoToleranceOnAttributes(array $disableTypoToleranceOnAttributes)
    {
        $this->settings['disableTypoToleranceOnAttributes'] = $disableTypoToleranceOnAttributes;

        return $this;
    }

    /**
     * @param array $attributes
     *
     * @return $this
     */
    public function setAttributesToSnippet(array $attributes)
    {
        $this->settings['attributesToSnippet'] = $attributes;

        return $this;
    }

    /**
     * @param array $replicas
     *
     * @return $this
     */
    public function setReplicas(array $replicas)
    {
        $this->settings['replicas'] = $replicas;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->settings;
    }
}
