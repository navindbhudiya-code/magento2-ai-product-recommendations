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

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Console\Cli;
use Magento\Framework\Stdlib\DateTime\DateTime;
use NavinDBhudiya\ProductRecommendation\Model\ResourceModel\Event as EventResource;
use NavinDBhudiya\ProductRecommendation\Service\Analytics\SeedPlanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `recommendation:demo:seed-events` — generate a reproducible synthetic event stream across the
 * catalog so the dashboard/A-B has a story. Every row is stamped is_demo=1. Same --seed => same
 * data (the planner is deterministic).
 */
class SeedEvents extends Command
{
    private const OPTION_SEED = 'seed';
    private const OPTION_DAYS = 'days';
    private const OPTION_STORE = 'store';
    private const OPTION_PRODUCTS = 'products';
    private const INSERT_CHUNK = 500;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var SeedPlanner
     */
    private SeedPlanner $seedPlanner;

    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param SeedPlanner $seedPlanner
     * @param EventResource $eventResource
     * @param DateTime $dateTime
     * @param string|null $name
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        SeedPlanner $seedPlanner,
        EventResource $eventResource,
        DateTime $dateTime,
        ?string $name = null
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->seedPlanner = $seedPlanner;
        $this->eventResource = $eventResource;
        $this->dateTime = $dateTime;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:demo:seed-events')
            ->setDescription('Generate a reproducible synthetic recommendation event stream (is_demo=1).')
            ->addOption(self::OPTION_SEED, null, InputOption::VALUE_OPTIONAL, 'Deterministic seed', '42')
            ->addOption(self::OPTION_DAYS, null, InputOption::VALUE_OPTIONAL, 'Days of history', '30')
            ->addOption(self::OPTION_STORE, 's', InputOption::VALUE_OPTIONAL, 'Store ID', '1')
            ->addOption(self::OPTION_PRODUCTS, 'p', InputOption::VALUE_OPTIONAL, 'Max products to span', '60');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $seed = (int) $input->getOption(self::OPTION_SEED);
        $days = max(1, (int) $input->getOption(self::OPTION_DAYS));
        $storeId = (int) $input->getOption(self::OPTION_STORE);
        $limit = max(1, (int) $input->getOption(self::OPTION_PRODUCTS));

        $catalog = $this->loadCatalog($limit);
        if ($catalog === []) {
            $output->writeln('<error>No products found to seed against.</error>');
            return Cli::RETURN_FAILURE;
        }

        $endDate = $this->dateTime->gmtDate('Y-m-d');
        $events = $this->seedPlanner->generate($seed, $days, $catalog, $endDate, $storeId);

        $inserted = 0;
        foreach (array_chunk($events, self::INSERT_CHUNK) as $chunk) {
            $inserted += $this->eventResource->insertBatch($chunk);
        }

        $output->writeln(sprintf(
            '<info>Seeded %d synthetic events (seed=%d, days=%d, products=%d). All rows is_demo=1.</info>',
            $inserted,
            $seed,
            $days,
            count($catalog)
        ));
        $output->writeln('<comment>Run the roll-up cron (or recommendation:ab:report) to see results.</comment>');

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Load up to $limit product ids => skus (most popular first by entity id ascending).
     *
     * @param int $limit
     * @return array
     */
    private function loadCatalog(int $limit): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('sku')->setPageSize($limit)->setCurPage(1);

        $catalog = [];
        foreach ($collection->getItems() as $product) {
            $catalog[(int) $product->getId()] = (string) $product->getSku();
        }
        return $catalog;
    }
}
