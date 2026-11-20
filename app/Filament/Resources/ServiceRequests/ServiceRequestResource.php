<?php

namespace App\Filament\Resources\ServiceRequests;

use App\Filament\Concerns\HasPermissions;
use App\Filament\RelationManagers\AttachmentsRelationManager;
use App\Filament\RelationManagers\CommentsRelationManager;
use App\Filament\RelationManagers\StatusHistoryRelationManager;
use App\Filament\Support\F;
use App\Models\Asset;
use App\Models\ServiceRequest;
use App\Services\ServiceRequestService;
use App\Support\Fmt;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** M04 Laporan kerusakan (service request): cepat dari ponsel, SLA respons mulai saat server menerima. */
class ServiceRequestResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = ServiceRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Pemeliharaan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Laporan kerusakan';

    protected static ?string $pluralModelLabel = 'Laporan kerusakan';

    protected static ?string $navigationLabel = 'Laporan kerusakan';

    protected static ?string $viewPermission = 'request.create';

    protected static ?string $editPermission = 'request.create';

    protected static ?string $recordTitleAttribute = 'number';

    public static function canViewAny(): bool
    {
        return (bool) static::user()?->hasAnyPermission(['request.create', 'request.view_all', 'request.triage']);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && ServiceRequest::visibleTo(static::user())->whereKey($record->getKey())->exists();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ServiceRequest::where('status', 'submitted')->count();

        return static::user()?->hasPermission('request.triage') && $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return ServiceRequest::where('status', 'submitted')->where('priority', 'P1')->exists() ? 'danger' : 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Hidden::make('client_event_uuid')->default(fn () => (string) Str::uuid()),
            Section::make('Apa yang rusak?')->columns(2)->schema([
                F::asset('asset_id', 'Aset (scan QR atau cari kode / nama / kamar)')->columnSpanFull()->live()
                    ->default(fn () => request()->integer('asset') ?: null)
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state && ($a = Asset::find($state))) {
                            $set('location_id', $a->location_id);
                        }
                    }),
                Placeholder::make('asset_info')->hiddenLabel()->columnSpanFull()
                    ->visible(fn (Get $get) => filled($get('asset_id')))
                    ->content(function (Get $get) {
                        $a = Asset::with('location')->find($get('asset_id'));
                        if (! $a) {
                            return null;
                        }
                        $open = $a->workOrders()->whereIn('status', \App\Models\WorkOrder::OPEN)->first();

                        return new HtmlString('<div class="ac-scan-msg">'.e($a->asset_code.' · '.$a->name).' — '.e($a->location?->full_path ?? '-')
                            .($open ? '<br><b>Sudah ada work order terbuka '.e($open->number).'</b> — laporan Anda tetap dicatat dan dapat ditautkan.' : '').'</div>');
                    }),
                F::location('location_id', 'Lokasi (wajib bila aset tidak diketahui)')->required(fn (Get $get) => blank($get('asset_id'))),
                Select::make('category')->label('Kategori')->options(ServiceRequest::CATEGORIES)->required()->native(false),
                Textarea::make('symptom')->label('Gejala / keluhan')->required()->rows(3)->maxLength(2000)->columnSpanFull()
                    ->placeholder('Contoh: AC tidak dingin, ada suara keras, air menetes dari unit indoor.'),
            ]),
            Section::make('Dampak')->columns(2)->schema([
                Select::make('impact')->label('Dampak operasional')->options(ServiceRequest::IMPACTS)->native(false)->live(),
                Toggle::make('is_safety')->label('Ada bahaya keselamatan (listrik terbuka, kebocoran gas, alarm)')->live(),
                Placeholder::make('suggested')->label('Prioritas yang disarankan')
                    ->content(function (Get $get) {
                        $p = ServiceRequestService::suggestPriority($get('impact'), (bool) $get('is_safety'));

                        return new HtmlString('<span class="ac-prio ac-'.$p.'">'.e(ServiceRequest::PRIORITIES[$p]).'</span>'
                            .($p === 'P1' ? '<div class="ac-scan-msg err" style="margin-top:.5rem">Darurat: amankan area & <b>telepon Engineering/Security segera</b>. Aplikasi bukan satu-satunya kanal untuk keadaan darurat.</div>' : ''));
                    }),
                Toggle::make('room_block_proposed')->label('Usulkan kamar tidak dijual sementara')->helperText('Keputusan tetap oleh Front Office.'),
                TextInput::make('reporter_contact')->label('Kontak pelapor')->default(fn () => auth()->user()?->phone),
            ]),
            Section::make('Foto (opsional)')->schema([
                F::upload('photos', 'Foto kerusakan', 'attachments/'.now()->format('Y/m'), multiple: true)->dehydrated(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Nomor')->searchable()->sortable()->weight('bold'),
                F::priorityColumn(),
                F::statusColumn('status', ServiceRequest::STATUSES, ServiceRequest::STATUS_COLORS),
                TextColumn::make('category')->label('Kategori')->formatStateUsing(fn ($state) => ServiceRequest::CATEGORIES[$state] ?? $state)->toggleable(),
                TextColumn::make('symptom')->label('Gejala')->limit(60)->wrap()->searchable(),
                TextColumn::make('location.full_path')->label('Lokasi')->wrap()->toggleable(),
                TextColumn::make('asset.asset_code')->label('Aset')->placeholder('—')->searchable()->toggleable(),
                TextColumn::make('received_at')->label('Diterima')->dateTime('d M H:i')->sortable(),
                TextColumn::make('response_due_at')->label('Batas respons')->dateTime('d M H:i')
                    ->color(fn ($record) => $record->responseOverdue() ? 'danger' : null)
                    ->description(fn ($record) => $record->response_at ? 'direspons '.Fmt::dt($record->response_at, 'H:i') : ($record->responseOverdue() ? 'terlambat' : null)),
                TextColumn::make('reporter.name')->label('Pelapor')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('priority')->label('Prioritas')->options(ServiceRequest::PRIORITIES),
                SelectFilter::make('category')->label('Kategori')->options(ServiceRequest::CATEGORIES),
                SelectFilter::make('status')->options(ServiceRequest::STATUSES)->multiple(),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('received_at', 'desc')
            ->poll('60s');
    }

    public static function getRelations(): array
    {
        return [StatusHistoryRelationManager::class, AttachmentsRelationManager::class, CommentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceRequests::route('/'),
            'create' => Pages\CreateServiceRequest::route('/create'),
            'view' => Pages\ViewServiceRequest::route('/{record}'),
        ];
    }
}
