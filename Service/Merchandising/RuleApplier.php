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

namespace NavinDBhudiya\ProductRecommendation\Service\Merchandising;

/**
 * Applies a compiled merchandising rule to a ranked result set (pure, deterministic).
 *
 * Items are arrays: ['id' => mixed, 'score' => float, 'attributes' => [name => number]].
 * Boost raises matching items' scores, bury lowers them, filter removes them; the list is then
 * re-sorted by score descending. When a rule has a context condition that the supplied context
 * does not satisfy, the rule is dormant and the items are returned unchanged.
 */
class RuleApplier
{
    /**
     * Default boost/bury strength.
     */
    private const WEIGHT = 1.0;

    /**
     * Apply a compiled rule to items given a runtime context.
     *
     * @param array $items
     * @param array $rule Output of RuleCompiler::compile().
     * @param array $context Runtime values, e.g. ['basket_total' => 6000].
     * @return array Re-ordered (and possibly filtered) items.
     */
    public function apply(array $items, array $rule, array $context = []): array
    {
        if (empty($rule['valid'])) {
            return $items;
        }
        if (!$this->contextSatisfied($rule['context'] ?? null, $context)) {
            return $items;
        }

        $action = $rule['action'];
        $item = $rule['item'];
        $maxAttr = $this->maxAttribute($items, (string) $item['attribute']);

        $result = [];
        foreach ($items as $row) {
            $matches = $this->itemMatches($row, $item);

            if ($action === 'filter' && $matches) {
                continue;
            }
            if ($action === 'boost') {
                $row['score'] = $this->boostedScore($row, $item, $maxAttr, $matches, 1);
            } elseif ($action === 'bury' && $matches) {
                $row['score'] = (float) ($row['score'] ?? 0) * 0.25;
            }
            $result[] = $row;
        }

        usort($result, static function (array $a, array $b): int {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });
        return $result;
    }

    /**
     * Whether the rule's context condition is satisfied (true when there is no condition).
     *
     * @param array|null $condition
     * @param array $context
     * @return bool
     */
    private function contextSatisfied(?array $condition, array $context): bool
    {
        if ($condition === null) {
            return true;
        }
        $actual = (float) ($context[$condition['field']] ?? 0);
        return $this->compare($actual, $condition['op'], (float) $condition['value']);
    }

    /**
     * Whether an item matches the rule's item target (threshold rules; high/low always "match").
     *
     * @param array $row
     * @param array $item
     * @return bool
     */
    private function itemMatches(array $row, array $item): bool
    {
        $value = (float) ($row['attributes'][$item['attribute']] ?? 0);
        if ($item['op'] !== null) {
            return $this->compare($value, $item['op'], (float) $item['threshold']);
        }
        // Directional (high/low) targets apply to all items proportionally.
        return true;
    }

    /**
     * Compute a boosted score for an item.
     *
     * @param array $row
     * @param array $item
     * @param float $maxAttr
     * @param bool $matches
     * @param int $direction Unused sign placeholder kept for clarity.
     * @return float
     */
    private function boostedScore(array $row, array $item, float $maxAttr, bool $matches, int $direction): float
    {
        $score = (float) ($row['score'] ?? 0);
        $value = (float) ($row['attributes'][$item['attribute']] ?? 0);

        if ($item['op'] !== null) {
            // Threshold boost: matching items get a flat multiplier.
            return $matches ? $score * (1 + self::WEIGHT) : $score;
        }

        // Directional boost: scale by the attribute, normalised to [0,1].
        $norm = $maxAttr > 0 ? $value / $maxAttr : 0.0;
        if ($item['direction'] === 'low') {
            $norm = 1 - $norm;
        }
        return $score * (1 + self::WEIGHT * $norm);
    }

    /**
     * Maximum value of an attribute across items (for normalisation).
     *
     * @param array $items
     * @param string $attribute
     * @return float
     */
    private function maxAttribute(array $items, string $attribute): float
    {
        $max = 0.0;
        foreach ($items as $row) {
            $max = max($max, (float) ($row['attributes'][$attribute] ?? 0));
        }
        return $max;
    }

    /**
     * Numeric comparison by operator.
     *
     * @param float $left
     * @param string $op
     * @param float $right
     * @return bool
     */
    private function compare(float $left, string $op, float $right): bool
    {
        switch ($op) {
            case 'gt':
                return $left > $right;
            case 'gte':
                return $left >= $right;
            case 'lt':
                return $left < $right;
            case 'lte':
                return $left <= $right;
            default:
                return false;
        }
    }
}
