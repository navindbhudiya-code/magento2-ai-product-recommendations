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

namespace NavinDBhudiya\ProductRecommendation\Service\Privacy;

/**
 * Pure retention-window math for GDPR data minimisation.
 *
 * Given "today" (passed in, never read from the clock) and a retention window, computes the
 * cut-off date before which rows must be deleted, and whether a given row is expired.
 */
class RetentionPolicy
{
    /**
     * Compute the cut-off date: rows dated strictly before this should be deleted.
     *
     * @param string $today Y-m-d.
     * @param int $retentionDays
     * @return string Y-m-d.
     */
    public function cutoffDate(string $today, int $retentionDays): string
    {
        $days = max(0, $retentionDays);
        $ts = strtotime($today . ' -' . $days . ' days');
        return $ts === false ? $today : date('Y-m-d', $ts);
    }

    /**
     * Whether a row dated $rowDate is past the retention window relative to $today.
     *
     * @param string $rowDate Y-m-d.
     * @param string $today Y-m-d.
     * @param int $retentionDays
     * @return bool
     */
    public function isExpired(string $rowDate, string $today, int $retentionDays): bool
    {
        return $rowDate < $this->cutoffDate($today, $retentionDays);
    }
}
