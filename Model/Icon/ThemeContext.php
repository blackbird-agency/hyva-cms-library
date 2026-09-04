<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model\Icon;

use Magento\Framework\DataObject;
use Magento\Framework\View\Design\ThemeInterface;

class ThemeContext
{
    /**
     * @param array<int, ThemeInterface> $themes Theme id => theme
     */
    public function __construct(
        protected readonly array $themes = [],
    ) {
    }

    /**
     * @return array<int, ThemeInterface>
     */
    public function getThemes(): array
    {
        return $this->themes;
    }

    public function isEmpty(): bool
    {
        return $this->themes === [];
    }

    public function count(): int
    {
        return \count($this->themes);
    }

    /**
     * Stable across request order, so two requests on the same stores share one cache entry.
     */
    public function getHash(): string
    {
        $ids = \array_keys($this->themes);
        \sort($ids);

        return \substr(\hash('md5', \implode(',', $ids)), 0, 12);
    }

    public function getLabel(int $themeId): string
    {
        $theme = $this->themes[$themeId] ?? null;

        if ($theme === null) {
            return (string) $themeId;
        }

        // theme_title lives on the concrete model, not on ThemeInterface.
        $title = $theme instanceof DataObject ? (string) $theme->getData('theme_title') : '';

        return $title !== '' ? $title : (string) ($theme->getCode() ?: $themeId);
    }
}
