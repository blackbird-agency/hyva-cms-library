<?php

declare(strict_types=1);

namespace Blackbird\HyvaCmsLibrary\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class VideoSource implements ArgumentInterface
{
    const DESKTOP_BREAKPOINT = 1024;

    /**
     * @return array{url: string, type: string, poster: string}
     */
    public function extractVideoData(mixed $video): array
    {
        if (!\is_array($video)) {
            return ['url' => '', 'type' => '', 'poster' => ''];
        }

        $extension = (string)($video['extension'] ?? '');

        return [
            'url'    => (string)($video['url'] ?? ''),
            'type'   => $extension ? "video/{$extension}" : '',
            'poster' => (string)($video['poster_url'] ?? ''),
        ];
    }
}
