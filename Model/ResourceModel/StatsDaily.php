<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * @category  NavinDBhudiya
 * @package   NavinDBhudiya_ProductRecommendation
 * @author    Navin Bhudiya
 * @license   MIT License
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for the daily recommendation stats roll-up.
 */
class StatsDaily extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('navindbhudiya_reco_stats_daily', 'stat_id');
    }

    /**
     * Upsert aggregated daily rows (replacing existing values for the same key).
     *
     * @param array $rows Output of StatsAggregator::aggregate().
     * @return void
     */
    public function upsertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $connection = $this->getConnection();
        $records = [];
        foreach ($rows as $row) {
            $records[] = [
                'stat_date' => $row['stat_date'],
                'slot' => $row['slot'],
                'store_id' => (int) $row['store_id'],
                'variant' => $row['variant'],
                'impressions' => (int) $row['impressions'],
                'clicks' => (int) $row['clicks'],
                'add_to_carts' => (int) $row['add_to_carts'],
                'purchases' => (int) $row['purchases'],
                'revenue' => (float) $row['revenue'],
                'is_demo' => !empty($row['is_demo']) ? 1 : 0,
            ];
        }
        $connection->insertOnDuplicate(
            $this->getMainTable(),
            $records,
            ['impressions', 'clicks', 'add_to_carts', 'purchases', 'revenue', 'is_demo']
        );
    }

    /**
     * Delete roll-up rows older than a cut-off date (retention).
     *
     * @param string $cutoffDate Y-m-d.
     * @return int
     */
    public function pruneOlderThan(string $cutoffDate): int
    {
        return $this->getConnection()->delete(
            $this->getMainTable(),
            ['stat_date < ?' => $cutoffDate]
        );
    }
}
