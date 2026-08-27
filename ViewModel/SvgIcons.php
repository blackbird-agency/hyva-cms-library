<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\ViewModel;

use Blackbird\HyvaCmsLibrary\Model\IconProvider;
use Hyva\Theme\ViewModel\SvgIcons as BaseSvgIcons;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset;
use Magento\Framework\View\DesignInterface;

/**
 * A bare legacy value resolves through the parent class: module svg folder, then theme fallback.
 */
class SvgIcons extends BaseSvgIcons
{
    protected const NAMESPACE_SEPARATOR = '/';

    /**
     * @param array<string, BaseSvgIcons> $renderers Namespace => renderer
     */
    public function __construct(
        Asset\Repository $assetRepository,
        CacheInterface $cache,
        DesignInterface $design,
        string $iconPathPrefix = 'Hyva_Theme::svg',
        string $iconSet = '',
        array $pathPrefixMapping = [],
        ?ScopeConfigInterface $scopeConfig = null,
        protected array $renderers = [],
    ) {
        parent::__construct(
            $assetRepository,
            $cache,
            $design,
            $iconPathPrefix,
            $iconSet,
            $pathPrefixMapping,
            $scopeConfig
        );
    }

    public function renderHtml(
        string $icon,
        string $classNames = '',
        ?int $width = 24,
        ?int $height = 24,
        array $attributes = []
    ): string {
        [$namespace, $name] = $this->splitNamespace($icon);

        if ($namespace !== null && isset($this->renderers[$namespace])) {
            return $this->renderers[$namespace]->renderHtml($name, $classNames, $width, $height, $attributes);
        }

        if ($namespace === IconProvider::GROUP_LIBRARY) {
            return parent::renderHtml($name, $classNames, $width, $height, $attributes);
        }

        return parent::renderHtml($icon, $classNames, $width, $height, $attributes);
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    protected function splitNamespace(string $icon): array
    {
        $parts = \explode(self::NAMESPACE_SEPARATOR, $icon, 2);

        return \count($parts) === 2 ? [$parts[0], $parts[1]] : [null, $icon];
    }
}
