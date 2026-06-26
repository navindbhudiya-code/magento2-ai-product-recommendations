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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Driver\File;
use NavinDBhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use NavinDBhudiya\ProductRecommendation\Service\Metrics\BaselineCalculator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Phase 0 baseline: measure current recommendation quality (hit-rate@k) and latency
 * over the hand-curated ground-truth pairs, and dump the result to evidence JSON.
 *
 * Read-only: it queries recommendations and writes a single JSON file. It never runs
 * Warden or mutates the catalog/index.
 */
class Baseline extends Command
{
    private const OPTION_K = 'k';
    private const OPTION_STORE = 'store';
    private const OPTION_OUTPUT = 'output';

    private const MODULE_NAME = 'NavinDBhudiya_ProductRecommendation';
    private const FIXTURE_REL = 'dev/demo/fixtures/known_pairs.json';
    private const OUTPUT_REL = 'dev/demo/evidence/phase0/baseline.json';

    /**
     * @var RecommendationServiceInterface
     */
    private RecommendationServiceInterface $recommendationService;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var ComponentRegistrar
     */
    private ComponentRegistrar $componentRegistrar;

    /**
     * @var BaselineCalculator
     */
    private BaselineCalculator $calculator;

    /**
     * @var File
     */
    private File $fileDriver;

    /**
     * @param RecommendationServiceInterface $recommendationService
     * @param ProductRepositoryInterface $productRepository
     * @param ComponentRegistrar $componentRegistrar
     * @param BaselineCalculator $calculator
     * @param File $fileDriver
     * @param string|null $name
     */
    public function __construct(
        RecommendationServiceInterface $recommendationService,
        ProductRepositoryInterface $productRepository,
        ComponentRegistrar $componentRegistrar,
        BaselineCalculator $calculator,
        File $fileDriver,
        ?string $name = null
    ) {
        $this->recommendationService = $recommendationService;
        $this->productRepository = $productRepository;
        $this->componentRegistrar = $componentRegistrar;
        $this->calculator = $calculator;
        $this->fileDriver = $fileDriver;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:demo:baseline')
            ->setDescription(
                'Measure baseline recommendation quality (hit-rate@k) and latency over the '
                . 'ground-truth pairs and dump evidence/phase0/baseline.json.'
            )
            ->addOption(self::OPTION_K, 'k', InputOption::VALUE_OPTIONAL, 'Top-k cut-off for hit-rate', '10')
            ->addOption(self::OPTION_STORE, 's', InputOption::VALUE_OPTIONAL, 'Store ID to query in')
            ->addOption(self::OPTION_OUTPUT, 'o', InputOption::VALUE_OPTIONAL, 'Override output JSON path');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $k = max(1, (int) $input->getOption(self::OPTION_K));
        $storeOption = $input->getOption(self::OPTION_STORE);
        $storeId = $storeOption !== null ? (int) $storeOption : null;

        $modulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::MODULE_NAME);
        $fixturePath = $modulePath . '/' . self::FIXTURE_REL;

        try {
            $fixture = json_decode($this->fileDriver->fileGetContents($fixturePath), true);
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Could not read fixtures: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }

