<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Throwable;

/** Inbox aplikasi (wajib) + email bila SMTP tersedia; kunci deduplikasi & status pengiriman. */
class NotificationService
{
    /**
     * @param  iterable<User>|User  $users
     */
    public function send(iterable|User $users, string $title, ?string $body = null, ?string $url = null, ?string $dedupeKey = null, string $color = 'info'): bool
    {
        $users = collect($users instanceof User ? [$users] : $users)->filter()->unique('id')->values();
        if ($users->isEmpty()) {
            return false;
        }
        if ($dedupeKey) {
            try {
                NotificationLog::create(['dedupe_key' => mb_substr($dedupeKey, 0, 191), 'recipients' => $users->count(), 'status' => 'queued']);
            } catch (QueryException) {
                return false; // sudah pernah dikirim
            }
        }
        try {
            $n = Notification::make()->title($title)->body($body)->color($color)->icon(match ($color) {
                'danger' => 'heroicon-o-exclamation-triangle', 'warning' => 'heroicon-o-clock', 'success' => 'heroicon-o-check-circle', default => 'heroicon-o-bell',
            });
            if ($url) {
                $n->actions([Action::make('buka')->label('Buka')->url($url)->markAsRead()]);
            }
            $n->sendToDatabase($users);
            if (config('assetcare.notify_mail')) {
                $this->mail($users, $title, $body, $url);
            }
            if ($dedupeKey) {
                NotificationLog::where('dedupe_key', mb_substr($dedupeKey, 0, 191))->update(['status' => 'sent']);
            }

            return true;
        } catch (Throwable $e) {
            if ($dedupeKey) {
                NotificationLog::where('dedupe_key', mb_substr($dedupeKey, 0, 191))->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
            }
            report($e);

            return false;
        }
    }

    protected function mail(Collection $users, string $title, ?string $body, ?string $url): void
    {
        foreach ($users as $u) {
            if (! $u->email) {
                continue;
            }
            \Illuminate\Support\Facades\Mail::raw(trim(($body ?? '')."\n\n".($url ?? '')), fn ($m) => $m->to($u->email)->subject('[Asset Care] '.$title));
        }
    }

    public function usersWith(string $permission): Collection
    {
        return User::withPermission($permission);
    }
}
