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
 * Compiles a constrained natural-language merchandising rule into a deterministic structure
 * (pure). Supported shapes (case-insensitive):
 *
 *   boost high-margin items when basket > 5000
 *   boost low-stock items
 *   bury out-of-stock items
 *   boost items over 100 (price)
 *   filter items under 50 (price)
 *
 * Output is a plain array consumed by RuleApplier; arbitrary prose is rejected (valid=false)
 * rather than guessed, so behaviour is predictable.
 */
class RuleCompiler
{
    /**
     * Words that map to a numeric product attribute.
     */
    private const ATTRIBUTE_SYNONYMS = [
        'margin' => 'margin',
        'price' => 'price',
        'priced' => 'price',
        'stock' => 'stock',
        'inventory' => 'stock',
    ];

    /**
     * Compile a rule string.
     *
     * @param string $rule
     * @return array
     */
    public function compile(string $rule): array
    {
        $normalized = strtolower(trim($rule));
        $result = [
            'action' => null,
            'item' => null,
            'context' => null,
            'raw' => $rule,
            'valid' => false,
        ];

        if (!preg_match('/^(boost|bury|filter)\s+(.+)$/', $normalized, $m)) {
            return $result;
        }
        $action = $m[1];
        $remainder = $m[2];

        // Split off an optional "when <condition>" context clause.
        $context = null;
        if (preg_match('/^(.*?)\s+when\s+(.+)$/', $remainder, $w)) {
            $remainder = trim($w[1]);
            $context = $this->parseCondition($w[2], 'basket_total');
            if ($context === null) {
                return $result;
            }
        }

        $item = $this->parseTarget($remainder);
        if ($item === null) {
            return $result;
        }

        $result['action'] = $action;
        $result['item'] = $item;
        $result['context'] = $context;
        $result['valid'] = true;
        return $result;
    }

    /**
     * Parse the item target: a high/low keyword, a threshold, or an out-of-stock flag.
     *
     * @param string $text
     * @return array|null
     */
    private function parseTarget(string $text): ?array
    {
        $text = trim($text);

        // "out-of-stock items" / "out of stock items"
        if (preg_match('/out[\s-]of[\s-]stock/', $text)) {
            return ['attribute' => 'stock', 'direction' => null, 'op' => 'lte', 'threshold' => 0.0];
        }

        // "high-margin items" / "low-stock items"
        if (preg_match('/(high|low)[\s-]([a-z]+)\s+items?/', $text, $m)) {
            $attribute = self::ATTRIBUTE_SYNONYMS[$m[2]] ?? null;
            if ($attribute === null) {
                return null;
            }
            return ['attribute' => $attribute, 'direction' => $m[1], 'op' => null, 'threshold' => null];
        }

        // "items over 100" / "items under 50" / "<attr> over 100"
        if (preg_match('/(?:([a-z]+)\s+)?items?\s+(over|above|under|below|>|<)\s+([0-9]+(?:\.[0-9]+)?)/', $text, $m)) {
            $attribute = $m[1] !== '' ? (self::ATTRIBUTE_SYNONYMS[$m[1]] ?? 'price') : 'price';
            $op = in_array($m[2], ['over', 'above', '>'], true) ? 'gt' : 'lt';
            return ['attribute' => $attribute, 'direction' => null, 'op' => $op, 'threshold' => (float) $m[3]];
        }

        return null;
    }

    /**
     * Parse a "<field> <op> <number>" condition.
     *
     * @param string $text
     * @param string $defaultField
     * @return array|null
     */
    private function parseCondition(string $text, string $defaultField): ?array
    {
        if (!preg_match('/([a-z_]+)?\s*(over|above|under|below|>|<|>=|<=)\s*([0-9]+(?:\.[0-9]+)?)/', trim($text), $m)) {
            return null;
        }
        $field = ($m[1] ?? '') !== '' ? $m[1] : $defaultField;
        if ($field === 'basket' || $field === 'cart') {
            $field = 'basket_total';
        }
        $opMap = ['over' => 'gt', 'above' => 'gt', '>' => 'gt', '>=' => 'gte',
                  'under' => 'lt', 'below' => 'lt', '<' => 'lt', '<=' => 'lte'];
        return ['field' => $field, 'op' => $opMap[$m[2]], 'value' => (float) $m[3]];
    }
}
