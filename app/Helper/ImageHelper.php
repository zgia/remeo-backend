<?php

declare(strict_types=1);

namespace App\Helper;

/**
 * 影像附件缩略图，非图片附件不生成缩略图。
 */
class ImageHelper
{
    public static function thumb(string $file, int $width, int $height, string $directory): bool
    {
        $info = @getimagesize($file);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return false;
        }
        $image = @imagecreatefromstring(file_get_contents($file));
        if (! $image) {
            return false;
        }
        $ratio  = min($width / $info[0], $height / $info[1], 1);
        $thumb  = imagescale($image, max(1, (int) ($info[0] * $ratio)), max(1, (int) ($info[1] * $ratio)));
        $target = $directory.pathinfo($file, PATHINFO_FILENAME).'-thumb.'.pathinfo($file, PATHINFO_EXTENSION);

        return match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($thumb, $target), IMAGETYPE_PNG => imagepng($thumb, $target),
            IMAGETYPE_GIF  => imagegif($thumb, $target), IMAGETYPE_WEBP => imagewebp($thumb, $target),
        };
    }
}
