<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\Model\Config\Source;

use Blackbird\HyvaCmsLibrary\Model\FFMpegProvider;
use FFMpeg\Coordinate\TimeCode;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class FileUploader implements ArgumentInterface
{
    private const string FILES_SUBDIR   = 'hyva_cms/files';
    private const string POSTERS_SUBDIR = 'hyva_cms/posters';

    /** @var array<string, string> extension => MIME type */
    private const array MIME_TYPE_MAP = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'mp4'  => 'video/mp4',
        'mov'  => 'video/quicktime',
        'avi'  => 'video/x-msvideo',
        'webm' => 'video/webm',
        'mkv'  => 'video/x-matroska',
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'zip'  => 'application/zip',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
    ];

    /** @var string[] */
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'webm', 'mkv'];

    /** @var string[] */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

    public function __construct(
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager,
        private readonly IoFile $ioFile,
        private readonly LoggerInterface $logger,
        private readonly FFMpegProvider $ffmpegProvider,
    ) {}

    /**
     * @param string   $fileInputName    Key in $_FILES (passed from controller)
     * @param string   $tmpPath          Temporary file path from $_FILES
     * @param string   $originalName     Original file name from $_FILES
     * @param string[] $allowedExtensions
     * @return array{url: string, filename: string, extension: string, poster_url: string|null}
     * @throws LocalizedException
     */
    public function upload(
        string $fileInputName,
        string $tmpPath,
        string $originalName,
        array $allowedExtensions
    ): array {
        $extension = \strtolower($this->ioFile->getPathInfo($originalName)['extension'] ?? '');

        $this->validateMimeType($tmpPath, $extension, $allowedExtensions);

        $uploader = $this->uploaderFactory->create(['fileId' => $fileInputName]);
        $uploader->setAllowedExtensions($allowedExtensions);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $destPath = $mediaDir->getAbsolutePath(self::FILES_SUBDIR);
        $result   = $uploader->save($destPath);

        $savedFilename = (string) $result['file'];
        $url           = $this->buildMediaUrl(self::FILES_SUBDIR . '/' . $savedFilename);

        $posterUrl = null;
        if (\in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            $posterUrl = $this->generatePoster($destPath . '/' . $savedFilename);
        } elseif (\in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            $posterUrl = $url;
        }

        return [
            'url'        => $url,
            'filename'   => $savedFilename,
            'extension'  => $extension,
            'poster_url' => $posterUrl,
        ];
    }

    /**
     * @param string   $tmpPath
     * @param string   $extension
     * @param string[] $allowedExtensions
     * @throws LocalizedException
     */
    public function validateMimeType(string $tmpPath, string $extension, array $allowedExtensions): void
    {
        if (!\in_array($extension, $allowedExtensions, true)) {
            throw new LocalizedException(__('File extension "%1" is not allowed.', $extension));
        }

        if (!isset(self::MIME_TYPE_MAP[$extension])) {
            throw new LocalizedException(__('Unknown extension "%1".', $extension));
        }

        $finfo      = new \finfo(\FILEINFO_MIME_TYPE);
        $actualMime = (string) $finfo->file($tmpPath);

        if ($actualMime !== self::MIME_TYPE_MAP[$extension]) {
            throw new LocalizedException(
                __('File MIME type "%1" does not match expected type for extension "%2".', $actualMime, $extension)
            );
        }
    }

    public function generatePoster(string $videoAbsPath): ?string
    {
        $ffmpeg = $this->ffmpegProvider->create();

        if ($ffmpeg === null) {
            return null;
        }

        try {
            $video = $ffmpeg->open($videoAbsPath);
            $frame  = $video->frame(TimeCode::fromSeconds(1));

            $mediaDir      = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $postersAbsDir = $mediaDir->getAbsolutePath(self::POSTERS_SUBDIR);

            $mediaDir->create(self::POSTERS_SUBDIR);

            $posterFilename = $this->ioFile->getPathInfo($videoAbsPath)['filename'] . '.jpg';
            $posterAbsPath  = $postersAbsDir . '/' . $posterFilename;

            $frame->save($posterAbsPath);

            return $this->buildMediaUrl(self::POSTERS_SUBDIR . '/' . $posterFilename);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    /**
     * @param  string[] $allowedExtensions
     * @return array<int, array{url: string, filename: string, extension: string, poster_url: string|null}>
     */
    public function listFiles(array $allowedExtensions): array
    {
        $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

        if (!$mediaDir->isDirectory(self::FILES_SUBDIR)) {
            return [];
        }

        $files = [];
        foreach ($mediaDir->read(self::FILES_SUBDIR) as $relativePath) {
            $pathInfo  = $this->ioFile->getPathInfo($relativePath);
            $filename  = $pathInfo['basename'];
            $extension = \strtolower($pathInfo['extension'] ?? '');

            if (!\in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            $posterUrl = null;
            if (\in_array($extension, self::VIDEO_EXTENSIONS, true)) {
                $videoBaseName = $pathInfo['filename'];
                foreach (self::IMAGE_EXTENSIONS as $imgExt) {
                    $posterRelPath = self::POSTERS_SUBDIR . '/' . $videoBaseName . '.' . $imgExt;
                    if ($mediaDir->isFile($posterRelPath)) {
                        $posterUrl = $this->buildMediaUrl($posterRelPath);
                        break;
                    }
                }
            } elseif (\in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                $posterUrl = $this->buildMediaUrl(self::FILES_SUBDIR . '/' . $filename);
            }

            $files[] = [
                'url'        => $this->buildMediaUrl(self::FILES_SUBDIR . '/' . $filename),
                'filename'   => $filename,
                'extension'  => $extension,
                'poster_url' => $posterUrl,
            ];
        }

        return $files;
    }

    private function buildMediaUrl(string $relativePath): string
    {
        $baseUrl = \rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB),
            '/'
        );

        return $baseUrl . '/media/' . \ltrim($relativePath, '/');
    }
}
