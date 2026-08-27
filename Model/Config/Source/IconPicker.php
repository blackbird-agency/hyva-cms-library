<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model\Config\Source;

use Blackbird\HyvaCmsLibrary\Model\IconProvider;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * @deprecated Use IconProvider directly, which also exposes theme and native icons.
 * @see IconProvider
 */
class IconPicker implements ArgumentInterface
{
    public function __construct(
        protected readonly IconProvider $iconProvider,
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string, svg: string}>
     */
    public function toOptionArray(): array
    {
        return \array_map(
            static fn (array $icon): array => [
                'value' => $icon['name'],
                'label' => $icon['label'],
                'svg' => $icon['svg'],
            ],
            $this->iconProvider->getLibraryIcons()
        );
    }
}