        $pairs = $fixture['pairs'] ?? null;
        if (!is_array($pairs) || $pairs === []) {
            $output->writeln('<error>No pairs found in ' . self::FIXTURE_REL . '</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln(sprintf('<info>Evaluating %d ground-truth pairs (hit-rate@%d)...</info>', count($pairs), $k));

        $rows = [];
        $perPair = [];
        $unresolved = [];

        foreach ($pairs as $pair) {
            $anchorSku = (string) ($pair['anchor_sku'] ?? '');
            $expectedSku = (string) ($pair['expected_sku'] ?? '');
            $row = ['anchor_sku' => $anchorSku, 'expected_sku' => $expectedSku, 'resolved' => false];

            $anchor = $this->resolveProduct($anchorSku, $storeId);
            $expected = $this->resolveProduct($expectedSku, $storeId);
            if ($anchor === null || $expected === null) {
                if ($anchor === null) {
                    $unresolved[] = $anchorSku;
                }
                if ($expected === null) {
                    $unresolved[] = $expectedSku;
                }
                $perPair[] = $row;
                $rows[] = ['resolved' => false];
                continue;
            }

            $start = microtime(true);
            $topSkus = [];
            $error = null;
            try {
                $recommendations = $this->recommendationService->getRelatedProducts($anchor, $k, $storeId);
                foreach ($recommendations as $product) {
                    $topSkus[] = (string) $product->getSku();
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            $hit = in_array($expectedSku, $topSkus, true);

            $rows[] = ['resolved' => true, 'hit' => $hit, 'latency_ms' => $latencyMs];
            $row['resolved'] = true;
            $row['hit'] = $hit;
            $row['latency_ms'] = $latencyMs;
            $row['top_skus'] = $topSkus;
            if ($error !== null) {
                $row['error'] = $error;
            }
            $perPair[] = $row;
        }

        $summary = $this->calculator->summarize($rows, $k);
        $document = array_merge(
            [
                'catalog' => $fixture['_catalog'] ?? 'Magento Luma sample data',
                'metric' => 'hit-rate@' . $k,
                'slot' => 'related',
                'store_id' => $storeId,
            ],
            $summary,
            [
                'unresolved_skus' => array_values(array_unique($unresolved)),
                'per_pair' => $perPair,
            ]
        );

        $outputPath = $input->getOption(self::OPTION_OUTPUT) ?: ($modulePath . '/' . self::OUTPUT_REL);
        $this->writeJson($outputPath, $document);

        $this->renderSummary($output, $summary, $unresolved, $outputPath, $k);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Resolve a product by SKU, returning null if it does not exist in the catalog.
     *
     * @param string $sku
     * @param int|null $storeId
     * @return \Magento\Catalog\Api\Data\ProductInterface|null
     */
    private function resolveProduct(string $sku, ?int $storeId)
    {
        if ($sku === '') {
            return null;
        }
        try {
            return $this->productRepository->get($sku, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Write the baseline document as pretty JSON, creating the directory if needed.
     *
     * @param string $path
     * @param array $document
     * @return void
     */
    private function writeJson(string $path, array $document): void
    {
        $dir = $this->fileDriver->getParentDirectory($path);
        if (!$this->fileDriver->isDirectory($dir)) {
            $this->fileDriver->createDirectory($dir);
        }
        $this->fileDriver->filePutContents(
            $path,
            json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    /**
     * Print a human-readable summary table.
     *
     * @param OutputInterface $output
     * @param array $summary
     * @param string[] $unresolved
     * @param string $outputPath
     * @param int $k
     * @return void
     */
    private function renderSummary(
        OutputInterface $output,
        array $summary,
        array $unresolved,
        string $outputPath,
        int $k
    ): void {
        $table = new Table($output);
        $table->setHeaders(['Metric', 'Value']);
        $table->addRow(['hit_rate_at_' . $k, $summary['hit_rate_at_' . $k]]);
        $table->addRow(['p50_latency_ms', $summary['p50_latency_ms']]);
        $table->addRow(['p95_latency_ms', $summary['p95_latency_ms']]);
        $table->addRow(['pairs_evaluated', $summary['pairs_evaluated'] . ' / ' . $summary['pairs_total']]);
        $table->addRow(['hits / misses', $summary['hits'] . ' / ' . $summary['misses']]);
        $table->render();

        if ($unresolved !== []) {
            $output->writeln(sprintf(
                '<comment>Unresolved SKUs (not counted): %s</comment>',
                implode(', ', array_values(array_unique($unresolved)))
            ));
        }
        $output->writeln(sprintf('<info>Baseline written to: %s</info>', $outputPath));
    }
}
