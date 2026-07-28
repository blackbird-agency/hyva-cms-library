<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Controller\Adminhtml\FileUpload;

use Blackbird\HyvaCmsLibrary\Model\Config\Source\FileUploader;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class Upload extends Action implements HttpPostActionInterface
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

        try {
            $authorizedExtensions = \array_values(\array_filter(
                \array_map('trim', \explode(',', (string)$this->getRequest()->getParam('authorized_extensions', '')))
            ));

            if (empty($authorizedExtensions)) {
                throw new LocalizedException(__('No authorized extensions provided.'));
            }
            $fileData = (array)$this->getRequest()->getFiles('file');
            $data = $this->fileUploader->upload(
                'file',
                (string)($fileData['tmp_name'] ?? ''),
                (string)($fileData['name'] ?? ''),
                $authorizedExtensions
            );

            $result->setData($data);
        } catch (LocalizedException $e) {
            $result->setHttpResponseCode(400);
            $result->setData(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $result->setHttpResponseCode(500);
            $result->setData(['error' => __('An error occurred while uploading the file.')->render()]);
        }

        return $result;
    }
}
