<?php

namespace App\Http\Controllers;

use App\Support\Brand;
use Illuminate\Support\Facades\Storage;

/** Menyajikan logo & favicon perusahaan yang diunggah (disimpan privat, tanpa perlu storage:link di cPanel). */
class BrandAssetController extends Controller
{
    public function show(string $type)
    {
        $key = ['logo' => 'logo_path', 'logo-dark' => 'logo_dark_path', 'favicon' => 'favicon_path'][$type] ?? abort(404);
        $path = Brand::get($key);
        $disk = Storage::disk('local');
        abort_unless($path && str_starts_with($path, 'branding/') && $disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
