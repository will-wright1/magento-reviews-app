<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\ViewModel;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use ReviewAutomation\Reviews\Model\Config;
class Branding implements ArgumentInterface
{
    public function __construct(private readonly Config $config, private readonly StoreManagerInterface $stores) {}
    public function getName(): string { return $this->config->getBrandName((int) $this->stores->getStore()->getId()); }
    public function getColor(): string { return $this->config->getAccentColor((int) $this->stores->getStore()->getId()); }
    public function getLogoUrl(): string { return $this->config->getLogoUrl((int) $this->stores->getStore()->getId()); }
}
