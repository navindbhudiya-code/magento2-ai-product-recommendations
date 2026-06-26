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

namespace NavinDBhudiya\ProductRecommendation\Service\Analytics;

/**
 * A/B lift + significance math (pure).
 *
 * Computes CTR / add-to-cart rate / revenue per impression for the AI and control variants,
 * the relative lift, and a basic significance flag via a two-proportion z-test on CTR
 * (|z| >= 1.96 ~ 95% confidence). Dependency-free so the numbers are reproducible.
 */
class LiftCalculator
{
    /**
     * Z critical value for ~95% two-sided confidence.
     */
    public const Z_95 = 1.96;

    /**
     * Compute per-variant rates, lift, and a significance flag.
     *
     * @param array $ai Variant totals: ['impressions','clicks','add_to_carts','purchases','revenue'].
     * @param array $control Same shape for the control variant.
     * @return array
     */
    public function compute(array $ai, array $control): array
    {
        $aiRates = $this->rates($ai);
        $controlRates = $this->rates($control);

        return [
            'ai' => $aiRates,
            'control' => $controlRates,
            'lift' => [
                'ctr_pct' => $this->liftPercent($aiRates['ctr'], $controlRates['ctr']),
                'atc_rate_pct' => $this->liftPercent($aiRates['atc_rate'], $controlRates['atc_rate']),
                'revenue_per_impression_pct' => $this->liftPercent(
                    $aiRates['revenue_per_impression'],
                    $controlRates['revenue_per_impression']
                ),
            ],
            'significant' => $this->isSignificant(
                (int) ($ai['clicks'] ?? 0),
                (int) ($ai['impressions'] ?? 0),
                (int) ($control['clicks'] ?? 0),
                (int) ($control['impressions'] ?? 0)
            ),
        ];
    }

    /**
     * Per-variant rates from raw totals.
     *
     * @param array $totals
     * @return array
     */
    private function rates(array $totals): array
    {
        $impressions = (int) ($totals['impressions'] ?? 0);
        $clicks = (int) ($totals['clicks'] ?? 0);
        $atc = (int) ($totals['add_to_carts'] ?? 0);
        $revenue = (float) ($totals['revenue'] ?? 0);

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $this->ratio($clicks, $impressions),
            'atc_rate' => $this->ratio($atc, $impressions),
            'revenue_per_impression' => $impressions > 0 ? round($revenue / $impressions, 4) : 0.0,
        ];
    }

    /**
     * Safe ratio rounded to 4 dp.
     *
     * @param int $numerator
     * @param int $denominator
     * @return float
     */
    private function ratio(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator, 4) : 0.0;
    }

    /**
     * Relative lift of AI over control as a percentage (0 when control is 0).
     *
     * @param float $aiRate
     * @param float $controlRate
     * @return float
     */
    private function liftPercent(float $aiRate, float $controlRate): float
    {
        if ($controlRate <= 0.0) {
            return 0.0;
        }
        return round(($aiRate - $controlRate) / $controlRate * 100, 2);
    }

    /**
     * Two-proportion z-test on CTR; true when |z| >= 1.96 (and both arms have impressions).
     *
     * @param int $clicksAi
     * @param int $impressionsAi
     * @param int $clicksControl
     * @param int $impressionsControl
     * @return bool
     */
    private function isSignificant(
        int $clicksAi,
        int $impressionsAi,
        int $clicksControl,
        int $impressionsControl
    ): bool {
        if ($impressionsAi <= 0 || $impressionsControl <= 0) {
            return false;
        }
        $p1 = $clicksAi / $impressionsAi;
        $p2 = $clicksControl / $impressionsControl;
        $pooled = ($clicksAi + $clicksControl) / ($impressionsAi + $impressionsControl);
        $se = sqrt($pooled * (1 - $pooled) * (1 / $impressionsAi + 1 / $impressionsControl));
        if ($se <= 0.0) {
            return false;
        }
        return abs(($p1 - $p2) / $se) >= self::Z_95;
    }
}
