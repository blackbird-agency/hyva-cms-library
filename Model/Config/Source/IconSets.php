<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model\Config\Source;

use Blackbird\HyvaCmsLibrary\Model\Icon\IconSet;
use Blackbird\HyvaCmsLibrary\Model\Icon\SetRegistry;
use Magento\Framework\Data\OptionSourceInterface;

class IconSets implements OptionSourceInterface
{
    public function __construct(
        protected readonly SetRegistry $setRegistry,
    ) {
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return \array_values(\array_map(
            static fn (IconSet $set): array => [
                'value' => $set->getCode(),
                'label' => $set->getLabel(),
            ],
            $this->setRegistry->getAll()
        ));
    }
}
