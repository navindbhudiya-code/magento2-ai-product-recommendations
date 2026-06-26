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
 * Pure consent gate: decides whether a tracking event may be written.
 *
 * Tracking only happens when the feature is enabled AND the visitor has given consent. Demo/
 * synthetic rows bypass visitor consent (they are not real people) but still require the
 * feature to be enabled.
 */
class ConsentGate
{
    /**
     * Whether an interaction event may be recorded.
     *
     * @param bool $trackingEnabled Admin feature flag.
     * @param bool $visitorConsent Whether the visitor consented to tracking.
     * @param bool $isDemo Whether the event is synthetic demo data.
     * @return bool
     */
    public function mayRecord(bool $trackingEnabled, bool $visitorConsent, bool $isDemo = false): bool
    {
        if (!$trackingEnabled) {
            return false;
        }
        return $isDemo || $visitorConsent;
    }
}
