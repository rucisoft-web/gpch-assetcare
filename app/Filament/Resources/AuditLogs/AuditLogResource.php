<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Concerns\HasPermissions;
use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/** Log audit hanya-baca: siapa, kapan, data apa, nilai lama/baru, alasan, request id. */
class AuditLogResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 22;

    protected static ?string $modelLabel = 'Log audit';

    protected static ?string $pluralModelLabel = 'Log audit';

    protected static ?string $navigationLabel = 'Log audit';

    protected static ?string $viewPermission = 'audit.view';

    protected static ?string $editPermission = null;

    public static function form(Schema $schema): Schema
    {
        $json = fn ($v) => new HtmlString('<pre style="white-space:pre-wrap;font-size:.75rem">'.e(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</pre>');

        return $schema->columns(1)->components([
            Section::make()->columns(3)->schema([
                Placeholder::make('when')->label('Waktu')->content(fn ($record) => \App\Support\Fmt::dt($record?->created_at, 'd/m/Y H:i:s')),
                Placeholder::make('who')->label('Pengguna')->content(fn ($record) => $record?->user?->name ?? 'sistem'),
                Placeholder::make('what')->label('Kejadian')->content(fn ($record) => $record?->event.' — '.class_basename((string) $record?->auditable_type).' #'.$record?->auditable_id.' '.$record?->auditable_label),
                Placeholder::make('reason')->label('Alasan')->content(fn ($record) => $record?->reason ?: '—'),
                Placeholder::make('rid')->label('Request ID')->content(fn ($record) => $record?->request_id ?: '—'),
                Placeholder::make('ip')->label('IP')->content(fn ($record) => $record?->ip_address ?: '—'),
                Placeholder::make('old')->label('Nilai lama')->content(fn ($record) => $json($record?->old_values))->columnSpanFull(),
                Placeholder::make('new')->label('Nilai baru')->content(fn ($record) => $json($record?->new_values))->columnSpanFull(),
                Placeholder::make('url')->label('URL')->content(fn ($record) => $record?->url ?: '—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Pengguna')->placeholder('sistem')->searchable(),
                TextColumn::make('event')->label('Kejadian')->badge()->color(fn ($state) => match ($state) { 'dibuat' => 'success', 'dihapus' => 'danger', default => 'warning' }),
                TextColumn::make('auditable_type')->label('Data')->formatStateUsing(fn ($state) => class_basename($state)),
                TextColumn::make('auditable_label')->label('Label')->limit(40)->searchable(),
                TextColumn::make('reason')->label('Alasan')->limit(40)->placeholder('—'),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->options(['dibuat' => 'Dibuat', 'diubah' => 'Diubah', 'dihapus' => 'Dihapus']),
                SelectFilter::make('user_id')->label('Pengguna')->relationship('user', 'name'),
                SelectFilter::make('auditable_type')->label('Data')->options(fn () => AuditLog::query()->distinct()->pluck('auditable_type')->mapWithKeys(fn ($t) => [$t => class_basename($t)])->all()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
