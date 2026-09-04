<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model\Icon;

/**
 * @phpstan-type SetDefinition array{label: string, module: string, path: string, value_prefix: string, sort_order?: int|string, asset_prefix?: string}
 */
class SetRegistry
{
    /** @var array<string, IconSet>|null */
    protected ?array $sets = null;

    /**
     * @param array<string, SetDefinition> $setDefinitions
     */
    public function __construct(
        protected readonly IconSetFactory $iconSetFactory,
        protected readonly array $setDefinitions = [],
    ) {
    }

    /**
     * @return array<string, IconSet>
     */
    public function getAll(): array
    {
        if ($this->sets !== null) {
            return $this->sets;
        }

        $sets = [];
        foreach ($this->setDefinitions as $code => $definition) {
            $code = (string) $code;

            if (!isset($definition['label'], $definition['module'], $definition['path'], $definition['value_prefix'])) {
                continue;
            }

            if (!\preg_match('/^[a-zA-Z0-9_]+$/', $code)) {
                continue;
            }

            $sets[$code] = $this->iconSetFactory->create([
                'code' => $code,
                'label' => (string) $definition['label'],
                'module' => (string) $definition['module'],
                'path' => (string) $definition['path'],
                'valuePrefix' => \trim((string) $definition['value_prefix'], '/'),
                'sortOrder' => (int) ($definition['sort_order'] ?? 0),
                'assetPrefix' => (string) ($definition['asset_prefix'] ?? ''),
            ]);
        }

        \uasort(
            $sets,
            static fn (IconSet $a, IconSet $b): int => [$a->getSortOrder(), $a->getCode()]
                <=> [$b->getSortOrder(), $b->getCode()]
        );

        return $this->sets = $sets;
    }

    public function get(string $code): ?IconSet
    {
        return $this->getAll()[$code] ?? null;
    }

    /**
     * @param list<string> $codes
     * @return list<IconSet>
     */
    public function getByCodes(array $codes): array
    {
        $wanted = \array_flip($codes);

        return \array_values(
            \array_filter($this->getAll(), static fn (IconSet $set): bool => isset($wanted[$set->getCode()]))
        );
    }

    /**
     * Longest prefix wins, so "heroicons/solid" is matched before a hypothetical "heroicons".
     */
    public function findByValue(string $value): ?IconSet
    {
        $candidates = $this->getAll();
        \uasort(
            $candidates,
            static fn (IconSet $a, IconSet $b): int => \strlen($b->getValuePrefix()) <=> \strlen($a->getValuePrefix())
        );

        foreach ($candidates as $set) {
            if (\str_starts_with($value, $set->getValuePrefix() . '/')) {
                return $set;
            }
        }

        return null;
    }
}
