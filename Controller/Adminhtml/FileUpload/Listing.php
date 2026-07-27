<?php

declare(strict_types=1);

namespace Blackbird\Controller\Adminhtml\FileUpload;

use Blackbird\Model\Config\Source\FileUploader;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Listing extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Blackbird_HyvaCmsLibrary::file_upload';

    public function __construct(
        Context $context,
        private readonly FileUploader $fileUploader,
        private readonly JsonFactory $jsonFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        $extensions = \array_values(\array_filter(
            \array_map('trim', \explode(',', (string) $this->getRequest()->getParam('extensions', '')))
        ));

        $result->setData(['files' => $this->fileUploader->listFiles($extensions)]);

        return $result;
    }
}
