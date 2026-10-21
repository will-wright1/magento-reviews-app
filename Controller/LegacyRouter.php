<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Controller;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use ReviewAutomation\Reviews\Model\TokenLookup;
/** Preserve links in already-sent emails after an explicit legacy import. */
class LegacyRouter implements RouterInterface
{
    public function __construct(private readonly TokenLookup $tokens) {}
    public function match(RequestInterface $request): ?ActionInterface
    {
        $path = $request->getPathInfo();
        if (preg_match('#^/(vanimal_reviews|fourbyfourat_reviews)/#', $path)
            && preg_match('#/token/([a-f0-9]{64})(?:/|$)#i', $path, $matches)
            && $this->tokens->loadByToken($matches[1], true)) {
            $request->setPathInfo((string) preg_replace('#^/(vanimal_reviews|fourbyfourat_reviews)/#', '/reviewautomation_reviews/', $path));
        }
        // Standard router now resolves the rewritten path; unimported tokens remain with the legacy module.
        return null;
    }
}
