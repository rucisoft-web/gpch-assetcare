<?php

namespace App\Support;

/**
 * Ubah foto apa pun (JPG/PNG/WebP/GIF/BMP) menjadi JPEG yang lebih kecil dari batas ukuran (bawaan 500 KB).
 * Urutan: putar sesuai EXIF, perkecil sisi terpanjang ke maks. 1600 px, turunkan kualitas, lalu perkecil lagi bila perlu.
 */
class ImageCompressor
{
    public const MAX_BYTES = 500 * 1024;

    public const MAX_SIDE = 1600;

    /** @return string|null isi JPEG, atau null bila berkas bukan gambar yang bisa dibaca GD */
    public static function toJpeg(string $raw, int $maxBytes = self::MAX_BYTES, int $maxSide = self::MAX_SIDE): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! ($img = @imagecreatefromstring($raw))) {
            return null;
        }
        $img = self::orient($img, $raw);
        // latar putih untuk PNG/WebP transparan (JPEG tidak punya transparansi)
        $w = imagesx($img);
        $h = imagesy($img);
        $flat = imagecreatetruecolor($w, $h);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
        $img = $flat;

        $side = min($maxSide, max($w, $h));
        $out = '';
        for ($round = 0; $round < 8; $round++) {
            $s = $side / max($w, $h);
            $cur = $s < 1 ? imagescale($img, max(1, (int) round($w * $s)), max(1, (int) round($h * $s)), IMG_BICUBIC) : $img;
            foreach ([82, 72, 62, 52] as $q) {
                ob_start();
                imagejpeg($cur, null, $q);
                $out = (string) ob_get_clean();
                if (strlen($out) <= $maxBytes) {
                    return $out;
                }
            }
            $side = (int) round($side * 0.8);
        }

        return $out;
    }

    private static function orient(\GdImage $img, string $raw): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($raw, "\xFF\xD8")) {
            return $img;
        }
        $o = (int) (@exif_read_data('data://image/jpeg;base64,'.base64_encode($raw))['Orientation'] ?? 1);

        return match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }
}
