<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model;

use Blackbird\HyvaCmsLibrary\Model\Icon\IconSet;
use Blackbird\HyvaCmsLibrary\Model\Icon\SetRegistry;
use Blackbird\HyvaCmsLibrary\Model\Icon\ThemeContext;
use Blackbird\HyvaCmsLibrary\Model\Icon\ThemeContextResolver;
use Hyva\CmsLiveviewEditor\Model\Cache\Type\HyvaCms;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Locale\ResolverInterface as LocaleResolverInterface;
use Magento\Framework\Module\Dir;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The module's own bundled icons are intentionally not enumerated, only still resolvable.
 *
 * @phpstan-type IconFlag array{type: string, message: string}
 * @phpstan-type IconEntry array{value: string, name: string, label: string, group: string, svg: string, flags: list<IconFlag>}
 */
class IconProvider implements ArgumentInterface
{
    public const GROUP_LIBRARY = 'library';
    public const GROUP_THEME = 'theme';

    public const FLAG_MISSING = 'missing';
    public const FLAG_DIVERGENT = 'divergent';

    protected const CACHE_KEY_PREFIX = 'blackbird_hyva_cms_library_icons';
    /** Bump whenever the shape of an IconEntry changes, or warm caches serve the old shape. */
    protected const CACHE_SCHEMA_VERSION = 'v3';
    protected const CACHE_LIFETIME = 86400;

    protected const LIBRARY_MODULE = 'Blackbird_HyvaCmsLibrary';
    protected const LIBRARY_SVG_PATH = '/view/frontend/web/svg';
    protected const THEME_SVG_PATH = '/web/svg';

    /** @var array<string, list<IconEntry>> */
    protected array $loadedGroups = [];

    protected ?ThemeContext $themeContext = null;

    public function __construct(
        protected readonly Dir $moduleDir,
        protected readonly File $fileDriver,
        protected readonly HyvaCms $cache,
        protected readonly SerializerInterface $serializer,
        protected readonly ComponentRegistrarInterface $componentRegistrar,
        protected readonly SetRegistry $setRegistry,
        protected readonly IconPickerConfig $config,
        protected readonly ThemeContextResolver $themeContextResolver,
        protected readonly LocaleResolverInterface $localeResolver,
    ) {
    }

