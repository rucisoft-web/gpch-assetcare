<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\Attachment;
use App\Services\ServiceRequestService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Laporkan kerusakan';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $sr = app(ServiceRequestService::class)->create($data, auth()->user(), $data['client_event_uuid'] ?? null);
        if ($sr->wasRecentlyCreated) {
            $disk = Storage::disk('local');
            foreach ((array) ($data['photos'] ?? []) as $path) {
                Attachment::create([
                    'attachable_type' => $sr::class, 'attachable_id' => $sr->id, 'kind' => 'before', 'disk' => 'local', 'path' => $path,
                    'original_name' => basename($path), 'mime' => $disk->mimeType($path), 'size' => $disk->size($path),
                    'checksum' => hash('sha256', $disk->get($path)), 'uploaded_by' => auth()->id(),
                ]);
            }
        }

        return $sr;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Laporan '.$this->record->number.' diterima ('.$this->record->priority.'). Engineering telah diberi tahu.';
    }

    protected function getRedirectUrl(): string
    {
        return ServiceRequestResource::getUrl('view', ['record' => $this->record]);
    }
}
