<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Controller\Adminhtml\Icon;

use Blackbird\HyvaCmsLibrary\Model\IconProvider;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Listing extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Blackbird_HyvaCmsLibrary::icon_picker';

    public function __construct(
        Context $context,
        protected readonly IconProvider $iconProvider,
        protected readonly JsonFactory $jsonFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        return $this->jsonFactory->create()->setData(['icons' => $this->iconProvider->getIcons()]);
    }
}
