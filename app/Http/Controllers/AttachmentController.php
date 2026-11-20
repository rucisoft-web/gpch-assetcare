<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Lampiran privat: hanya bagi pengguna yang boleh melihat objek pemiliknya. */
class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment)
    {
        $u = $request->user();
        $owner = $attachment->attachable;
        $allowed = match (true) {
            $owner instanceof Asset => $u->hasPermission('asset.view'),
            $owner instanceof WorkOrder => WorkOrder::visibleTo($u)->whereKey($owner->id)->exists() && $u->hasPermission('wo.view'),
            $owner instanceof ServiceRequest => ServiceRequest::visibleTo($u)->whereKey($owner->id)->exists(),
            $owner instanceof \App\Models\AssetDocument => $owner->canBeSeenBy($u),
            default => false,
        };
        if ($attachment->visibility === 'financial' && ! $u->canSeeFinancials()) {
            $allowed = false;
        }
        abort_unless($allowed, 403);
        if ($owner instanceof \App\Models\AssetDocument) {
            $owner->writeAudit('berkas dibuka', [], ['berkas' => $attachment->original_name, 'versi' => $attachment->file_version]);
        }
        $disk = Storage::disk($attachment->disk ?: 'local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name ?: basename($attachment->path), [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0',
        ], 'inline');
    }

    /** Foto utama aset (kolom photo_path), untuk kartu aset. */
    public function assetPhoto(Request $request, Asset $asset)
    {
        abort_unless($request->user()->hasPermission('asset.view'), 403);
        $path = $asset->photo_path ?: $asset->attachments()->where('kind', 'photo')->latest('id')->value('path');
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Content-Type' => $disk->mimeType($path) ?: 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ], 'inline');
    }
}
