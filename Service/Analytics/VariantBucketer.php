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
 * Deterministic A/B bucketing of a session into the `ai` or `control` variant.
 *
 * Pure and stable: the same session id always maps to the same variant (so a visitor's
 * experience is consistent), and a salt allows re-randomising the split per experiment.
 */
class VariantBucketer
{
    public const VARIANT_AI = 'ai';
    public const VARIANT_CONTROL = 'control';

    /**
     * Bucket a session id into a variant.
     *
     * @param string $sessionId
     * @param string $salt Optional experiment salt to reshuffle the assignment.
     * @return string Either VARIANT_AI or VARIANT_CONTROL.
     */
    public function bucket(string $sessionId, string $salt = ''): string
    {
        // crc32 over a salted key gives a stable, well-distributed integer; parity -> ~50/50.
        $hash = crc32($salt . '|' . $sessionId);
        return ($hash % 2 === 0) ? self::VARIANT_AI : self::VARIANT_CONTROL;
    }

    /**
     * Whether a session is in the AI variant.
     *
     * @param string $sessionId
     * @param string $salt
     * @return bool
     */
    public function isAi(string $sessionId, string $salt = ''): bool
    {
        return $this->bucket($sessionId, $salt) === self::VARIANT_AI;
    }
}
