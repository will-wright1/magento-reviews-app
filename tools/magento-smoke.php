<?php
/** Read-only runtime check. Run against a disposable, installed Magento instance after setup:upgrade. */
declare(strict_types=1);
$magento = realpath($argv[1] ?? '');
if (!$magento || !is_file($magento . '/app/bootstrap.php')) { fwrite(STDERR, "Usage: php tools/magento-smoke.php /path/to/magento\n"); exit(1); }
require $magento . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create($magento, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');
foreach ([
    \ReviewAutomation\Reviews\Model\Config::class,
    \ReviewAutomation\Reviews\Model\Email\SendDispatcher::class,
    \ReviewAutomation\Reviews\Cron\PopulateQueue::class,
    \ReviewAutomation\Reviews\Cron\SendEmails::class,
    \ReviewAutomation\Reviews\Cron\FeedBoosterBatches::class,
    \ReviewAutomation\Reviews\Controller\Preview\Index::class,
    \ReviewAutomation\Reviews\Controller\Response\Click::class,
    \ReviewAutomation\Reviews\Controller\Response\Submit::class,
    \ReviewAutomation\Reviews\Controller\Response\Feedbackpost::class,
    \ReviewAutomation\Reviews\Controller\Unsubscribe\Save::class,
    \ReviewAutomation\Reviews\Block\FeedbackForm::class,
    \ReviewAutomation\Reviews\Block\Thankyou::class,
    \ReviewAutomation\Reviews\Console\Command\ImportLegacy::class,
] as $class) {
    $om->create($class);
    echo "OK dependency construction: $class\n";
}
$resource=$om->get(\Magento\Framework\App\ResourceConnection::class);
foreach (['queue','feedback','unsubscribe','booster_batch'] as $suffix) {
    $table=$resource->getTableName('reviewautomation_review_'.$suffix);
    if (!$resource->getConnection()->isTableExists($table)) { throw new \RuntimeException('Missing '.$table); }
    echo "OK table: $table\n";
}
echo "Runtime smoke check passed. No emails sent or review records changed.\n";
