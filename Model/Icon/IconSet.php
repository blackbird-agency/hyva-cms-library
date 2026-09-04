<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model\Icon;

class IconSet
{
    public function __construct(
        protected readonly string $code,
        protected readonly string $label,
        protected readonly string $module,
        protected readonly string $path,
        protected readonly string $valuePrefix,
        protected readonly int $sortOrder = 0,
        protected readonly string $assetPrefix = '',
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return (string) __($this->label);
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getValuePrefix(): string
    {
        return $this->valuePrefix;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getAssetPrefix(): string
    {
        return $this->assetPrefix;
    }

    public function getOverrideRelativePath(): string
    {
        return '/' . $this->module . '/web/svg/' . $this->valuePrefix;
    }
}
