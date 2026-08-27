<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model;

use Hyva\CmsLiveviewEditor\Model\Cache\Type\HyvaCms;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * The module's own bundled icons are intentionally not enumerated, only still renderable.
 *
 * @phpstan-type IconEntry array{value: string, name: string, label: string, group: string, svg: string}
 */
class IconProvider implements ArgumentInterface
{
    public const GROUP_LIBRARY = 'library';
    public const GROUP_THEME = 'theme';
    public const GROUP_LUCIDE = 'lucide';

    protected const CACHE_KEY_PREFIX = 'blackbird_hyva_cms_library_icons';
    protected const CACHE_LIFETIME = 86400;

    protected const LIBRARY_MODULE = 'Blackbird_HyvaCmsLibrary';
    protected const LIBRARY_SVG_PATH = '/view/frontend/web/svg';
    protected const LUCIDE_MODULE = 'Hyva_Theme';
    protected const LUCIDE_SVG_PATH = '/view/base/web/svg/lucide';
    protected const THEME_SVG_PATH = '/web/svg';
    protected const LUCIDE_OVERRIDE_PATH = '/web/svg/lucide';

    /** @var list<IconEntry>|null */
    protected ?array $icons = null;

    public function __construct(
        protected readonly Dir $moduleDir,
        protected readonly File $fileDriver,
        protected readonly HyvaCms $cache,
        protected readonly SerializerInterface $serializer,
        protected readonly ScopeConfigInterface $scopeConfig,
        protected readonly ThemeProviderInterface $themeProvider,
        protected readonly ComponentRegistrarInterface $componentRegistrar,
        protected readonly ModuleListInterface $moduleList,
    ) {
    }

