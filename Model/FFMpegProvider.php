<?php

declare(strict_types=1);

namespace Blackbird\Model;

use FFMpeg\FFMpeg;
use Symfony\Component\Process\ExecutableFinder;
use const Blackbird\HyvaCmsLibrary\Model\BP;

class FFMpegProvider
{
    private const string FFMPEG_BIN  = 'ffmpeg';
    private const string FFPROBE_BIN = 'ffprobe';

    public function __construct(
        private readonly ExecutableFinder $executableFinder,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->resolveBin(self::FFMPEG_BIN) !== null
            && $this->resolveBin(self::FFPROBE_BIN) !== null;
    }

    public function create(): ?FFMpeg
    {
        $ffmpegBin  = $this->resolveBin(self::FFMPEG_BIN);
        $ffprobeBin = $this->resolveBin(self::FFPROBE_BIN);

        if ($ffmpegBin === null || $ffprobeBin === null) {
            return null;
        }

        return FFMpeg::create([
            'ffmpeg.binaries'  => $ffmpegBin,
            'ffprobe.binaries' => $ffprobeBin,
        ]);
    }

    private function resolveBin(string $name): ?string
    {
        $local = BP . '/bin/' . $name;
        $info  = new \SplFileInfo($local);

        if ($info->isFile() && $info->isExecutable()) {
            return $local;
        }

        return $this->executableFinder->find($name);
    }
}