    /**
     * @return list<IconEntry>
     */
    public function getIcons(): array
    {
        $icons = [];

        foreach ($this->getEnabledGroupCodes() as $code) {
            $icons = [...$icons, ...$this->loadGroup($code)];
        }

        return $icons;
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function getGroups(): array
    {
        $groups = [['code' => self::GROUP_THEME, 'label' => (string) __('Theme icons')]];

        foreach ($this->getEnabledSets() as $set) {
            $groups[] = ['code' => $set->getCode(), 'label' => $set->getLabel()];
        }

        return $groups;
    }

    /**
     * @return IconEntry|null
     */
    public function findIcon(string $value): ?array
    {
        $value = \trim($value);

        if ($value === '') {
            return null;
        }

        $group = $this->resolveGroupForValue($value);

        if ($group !== null) {
            foreach ($this->loadGroup($group) as $icon) {
                if ($icon['value'] === $value) {
                    return $icon;
                }
            }

            return null;
        }

        return $this->findLegacyIcon($value);
    }

    /**
     * @return list<IconEntry>
     */
    public function getLibraryIcons(): array
    {
        return $this->collectFromDirectory(
            $this->getModuleSvgPath(self::LIBRARY_MODULE, self::LIBRARY_SVG_PATH),
            self::GROUP_LIBRARY,
            self::GROUP_LIBRARY
        );
    }

    /**
     * @return list<string>
     */
    protected function getEnabledGroupCodes(): array
    {
        return [
            self::GROUP_THEME,
            ...\array_map(static fn (IconSet $set): string => $set->getCode(), $this->getEnabledSets()),
        ];
    }

    /**
     * @return list<IconSet>
     */
    protected function getEnabledSets(): array
    {
        return $this->setRegistry->getByCodes($this->config->getEnabledSetCodes());
    }

    protected function resolveGroupForValue(string $value): ?string
    {
        if (\str_starts_with($value, self::GROUP_THEME . '/')) {
            return self::GROUP_THEME;
        }

        return $this->setRegistry->findByValue($value)?->getCode();
    }

    /**
     * @return IconEntry|null
     */
    protected function findLegacyIcon(string $value): ?array
    {
        $name = \str_starts_with($value, self::GROUP_LIBRARY . '/')
            ? \substr($value, \strlen(self::GROUP_LIBRARY) + 1)
            : $value;

        foreach ($this->getLibraryIcons() as $icon) {
            if ($icon['name'] === $name) {
                $icon['value'] = $value;

                return $icon;
            }
        }

        return null;
    }

    /**
     * @return list<IconEntry>
     */
    protected function loadGroup(string $code): array
    {
        if (isset($this->loadedGroups[$code])) {
            return $this->loadedGroups[$code];
        }

        $cacheKey = $this->getCacheKey($code);
        $cached = $this->cache->load($cacheKey);

        // HyvaCms::load() already unserializes; a second pass would fail on an array.
        if (\is_array($cached)) {
            return $this->loadedGroups[$code] = $cached;
        }

        $icons = $code === self::GROUP_THEME
            ? $this->collectThemeIcons()
            : $this->collectSetIcons($code);

        $this->cache->save(
            $this->serializer->serialize($icons),
            $cacheKey,
            [HyvaCms::CACHE_TAG],
            self::CACHE_LIFETIME
        );

        return $this->loadedGroups[$code] = $icons;
    }

    protected function getCacheKey(string $code): string
    {
        return \implode('_', [
            self::CACHE_KEY_PREFIX,
            self::CACHE_SCHEMA_VERSION,
            $code,
            $this->getThemeContext()->getHash(),
            \str_replace('-', '_', $this->localeResolver->getLocale()),
        ]);
    }

    protected function getThemeContext(): ThemeContext
    {
        return $this->themeContext ??= $this->themeContextResolver->resolve();
    }

    /**
     * @return list<IconEntry>
     */
    protected function collectSetIcons(string $code): array
    {
        $set = $this->setRegistry->get($code);

        if ($set === null) {
            return [];
        }

        $icons = $this->collectFromDirectory(
            $this->getModuleSvgPath($set->getModule(), $set->getPath()),
            $set->getCode(),
            $set->getValuePrefix()
        );

        return $this->applyThemeOverrides($icons, $set);
    }

    /**
     * @param list<IconEntry> $icons
     * @return list<IconEntry>
     */
    protected function applyThemeOverrides(array $icons, IconSet $set): array
    {
        $context = $this->getThemeContext();

        if ($icons === [] || $context->isEmpty()) {
            return $icons;
        }

        $overridesByTheme = [];
        foreach (\array_keys($context->getThemes()) as $themeId) {
            $overridesByTheme[$themeId] = $this->collectThemeFiles($themeId, $set->getOverrideRelativePath());
        }

        $totalThemes = $context->count();

        foreach ($icons as $index => $icon) {
            $overridingIds = \array_keys(\array_filter(
                $overridesByTheme,
                static fn (array $files): bool => isset($files[$icon['name']])
            ));

            if ($overridingIds === []) {
                continue;
            }

            $artworkByTheme = $this->readArtwork(\array_combine(
                $overridingIds,
                \array_map(
                    static fn (int $themeId): string => $overridesByTheme[$themeId][$icon['name']],
                    $overridingIds
                )
            ));

            $icons[$index]['svg'] = \reset($artworkByTheme);

            if (\count($overridingIds) < $totalThemes) {
                $icons[$index]['flags'][] = [
                    'type' => self::FLAG_DIVERGENT,
                    'message' => (string) __(
                        'Only the theme %1 replaces it. Other store views show the native %2 icon.',
                        $this->labels($context, $overridingIds),
                        $set->getLabel()
                    ),
                ];
            } elseif ($this->hasDivergentArtwork($artworkByTheme)) {
                $icons[$index]['flags'][] = [
                    'type' => self::FLAG_DIVERGENT,
                    'message' => $this->divergentArtworkMessage($context, $overridingIds[0]),
                ];
            }
        }

        return $icons;
    }

    /**
     * getInheritedThemes() returns ancestors first, so a child theme's icon wins over its parent.
     *
     * @return list<IconEntry>
     */
    protected function collectThemeIcons(): array
    {
        $context = $this->getThemeContext();

        if ($context->isEmpty()) {
            return [];
        }

        $filesByName = [];
        foreach (\array_keys($context->getThemes()) as $themeId) {
            foreach ($this->collectThemeFiles($themeId, self::THEME_SVG_PATH) as $name => $file) {
                $filesByName[$name][$themeId] = $file;
            }
        }

        $totalThemes = $context->count();
        $icons = [];

        foreach ($filesByName as $name => $filesByTheme) {
            $artworkByTheme = $this->readArtwork($filesByTheme);

            $icon = $this->buildEntry(
                (string) $name,
                self::GROUP_THEME,
                self::GROUP_THEME,
                \reset($artworkByTheme)
            );

            if (\count($filesByTheme) < $totalThemes) {
                $missingIds = \array_values(
                    \array_diff(\array_keys($context->getThemes()), \array_keys($filesByTheme))
                );

                $icon['flags'][] = [
                    'type' => self::FLAG_MISSING,
                    'message' => (string) __(
                        'Missing from the theme %1. Nothing will render on its store views.',
                        $this->labels($context, $missingIds)
                    ),
                ];
            }

            if ($this->hasDivergentArtwork($artworkByTheme)) {
                $icon['flags'][] = [
                    'type' => self::FLAG_DIVERGENT,
                    'message' => $this->divergentArtworkMessage($context, (int) \array_key_first($filesByTheme)),
                ];
            }

            $icons[] = $icon;
        }

        \usort($icons, static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']));

        return $icons;
    }

    /**
     * Every candidate is read, not just the winning one: divergence is invisible to a presence check.
     *
     * @param array<int, string> $filesByTheme Theme id => absolute file path
     * @return array<int, string> Theme id => file contents
     */
    protected function readArtwork(array $filesByTheme): array
    {
        return \array_map(
            fn (string $file): string => $this->fileDriver->fileGetContents($file),
            $filesByTheme
        );
    }

    /**
     * @param array<int, string> $artworkByTheme
     */
    protected function hasDivergentArtwork(array $artworkByTheme): bool
    {
        return \count(\array_unique($artworkByTheme)) > 1;
    }

    protected function divergentArtworkMessage(ThemeContext $context, int $shownThemeId): string
    {
        return (string) __(
            'The artwork shown here comes from %1. Other themes use a different drawing.',
            $context->getLabel($shownThemeId)
        );
    }

    /**
     * @param list<int> $themeIds
     */
    protected function labels(ThemeContext $context, array $themeIds): string
    {
        return \implode(', ', \array_map(
            static fn (int $themeId): string => $context->getLabel($themeId),
            $themeIds
        ));
    }

    /**
     * @return array<string, string> Icon name => absolute file path
     */
    protected function collectThemeFiles(int $themeId, string $relativePath): array
    {
        $theme = $this->getThemeContext()->getThemes()[$themeId] ?? null;

        if ($theme === null) {
            return [];
        }

        $files = [];
        foreach ($theme->getInheritedThemes() as $inheritedTheme) {
            $themePath = $this->componentRegistrar->getPath(
                ComponentRegistrar::THEME,
                (string) $inheritedTheme->getFullPath()
            );

            if (!$themePath) {
                continue;
            }

            $directory = $themePath . $relativePath;

            if (!$this->fileDriver->isDirectory($directory)) {
                continue;
            }

            foreach ($this->fileDriver->search('*.svg', $directory) ?: [] as $file) {
                $files[\pathinfo($file, PATHINFO_FILENAME)] = $file;
            }
        }

        return $files;
    }

    /**
     * @return list<IconEntry>
     */
    protected function collectFromDirectory(string $directory, string $group, string $valuePrefix): array
    {
        if ($directory === '' || !$this->fileDriver->isDirectory($directory)) {
            return [];
        }

        $icons = [];
        foreach ($this->fileDriver->search('*.svg', $directory) ?: [] as $file) {
            $icons[] = $this->buildEntry(
                \pathinfo($file, PATHINFO_FILENAME),
                $group,
                $valuePrefix,
                $this->fileDriver->fileGetContents($file)
            );
        }

        \usort($icons, static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']));

        return $icons;
    }

    /**
     * @return IconEntry
     */
    protected function buildEntry(string $name, string $group, string $valuePrefix, string $svg): array
    {
        return [
            'value' => $valuePrefix . '/' . $name,
            'name' => $name,
            'label' => \ucwords(\str_replace(['_', '-'], ' ', $name)),
            'group' => $group,
            'svg' => $svg,
            'flags' => [],
        ];
    }

    protected function getModuleSvgPath(string $moduleName, string $relativePath): string
    {
        try {
            return $this->moduleDir->getDir($moduleName) . $relativePath;
        } catch (\Throwable) {
            return '';
        }
    }
}