    /**
     * @return list<IconEntry>
     */
    public function getIcons(): array
    {
        if ($this->icons !== null) {
            return $this->icons;
        }

        $theme = $this->getFrontendTheme();
        $moduleVersion = $this->moduleList->getOne(self::LIBRARY_MODULE)['setup_version'] ?? 'unknown';
        $cacheKey = self::CACHE_KEY_PREFIX . '_' . ($theme?->getId() ?? 'none') . '_' . $moduleVersion;
        $cached = $this->cache->load($cacheKey);

        // HyvaCms::load() already unserializes; a second pass would fail on an array.
        if (\is_array($cached)) {
            $this->icons = $cached;

            return $this->icons;
        }

        [$lucideIcons, $overriddenLucideIcons] = $this->extractThemeOverrides(
            $this->collectLucideIcons(),
            $theme,
            self::LUCIDE_MODULE,
            self::LUCIDE_OVERRIDE_PATH
        );

        $themeIcons = [...$this->collectThemeIcons($theme), ...$overriddenLucideIcons];
        \usort($themeIcons, static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']));

        $this->icons = [...$themeIcons, ...$lucideIcons];

        $this->cache->save(
            $this->serializer->serialize($this->icons),
            $cacheKey,
            [HyvaCms::CACHE_TAG],
            self::CACHE_LIFETIME
        );

        return $this->icons;
    }

    /**
     * @return list<IconEntry>
     */
    public function getIconsByGroup(string $group): array
    {
        return \array_values(
            \array_filter($this->getIcons(), static fn (array $icon): bool => $icon['group'] === $group)
        );
    }

    /**
     * @return list<IconEntry>
     */
    public function getLibraryIcons(): array
    {
        return $this->collectLibraryIcons();
    }

    /**
     * @return list<IconEntry>
     */
    protected function collectLibraryIcons(): array
    {
        return $this->collectFromDirectory(
            $this->getModuleSvgPath(self::LIBRARY_MODULE, self::LIBRARY_SVG_PATH),
            self::GROUP_LIBRARY
        );
    }

    /**
     * @return list<IconEntry>
     */
    protected function collectLucideIcons(): array
    {
        return $this->collectFromDirectory(
            $this->getModuleSvgPath(self::LUCIDE_MODULE, self::LUCIDE_SVG_PATH),
            self::GROUP_LUCIDE
        );
    }

    /**
     * getInheritedThemes() returns ancestors first, so a child theme's icon wins over its parent.
     *
     * @return list<IconEntry>
     */
    protected function collectThemeIcons(?ThemeInterface $theme): array
    {
        if ($theme === null) {
            return [];
        }

        $collected = [];
        foreach ($theme->getInheritedThemes() as $inheritedTheme) {
            $themePath = $this->componentRegistrar->getPath(
                ComponentRegistrar::THEME,
                (string) $inheritedTheme->getFullPath()
            );

            if (!$themePath) {
                continue;
            }

            foreach ($this->collectFromDirectory($themePath . self::THEME_SVG_PATH, self::GROUP_THEME) as $icon) {
                $collected[$icon['name']] = $icon;
            }
        }

        $icons = \array_values($collected);
        \usort($icons, static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']));

        return $icons;
    }

    /**
     * The value stays untouched: "theme/settings" would point at <theme>/web/svg/settings.svg,
     * which does not exist — the override lives under <theme>/Hyva_Theme/web/svg/lucide/.
     *
     * @param list<IconEntry> $icons
     * @return array{0: list<IconEntry>, 1: list<IconEntry>}
     */
    protected function extractThemeOverrides(
        array $icons,
        ?ThemeInterface $theme,
        string $moduleName,
        string $relativePath
    ): array {
        if ($theme === null || $icons === []) {
            return [$icons, []];
        }

        $overrides = [];
        foreach ($theme->getInheritedThemes() as $inheritedTheme) {
            $themePath = $this->componentRegistrar->getPath(
                ComponentRegistrar::THEME,
                (string) $inheritedTheme->getFullPath()
            );

            if (!$themePath) {
                continue;
            }

            $directory = $themePath . '/' . $moduleName . $relativePath;

            if (!$this->fileDriver->isDirectory($directory)) {
                continue;
            }

            foreach ($this->fileDriver->search('*.svg', $directory) ?: [] as $file) {
                $overrides[\pathinfo($file, PATHINFO_FILENAME)] = $this->fileDriver->fileGetContents($file);
            }
        }

        if ($overrides === []) {
            return [$icons, []];
        }

        $kept = [];
        $moved = [];
        foreach ($icons as $icon) {
            if (!isset($overrides[$icon['name']])) {
                $kept[] = $icon;
                continue;
            }

            $icon['svg'] = $overrides[$icon['name']];
            $icon['group'] = self::GROUP_THEME;
            $moved[] = $icon;
        }

        return [$kept, $moved];
    }

    /**
     * @return list<IconEntry>
     */
    protected function collectFromDirectory(string $directory, string $group): array
    {
        if ($directory === '' || !$this->fileDriver->isDirectory($directory)) {
            return [];
        }

        $icons = [];
        foreach ($this->fileDriver->search('*.svg', $directory) ?: [] as $file) {
            $name = \pathinfo($file, PATHINFO_FILENAME);
            $icons[] = [
                'value' => $group . '/' . $name,
                'name' => $name,
                'label' => \ucwords(\str_replace(['_', '-'], ' ', $name)),
                'group' => $group,
                'svg' => $this->fileDriver->fileGetContents($file),
            ];
        }

        \usort($icons, static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']));

        return $icons;
    }

    protected function getModuleSvgPath(string $moduleName, string $relativePath): string
    {
        try {
            return $this->moduleDir->getDir($moduleName) . $relativePath;
        } catch (\Throwable) {
            return '';
        }
    }

    protected function getFrontendTheme(): ?ThemeInterface
    {
        $themeId = $this->scopeConfig->getValue(DesignInterface::XML_PATH_THEME_ID, ScopeInterface::SCOPE_STORE);

        if (!$themeId) {
            return null;
        }

        try {
            $theme = $this->themeProvider->getThemeById((int) $themeId);
        } catch (\Throwable) {
            return null;
        }

        return $theme->getId() ? $theme : null;
    }
}
