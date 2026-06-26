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
use NavinDBhudiya\ProductRecommendation\Service\Health\HealthChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `recommendation:health` — pings the embedding provider + vector store and prints index
 * coverage. Exits non-zero when overall status is red so it is CI/monitoring friendly.
 */
class Health extends Command
{
    private const OPTION_JSON = 'json';

    /**
     * @var HealthChecker
     */
    private HealthChecker $healthChecker;

    /**
     * @param HealthChecker $healthChecker
     * @param string|null $name
     */
    public function __construct(HealthChecker $healthChecker, ?string $name = null)
    {
        $this->healthChecker = $healthChecker;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:health')
            ->setDescription('Check embedding provider + vector store reachability and index coverage.')
            ->addOption(self::OPTION_JSON, 'j', InputOption::VALUE_NONE, 'Output the raw JSON report');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->healthChecker->gather();

        if ($input->getOption(self::OPTION_JSON)) {
            $output->writeln((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $this->exitCode($report['status']);
        }

        $coverage = $report['coverage'];
        $table = new Table($output);
        $table->setHeaders(['Check', 'Value']);
        $table->addRow(['Overall status', strtoupper((string) $report['status'])]);
        $table->addRow([
            'Embedding provider',
            $report['embedding_provider']['name'] . ' — ' . $this->yesNo($report['embedding_provider']['available']),
        ]);
        $table->addRow([
            'Vector store',
            $report['vector_store']['name'] . ' — ' . $this->yesNo($report['vector_store']['reachable']),
        ]);
        $table->addRow([
            'Index coverage',
            sprintf('%d / %d (%.1f%%)', $coverage['indexed'], $coverage['total'], $coverage['percent']),
        ]);
        $table->render();

        return $this->exitCode($report['status']);
    }

    /**
     * Map status to a process exit code (red -> failure).
     *
     * @param string $status
     * @return int
     */
    private function exitCode(string $status): int
    {
        return $status === HealthChecker::STATUS_RED ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }

    /**
     * Render a reachability flag as a short label.
     *
     * @param bool $value
     * @return string
     */
    private function yesNo(bool $value): string
    {
        return $value ? 'OK' : 'UNREACHABLE';
    }
}
