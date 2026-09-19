<?php

namespace App\Actions\Receipts;

use App\Support\FnsQr;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Zxing\QrReader;

class DecodeReceiptQr
{
    public function execute(UploadedFile $photo): string
    {
        $path = $photo->getRealPath();

        if ($path === false) {
            $path = $photo->getPathname();
        }

        $contents = file_get_contents($path);
        $image = is_string($contents) ? imagecreatefromstring($contents) : false;

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([
                'photo' => 'Нужно изображение с QR-кодом.',
            ]);
        }

        $fallback = null;

        foreach ($this->variants($image) as $variant) {
            $text = $this->read($variant);

            if ($text === null) {
                continue;
            }

            if (FnsQr::tryParse($text) !== null) {
                return $text;
            }

            $fallback ??= $text;
        }

        if (is_string($fallback)) {
            return $fallback;
        }

        throw ValidationException::withMessages([
            'photo' => 'На фото не удалось прочитать QR. Снимите код крупнее и без бликов или вставьте строку вручную.',
        ]);
    }

    /**
     * @return list<GdImage>
     */
    private function variants(GdImage $source): array
    {
        $variants = [$source];

        foreach ([1200, 800] as $maxEdge) {
            $fitted = $this->fit($source, $maxEdge);
            $variants[] = $this->enhance($fitted, -40);
            $variants[] = $this->enhance($fitted, -70);
            $variants[] = $this->enhance($this->negate($fitted), -40);
        }

        foreach ($this->regions($source) as $region) {
            $variants[] = $this->enhance($this->fit($region, 900), -40);
        }

        return $variants;
    }

    /**
     * @return list<GdImage>
     */
    private function regions(GdImage $source): array
    {
        return [
            $this->crop($source, 0.18, 0.12, 0.82, 0.58),
            $this->crop($source, 0.18, 0.42, 0.82, 0.92),
            $this->crop($source, 0.25, 0.28, 0.75, 0.68),
        ];
    }

    private function crop(GdImage $source, float $x0, float $y0, float $x1, float $y1): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $left = (int) floor($width * $x0);
        $top = (int) floor($height * $y0);
        $cropWidth = max(1, (int) floor($width * ($x1 - $x0)));
        $cropHeight = max(1, (int) floor($height * ($y1 - $y0)));
        $crop = imagecreatetruecolor($cropWidth, $cropHeight);
        imagecopy($crop, $source, 0, 0, $left, $top, $cropWidth, $cropHeight);

        return $crop;
    }

    private function negate(GdImage $source): GdImage
    {
        $copy = imagecreatetruecolor(imagesx($source), imagesy($source));
        imagecopy($copy, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
        imagefilter($copy, IMG_FILTER_NEGATE);

        return $copy;
    }

    private function fit(GdImage $source, int $maxEdge): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $longest = max($width, $height);

        if ($longest <= $maxEdge) {
            return $source;
        }

        $scale = $maxEdge / $longest;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $fitted = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($fitted, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $fitted;
    }

    private function enhance(GdImage $source, int $contrast): GdImage
    {
        $copy = imagecreatetruecolor(imagesx($source), imagesy($source));
        imagecopy($copy, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
        imagefilter($copy, IMG_FILTER_GRAYSCALE);
        imagefilter($copy, IMG_FILTER_CONTRAST, $contrast);

        return $copy;
    }

    private function read(GdImage $image): ?string
    {
        $reader = new QrReader($image, QrReader::SOURCE_TYPE_RESOURCE, false);
        $text = $reader->text(['TRY_HARDER' => true]);

        if (! is_string($text)) {
            return null;
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
