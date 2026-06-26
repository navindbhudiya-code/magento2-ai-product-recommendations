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

namespace NavinDBhudiya\ProductRecommendation\Cron;

use Magento\Framework\Stdlib\DateTime\DateTime;
use NavinDBhudiya\ProductRecommendation\Model\ResourceModel\Event as EventResource;
use NavinDBhudiya\ProductRecommendation\Model\ResourceModel\StatsDaily as StatsResource;
use NavinDBhudiya\ProductRecommendation\Service\Analytics\StatsAggregator;
use Psr\Log\LoggerInterface;

/**
 * Rolls recent raw events up into the daily stats table and prunes old raw events.
 */
class RollupStats
{
    /**
     * Roll up a trailing window so late-arriving events are captured.
     */
    private const ROLLUP_WINDOW_DAYS = 2;

    /**
     * Raw-event retention in days.
     */
    private const RAW_RETENTION_DAYS = 90;

    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var StatsResource
     */
    private StatsResource $statsResource;

    /**
     * @var StatsAggregator
     */
    private StatsAggregator $aggregator;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param EventResource $eventResource
     * @param StatsResource $statsResource
     * @param StatsAggregator $aggregator
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        EventResource $eventResource,
        StatsResource $statsResource,
        StatsAggregator $aggregator,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->eventResource = $eventResource;
        $this->statsResource = $statsResource;
        $this->aggregator = $aggregator;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Cron entry point.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $to = $this->dateTime->gmtDate('Y-m-d');
            $fromTs = $this->dateTime->gmtTimestamp() - self::ROLLUP_WINDOW_DAYS * 86400;
            $from = $this->dateTime->gmtDate('Y-m-d', $fromTs);

            $rawEvents = $this->eventResource->fetchForAggregation($from, $to);
            $rows = $this->aggregator->aggregate($rawEvents);
            $this->statsResource->upsertRows($rows);

            $retentionCutoff = $this->dateTime->gmtDate(
                'Y-m-d',
                $this->dateTime->gmtTimestamp() - self::RAW_RETENTION_DAYS * 86400
            );
            $pruned = $this->eventResource->pruneOlderThan($retentionCutoff);

            $this->logger->info(sprintf(
                '[ProductRecommendation] Rolled up %d stat rows (%s..%s); pruned %d raw events.',
                count($rows),
                $from,
                $to,
                $pruned
            ));
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Roll-up cron failed: ' . $e->getMessage());
        }
    }
}
