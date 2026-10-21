<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Console\Command;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\MaintenanceMode;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use ReviewAutomation\Reviews\Model\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
/** Copies legacy IDs and tokens into empty target tables, leaving source tables intact. */
class ImportLegacy extends Command
{
    public function __construct(private readonly ResourceConnection $resource, private readonly MaintenanceMode $maintenance,
        private readonly ScopeConfigInterface $scopeConfig, private readonly StoreManagerInterface $stores,
        private readonly Config $config) { parent::__construct(); }
    protected function configure(): void
    {
        $this->setName('reviewautomation:import-legacy')
            ->setDescription('Preview or import legacy review data into an empty installation.')
            ->addArgument('source', InputArgument::REQUIRED, 'vanimal or fourbyfourat')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Write after preflight; requires maintenance and disabled sending.');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = (string) $input->getArgument('source');
        if (!in_array($source, ['vanimal', 'fourbyfourat'], true)) {
            $output->writeln('<error>Unknown source.</error>'); return 1;
        }
        $db = $this->resource->getConnection();
        $pairs = [];
        try {
            foreach (['booster_batch', 'queue', 'feedback', 'unsubscribe'] as $suffix) {
                $from = $this->resource->getTableName($source . '_review_' . $suffix);
                $to = $this->resource->getTableName('reviewautomation_review_' . $suffix);
                if (!$db->isTableExists($to)) { throw new \RuntimeException('Run setup:upgrade for the new module first.'); }
                if ((int) $db->fetchOne($db->select()->from($to, ['COUNT(*)'])) > 0) {
                    throw new \RuntimeException('Destination must be empty: ' . $to);
                }
                if (!$db->isTableExists($from)) {
                    if ($suffix === 'booster_batch') { continue; }
                    throw new \RuntimeException('Missing legacy table: ' . $from);
                }
                $columns = array_values(array_intersect(array_keys($db->describeTable($to)), array_keys($db->describeTable($from))));
                if (in_array($suffix, ['queue', 'feedback'], true)) {
                    $key = $suffix === 'queue' ? 'order_id' : 'queue_id';
                    $duplicate = $db->fetchOne($db->select()->from($from, [$key])->group($key)->having('COUNT(*) > 1')->limit(1));
                    if ($duplicate !== false) { throw new \RuntimeException('Duplicate ' . $key . ' in ' . $from . '; reconcile before import.'); }
                }
                $count = (int) $db->fetchOne($db->select()->from($from, ['COUNT(*)']));
                $output->writeln($suffix . ': ' . $count . ' rows');
                $pairs[] = [$from, $to, $columns];
            }
            if (!$input->getOption('execute')) { $output->writeln('Preview only. No data changed.'); return 0; }
            if (!$this->maintenance->isOn()) { throw new \RuntimeException('Enable maintenance mode and stop cron before importing.'); }
            foreach (array_merge([null], array_keys($this->stores->getStores())) as $store) {
                if ($this->config->isEnabled($store === null ? null : (int) $store)
                    || $this->scopeConfig->isSetFlag($source . '_review_automation/general/enabled', ScopeInterface::SCOPE_STORE, $store)) {
                    throw new \RuntimeException('Disable legacy and new sending at every config scope first.');
                }
            }
            $db->beginTransaction();
            try {
                foreach ($pairs as [$from, $to, $columns]) {
                    $db->query($db->insertFromSelect($db->select()->from($from, $columns), $to, $columns));
                    if (str_ends_with($to, '_booster_batch')) {
                        $db->update($to, ['status' => 'paused'], ['status = ?' => 'active']);
                    }
                }
                $db->commit();
            } catch (\Throwable $e) { $db->rollBack(); throw $e; }
            $output->writeln('Imported. Source tables and configuration were not changed. Booster batches are paused.');
            return 0;
        } catch (\Throwable $e) { $output->writeln('<error>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</error>'); return 1; }
    }
}
