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
 * Resource model for recommendation interaction events.
 */
class Event extends AbstractDb
{
    /**
     * Columns accepted on insert (helper keys like stat_date are dropped).
     */
    private const COLUMNS = [
        'slot',
        'product_id',
        'recommended_id',
        'event_type',
        'session_id',
        'store_id',
        'variant',
        'revenue',
        'is_demo',
        'created_at',
    ];

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('navindbhudiya_reco_event', 'event_id');
    }

    /**
     * Bulk-insert event rows. Unknown keys are ignored so SeedPlanner output can be passed as-is.
     *
     * @param array $rows
     * @return int Number of rows inserted.
     */
    public function insertBatch(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        $clean = [];
        foreach ($rows as $row) {
            $record = [];
            foreach (self::COLUMNS as $column) {
                if (array_key_exists($column, $row)) {
                    $record[$column] = $row[$column];
                }
            }
            if ($record !== []) {
                $clean[] = $record;
            }
        }
        if ($clean === []) {
            return 0;
        }
        return $this->getConnection()->insertMultiple($this->getMainTable(), $clean);
    }

    /**
     * Fetch raw rows shaped for StatsAggregator over an optional date range / store.
     *
     * @param string|null $from Y-m-d inclusive.
     * @param string|null $to Y-m-d inclusive.
     * @param int|null $storeId
     * @return array
     */
    public function fetchForAggregation(?string $from = null, ?string $to = null, ?int $storeId = null): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(
                $this->getMainTable(),
                [
                    'stat_date' => new \Zend_Db_Expr('DATE(created_at)'),
                    'slot',
                    'store_id',
                    'variant',
                    'event_type',
                    'revenue',
                    'is_demo',
                ]
            );
        if ($from !== null) {
            $select->where('created_at >= ?', $from . ' 00:00:00');
        }
        if ($to !== null) {
            $select->where('created_at <= ?', $to . ' 23:59:59');
        }
        if ($storeId !== null) {
            $select->where('store_id = ?', $storeId);
        }
        return $connection->fetchAll($select);
    }

    /**
     * Whether any synthetic demo rows exist (drives the "Demo data" banner).
     *
     * @return bool
     */
    public function hasDemoRows(): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('is_demo = ?', 1);
        return (int) $connection->fetchOne($select) > 0;
    }

    /**
     * Delete events older than a cut-off date (retention).
     *
     * @param string $cutoffDate Y-m-d.
     * @return int
     */
    public function pruneOlderThan(string $cutoffDate): int
    {
        return $this->getConnection()->delete(
            $this->getMainTable(),
            ['created_at < ?' => $cutoffDate . ' 00:00:00']
        );
    }

    /**
     * Delete all events for a session (GDPR erase support).
     *
     * @param string $sessionId
     * @return int
     */
    public function deleteBySession(string $sessionId): int
    {
        return $this->getConnection()->delete($this->getMainTable(), ['session_id = ?' => $sessionId]);
    }
}
