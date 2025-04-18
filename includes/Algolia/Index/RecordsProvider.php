<?php

namespace WC_Product_Search_Admin\Algolia\Index;

interface RecordsProvider
{
    /**
     * @param int $page
     * @param int $perPage
     *
     * @return array
     */
    public function getRecords($page, $perPage);

    /**
     * @param int $perPage
     *
     * @return int
     */
    public function getTotalPagesCount($perPage);
}
