<?php

declare(strict_types=1);

namespace Blackbird\Model\Config\Source;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class IconPicker implements ArgumentInterface
{
    private const string MODULE_NAME = 'Blackbird_HyvaCmsLibrary';

    /** @var array<int, array{value: string, label: string, svg: string}>|null */
    private ?array $options = null;

    public function __construct(
        private readonly Dir $moduleDir,
        private readonly File $fileDriver,
    ) {}

    /**
     * @return array<int, array{value: string, label: string, svg: string}>
     * @throws FileSystemException
     */
    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $imagesPath = $this->moduleDir->getDir(self::MODULE_NAME) . '/view/frontend/web/svg';

        $this->options = [];
        foreach ($this->fileDriver->search('*.svg', $imagesPath) ?: [] as $svgFile) {
            $value = \pathinfo($svgFile, PATHINFO_FILENAME);
            $this->options[] = [
                'value' => $value,
                'label' => \ucwords(\str_replace('_', ' ', $value)),
                'svg'   => $this->fileDriver->fileGetContents($svgFile),
            ];
        }

        return $this->options;
    }
}
