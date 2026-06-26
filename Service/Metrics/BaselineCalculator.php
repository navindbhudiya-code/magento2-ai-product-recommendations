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

namespace NavinDBhudiya\ProductRecommendation\Service\Metrics;

/**
 * Pure metric math for the baseline command: hit-rate@k and latency percentiles, no Magento deps.
 */
class BaselineCalculator
{
    /**
     * Compute a percentile of a list of numbers using linear interpolation between ranks.
     *
     * Non-numeric entries are ignored. An empty list yields 0.0.
     *
     * @param float[]|int[] $values
     * @param float $percentile 0-100
     * @return float
     */
    public function percentile(array $values, float $percentile): float
    {
        $numbers = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $numbers[] = (float) $value;
            }
        }
        if ($numbers === []) {
            return 0.0;
        }
        sort($numbers);
        $count = count($numbers);
        if ($percentile <= 0 || $count === 1) {
            return $numbers[0];
        }
        if ($percentile >= 100) {
            return $numbers[$count - 1];
        }
        $rank = ($percentile / 100) * ($count - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        if ($low === $high) {
            return $numbers[$low];
        }
        return $numbers[$low] + ($numbers[$high] - $numbers[$low]) * ($rank - $low);
    }

    /**
     * Aggregate per-pair evaluation rows into the baseline summary.
     *
     * Only rows flagged resolved=true count toward hit-rate and latency; unresolved
     * SKUs are reported separately and never counted as misses.
     *
     * @param array $rows Per-pair rows: each ['resolved' => bool, 'hit' => bool, 'latency_ms' => float].
     * @param int $k The "@k" cut-off (e.g. 10 for hit-rate@10).
     * @return array
     */
    public function summarize(array $rows, int $k = 10): array
    {
        $total = count($rows);
        $evaluated = 0;
        $hits = 0;
        $latencies = [];

        foreach ($rows as $row) {
            if (empty($row['resolved'])) {
                continue;
            }
            $evaluated++;
            if (!empty($row['hit'])) {
                $hits++;
            }
            if (isset($row['latency_ms']) && is_numeric($row['latency_ms'])) {
                $latencies[] = (float) $row['latency_ms'];
            }
        }

        $hitRate = $evaluated > 0 ? round($hits / $evaluated, 4) : 0.0;

        return [
            'k' => $k,
            'pairs_total' => $total,
            'pairs_evaluated' => $evaluated,
            'pairs_unresolved' => $total - $evaluated,
            'hits' => $hits,
            'misses' => $evaluated - $hits,
            'hit_rate_at_' . $k => $hitRate,
            'p50_latency_ms' => round($this->percentile($latencies, 50.0), 2),
            'p95_latency_ms' => round($this->percentile($latencies, 95.0), 2),
        ];
    }
}
