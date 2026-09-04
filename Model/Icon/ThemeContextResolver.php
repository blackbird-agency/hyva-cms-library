<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model\Icon;

use Hyva\CmsLiveviewEditor\Model\Provider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The only place in the module that knows about the Hyvä liveview editor.
 */
class ThemeContextResolver
{
    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly ScopeConfigInterface $scopeConfig,
        protected readonly ThemeProviderInterface $themeProvider,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly ThemeContextFactory $themeContextFactory,
        protected readonly Provider $liveviewProvider,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function resolve(): ThemeContext
    {
        $themes = [];

        foreach ($this->resolveStoreIds() as $storeId) {
            $themeId = (int) $this->scopeConfig->getValue(
                DesignInterface::XML_PATH_THEME_ID,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            if ($themeId === 0 || isset($themes[$themeId])) {
                continue;
            }

            $theme = $this->loadTheme($themeId);

            if ($theme !== null) {
                $themes[$themeId] = $theme;
            }
        }

        return $this->themeContextFactory->create(['themes' => $themes]);
    }

    /**
     * @return list<int>
     */
    protected function resolveStoreIds(): array
    {
        $fromContent = $this->resolveStoreIdsFromEditedContent();

        return $fromContent !== [] ? $fromContent : $this->getAllActiveStoreIds();
    }

    /**
     * An empty result is the signal to fall back on every active store view, not an error.
     *
     * @return list<int>
     */
    protected function resolveStoreIdsFromEditedContent(): array
    {
        $type = (string) $this->request->getParam('type');
        $entityId = (int) $this->request->getParam('id');

        if ($type === '' || $entityId <= 0) {
            return [];
        }

        // Row shape is not under contract either, so the processing stays inside the try.
        try {
            $rows = $this->liveviewProvider->getStoreContentData($type, $entityId);

            $assigned = \array_filter($rows, static fn (array $row): bool => !empty($row['is_assigned']));
            $rows = $assigned !== [] ? $assigned : $rows;

            $storeIds = \array_map(static fn (array $row): int => (int) ($row['store_id'] ?? 0), $rows);

            return \array_values(\array_unique(\array_filter($storeIds, static fn (int $id): bool => $id > 0)));
        } catch (\Throwable $exception) {
            $this->logger->debug(
                'Icon picker could not resolve the edited content store views: ' . $exception->getMessage()
            );

            return [];
        }
    }

    /**
     * @return list<int>
     */
    protected function getAllActiveStoreIds(): array
    {
        $storeIds = [];

        foreach ($this->storeManager->getStores() as $store) {
            if ((int) $store->getId() > 0 && $store->getIsActive()) {
                $storeIds[] = (int) $store->getId();
            }
        }

        return $storeIds;
    }

    protected function loadTheme(int $themeId): ?ThemeInterface
    {
        try {
            $theme = $this->themeProvider->getThemeById($themeId);
        } catch (\Throwable) {
            return null;
        }

        return $theme->getId() ? $theme : null;
    }
}
