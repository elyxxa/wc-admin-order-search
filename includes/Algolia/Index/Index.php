<?php

namespace WC_Product_Search_Admin\Algolia\Index;

use WC_Product_Search_Admin\AlgoliaSearch\Client;

abstract class Index
{
    /**
     * @return string
     */
    abstract public function getName();

    /**
     * @param bool $forwardToReplicas
     *
     * @return void
     */
    public function clear($forwardToReplicas = false)
    {
        error_log('WC_PSA: Attempting to clear Algolia index using clearIndex()');
        try {
            $this->getAlgoliaIndex()->clearIndex();
            error_log('WC_PSA: Successfully cleared Algolia index');
        } catch (\Exception $e) {
            error_log('WC_PSA: Error clearing Algolia index: ' . $e->getMessage());
        }
    }

    /**
     * @param bool $forwardToReplicas
     *
     * @return void
     */
    public function delete($forwardToReplicas = false)
    {
        // Algolia SDK doesn't have direct delete method for indices
        // But we can perform a similar operation by clearing the index
        $this->getAlgoliaIndex()->clearIndex();
    }

    /**
     * @return void
     */
    public function setSettings($forwardToReplicas = false)
    {
        $settings = $this->getSettings()->toArray();
        $this->getAlgoliaIndex()->setSettings($settings, $forwardToReplicas);
    }

    /**
     * @param bool $forwardToReplicas
     *
     * @return void
     */
    public function pushSettings($forwardToReplicas = false)
    {
        $this->setSettings($forwardToReplicas);
    }

    /**
     * @param int    $page
     * @param int    $perPage
     * @param callable|null $callback A function receiving $batch, $page, $totalPages
     *
     * @return int
     */
    public function reIndex($forwardToReplicas = false, $perPage = 500, $callback = null)
    {
        $totalReindexedRecordsCount = 0;
        $this->clear($forwardToReplicas);
        $this->pushSettings($forwardToReplicas);

        $recordsProvider = $this->getRecordsProvider();

        $totalPagesCount = $recordsProvider->getTotalPagesCount($perPage);
        for ($page = 1; $page <= $totalPagesCount; $page++) {
            $totalReindexedRecordsCount += $this->pushRecords($page, $perPage, $callback);
        }

        return $totalReindexedRecordsCount;
    }

    /**
     * @param int $page
     * @param int $perPage
     * @param callable|null $batchCallback
     *
     * @return int
     */
    public function pushRecords($page, $perPage, $batchCallback = null)
    {
        $recordsProvider = $this->getRecordsProvider();

        $records = $recordsProvider->getRecords($page, $perPage);
        if (empty($records)) {
            return 0;
        }

        $this->getAlgoliaIndex()->addObjects($records);
        $recordsCount = count($records);

        if (null !== $batchCallback) {
            call_user_func($batchCallback, $records, $page, $recordsProvider->getTotalPagesCount($perPage));
        }

        return $recordsCount;
    }

    /**
     * @return \AlgoliaSearch\Index|\WC_Product_Search_Admin\AlgoliaSearch\Index
     */
    protected function getAlgoliaIndex()
    {
        return $this->getAlgoliaClient()->initIndex($this->getName());
    }

    /**
     * @return RecordsProvider
     */
    abstract protected function getRecordsProvider();

    /**
     * @return IndexSettings
     */
    abstract protected function getSettings();

    /**
     * @return Client
     */
    abstract protected function getAlgoliaClient();
}
