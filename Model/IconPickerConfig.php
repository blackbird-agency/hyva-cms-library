<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class IconPickerConfig
{
    public const XML_PATH_ICON_SETS = 'hyva_cms/icon_picker/icon_sets';

    public function __construct(
        protected readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getEnabledSetCodes(): array
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_ICON_SETS);

        return \array_values(\array_filter(\array_map('trim', \explode(',', $value))));
    }
}
