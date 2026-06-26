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

namespace NavinDBhudiya\ProductRecommendation\Console\Command;

use Magento\Framework\Console\Cli;
use NavinDBhudiya\ProductRecommendation\Model\ResourceModel\Event as EventResource;
use NavinDBhudiya\ProductRecommendation\Service\Analytics\LiftCalculator;
use NavinDBhudiya\ProductRecommendation\Service\Analytics\StatsAggregator;
use NavinDBhudiya\ProductRecommendation\Service\Analytics\VariantBucketer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `recommendation:ab:report` — print the AI-vs-control A/B comparison with lift % and a
 * significance flag over a date range. Surfaces a "Demo data" note when synthetic rows exist.
 */
class AbReport extends Command
{
    private const OPTION_FROM = 'from';
    private const OPTION_TO = 'to';
    private const OPTION_STORE = 'store';

    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var StatsAggregator
     */
    private StatsAggregator $aggregator;

    /**
     * @var LiftCalculator
     */
    private LiftCalculator $liftCalculator;

    /**
     * @param EventResource $eventResource
     * @param StatsAggregator $aggregator
     * @param LiftCalculator $liftCalculator
     * @param string|null $name
     */
    public function __construct(
        EventResource $eventResource,
        StatsAggregator $aggregator,
        LiftCalculator $liftCalculator,
        ?string $name = null
    ) {
        $this->eventResource = $eventResource;
        $this->aggregator = $aggregator;
        $this->liftCalculator = $liftCalculator;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:ab:report')
            ->setDescription('Show AI vs control A/B lift over a date range.')
            ->addOption(self::OPTION_FROM, null, InputOption::VALUE_OPTIONAL, 'From date (Y-m-d)')
            ->addOption(self::OPTION_TO, null, InputOption::VALUE_OPTIONAL, 'To date (Y-m-d)')
            ->addOption(self::OPTION_STORE, 's', InputOption::VALUE_OPTIONAL, 'Store ID');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = $input->getOption(self::OPTION_FROM);
        $to = $input->getOption(self::OPTION_TO);
        $storeOption = $input->getOption(self::OPTION_STORE);
        $storeId = $storeOption !== null ? (int) $storeOption : null;

        $rawEvents = $this->eventResource->fetchForAggregation($from, $to, $storeId);
        $rows = $this->aggregator->aggregate($rawEvents);

        $totals = $this->totalsByVariant($rows);
        $result = $this->liftCalculator->compute(
            $totals[VariantBucketer::VARIANT_AI],
            $totals[VariantBucketer::VARIANT_CONTROL]
        );

        $this->render($output, $result);

        if ($this->eventResource->hasDemoRows()) {
            $output->writeln('<comment>Demo data present: some rows are synthetic (is_demo=1). '
                . 'The measurement method is identical to production.</comment>');
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Sum aggregated rows into per-variant totals for the lift calculator.
     *
     * @param array $rows
     * @return array
     */
    private function totalsByVariant(array $rows): array
    {
        $empty = ['impressions' => 0, 'clicks' => 0, 'add_to_carts' => 0, 'purchases' => 0, 'revenue' => 0.0];
        $totals = [
            VariantBucketer::VARIANT_AI => $empty,
            VariantBucketer::VARIANT_CONTROL => $empty,
        ];
        foreach ($rows as $row) {
            $variant = $row['variant'] === VariantBucketer::VARIANT_CONTROL
                ? VariantBucketer::VARIANT_CONTROL
                : VariantBucketer::VARIANT_AI;
            $totals[$variant]['impressions'] += (int) $row['impressions'];
            $totals[$variant]['clicks'] += (int) $row['clicks'];
            $totals[$variant]['add_to_carts'] += (int) $row['add_to_carts'];
            $totals[$variant]['purchases'] += (int) $row['purchases'];
            $totals[$variant]['revenue'] += (float) $row['revenue'];
        }
        return $totals;
    }

    /**
     * Render the comparison table.
     *
     * @param OutputInterface $output
     * @param array $result
     * @return void
     */
    private function render(OutputInterface $output, array $result): void
    {
        $ai = $result['ai'];
        $control = $result['control'];
        $lift = $result['lift'];

        $table = new Table($output);
        $table->setHeaders(['Metric', 'AI', 'Control', 'Lift %']);
        $table->addRow(['Impressions', $ai['impressions'], $control['impressions'], '—']);
        $table->addRow(['Clicks', $ai['clicks'], $control['clicks'], '—']);
        $table->addRow(['CTR', $ai['ctr'], $control['ctr'], $lift['ctr_pct']]);
        $table->addRow(['Add-to-cart rate', $ai['atc_rate'], $control['atc_rate'], $lift['atc_rate_pct']]);
        $table->addRow([
            'Revenue / impression',
            $ai['revenue_per_impression'],
            $control['revenue_per_impression'],
            $lift['revenue_per_impression_pct'],
        ]);
        $table->render();

        $output->writeln($result['significant']
            ? '<info>Result is statistically significant (95% confidence on CTR).</info>'
            : '<comment>Not yet statistically significant — collect more traffic.</comment>');
    }
}
