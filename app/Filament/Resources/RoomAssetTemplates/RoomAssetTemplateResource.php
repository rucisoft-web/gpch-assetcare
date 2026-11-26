<?php

namespace App\Filament\Resources\RoomAssetTemplates;

use App\Filament\Concerns\HasPermissions;
use App\Filament\Pages\GenerateAssets;
use App\Filament\Support\F;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\RoomAssetTemplate;
use App\Models\RoomType;
use App\Services\RoomTemplateService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Template aset per tipe kamar (bagian 24). Published = terkunci; revisi = versi baru. */
class RoomAssetTemplateResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = RoomAssetTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengendalian Aset';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Template kamar';

    protected static ?string $pluralModelLabel = 'Template aset kamar';

    protected static ?string $navigationLabel = 'Template aset kamar';

    protected static ?string $viewPermission = 'template.manage';

    protected static ?string $editPermission = 'template.manage';

    public static function canViewAny(): bool
    {
        return (bool) static::user()?->hasAnyPermission(['template.manage', 'template.publish', 'generation.run']);
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows('template.manage') && $record->status === 'draft';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Template')->columns(4)->schema([
                TextInput::make('code')->label('Kode')->required()->maxLength(30)->placeholder('TPL-DLX')
                    ->disabled(fn ($record) => (bool) $record),
                TextInput::make('name')->label('Nama')->required()->columnSpan(2),
                Select::make('room_type_id')->label('Tipe kamar')->required()->native(false)->options(fn () => RoomType::orderBy('name')->pluck('name', 'id')),
                Textarea::make('notes')->label('Catatan revisi')->columnSpanFull()->rows(2),
            ]),
            Section::make('Daftar item standar')->schema([
                Repeater::make('items')->relationship()->hiddenLabel()->orderColumn('sort')->reorderable()->collapsible()
                    ->itemLabel(fn (array $state) => trim(($state['quantity'] ?? '').' × '.($state['standard_name'] ?? 'item baru')))
                    ->addActionLabel('Tambah item')->columns(6)
                    ->schema([
                        TextInput::make('item_key')->label('Kunci item')->required()->maxLength(40)->alphaDash()
                            ->helperText('Stabil lintas versi, mis. tv-utama')->columnSpan(2)
                            ->dehydrateStateUsing(fn ($state) => Str::slug($state)),
                        Select::make('category_id')->label('Kategori')->required()->native(false)->searchable()->columnSpan(2)
                            ->options(fn () => AssetCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                        TextInput::make('standard_name')->label('Nama standar')->required()->columnSpan(2),
                        TextInput::make('quantity')->label('Jumlah')->numeric()->minValue(1)->default(1)->required(),
                        Select::make('mode')->label('Mode')->options(['individual' => 'Individual (1 QR per unit)', 'quantity' => 'Kelompok jumlah (1 QR)'])->default('individual')->native(false)->columnSpan(2),
                        TextInput::make('unit')->label('Satuan')->default('unit'),
                        TextInput::make('positions')->label('Label posisi')->placeholder('Kiri, Kanan')->columnSpan(2),
                        Select::make('default_department_id')->label('Departemen')->native(false)->options(fn () => Department::orderBy('name')->pluck('name', 'id'))->columnSpan(2),
                        Select::make('default_criticality')->label('Kritikalitas')->options(Asset::CRITICALITY)->native(false),
                        TextInput::make('pm_interval_days')->label('Usulan PM (hari)')->numeric()->minValue(1),
                        Textarea::make('specification')->label('Spesifikasi')->rows(1)->columnSpan(6),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->weight('bold')->searchable()->sortable(),
                TextColumn::make('version')->label('Versi')->prefix('v'),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('roomType.name')->label('Tipe kamar'),
                F::statusColumn('status', RoomAssetTemplate::STATUSES, ['draft' => 'warning', 'published' => 'success', 'archived' => 'gray']),
                TextColumn::make('items_count')->counts('items')->label('Item'),
                TextColumn::make('preparer.name')->label('Penyusun')->toggleable(),
                TextColumn::make('reviewer.name')->label('Pemeriksa')->placeholder('—')->toggleable(),
                TextColumn::make('published_at')->label('Terbit')->dateTime('d M Y')->placeholder('—'),
            ])
            ->recordActions(static::recordActions())
            // template terbit terkunci: klik baris membuka "Lihat isi", bukan halaman edit (403)
            ->recordUrl(fn (RoomAssetTemplate $record) => static::canEdit($record) ? static::getUrl('edit', ['record' => $record]) : null)
            ->recordAction(fn (RoomAssetTemplate $record) => static::canEdit($record) ? null : 'isi')
            ->defaultSort('code')
            ->groups(['code']);
    }

    public static function recordActions(): array
    {
        $u = fn () => auth()->user();

        return [
            EditAction::make()->visible(fn (RoomAssetTemplate $record) => static::canEdit($record)),
            Action::make('isi')->label('Lihat isi')->icon('heroicon-o-list-bullet')->color('gray')
                ->modalHeading(fn (RoomAssetTemplate $record) => $record->label)
                ->modalSubmitAction(false)->modalCancelActionLabel('Tutup')
                ->modalContent(function (RoomAssetTemplate $record) {
                    $record->load('items.category', 'items.department', 'roomType');
                    $rooms = \App\Models\Location::where('room_type_id', $record->room_type_id)->whereIn('location_type', ['room', 'venue'])->pluck('id');
                    $ex = \App\Models\RoomException::whereIn('room_id', $rooms)->count();
                    $rows = $record->items->map(fn ($i) => '<tr><td>'.e($i->standard_name).'</td><td>'.e($i->category?->name).'</td><td style="text-align:right"><b>'.(int) $i->quantity.'</b> '.e($i->unit).'</td><td>'.e($i->department?->name ?? '—').'</td><td>'.e($i->specification ?? '').'</td></tr>')->join('');

                    return new HtmlString('<div class="arah"><p class="arah-muted">Tipe kamar <b>'.e($record->roomType?->name).'</b> · '.$rooms->count().' kamar · '.$record->unitCount().' unit aset per kamar · '.$ex.' pengecualian per kamar (jumlah berbeda / tidak tersedia / tambahan).</p>'
                        .'<table class="arah-table"><thead><tr><th>Jenis aset tetap</th><th>Kategori</th><th>Jumlah</th><th>Departemen</th><th>Keterangan</th></tr></thead><tbody>'.$rows.'</tbody></table></div>');
                }),
            Action::make('terbitkan')->label('Terbitkan')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (RoomAssetTemplate $record) => $record->status === 'draft' && $u()->hasPermission('template.publish'))
                ->requiresConfirmation()
                ->modalDescription('Setelah terbit, template terkunci. Pemeriksa harus berbeda dari penyusun. Versi published sebelumnya dengan kode sama akan diarsipkan.')
                ->action(fn (RoomAssetTemplate $record) => F::attempt(function () use ($record, $u) {
                    if ((int) $record->prepared_by === (int) $u()->id) {
                        throw \App\Services\WorkflowException::forbidden('Pemeriksa template harus berbeda dari penyusun.');
                    }
                    if ($record->items()->doesntExist()) {
                        throw \App\Services\WorkflowException::invalid('Template belum memiliki item.');
                    }
                    \Illuminate\Support\Facades\DB::transaction(function () use ($record, $u) {
                        RoomAssetTemplate::where('code', $record->code)->where('status', 'published')->whereKeyNot($record->id)->update(['status' => 'archived']);
                        $record->update(['status' => 'published', 'reviewed_by' => $u()->id, 'published_at' => now()]);
                    });
                }, 'Template diterbitkan')),
            Action::make('versi')->label('Buat versi baru')->icon('heroicon-o-document-duplicate')->color('gray')
                ->visible(fn (RoomAssetTemplate $record) => $record->status !== 'draft' && $u()->hasPermission('template.manage')
                    && ! RoomAssetTemplate::where('code', $record->code)->where('status', 'draft')->exists())
                ->requiresConfirmation()->modalDescription('Versi draft baru disalin dari versi ini. Aset aktual tidak berubah sampai diterapkan & diverifikasi.')
                ->action(fn (RoomAssetTemplate $record) => redirect(static::getUrl('edit', ['record' => $record->newVersion()]))),
            Action::make('selisih')->label('Bandingkan')->icon('heroicon-o-arrows-right-left')->color('gray')
                ->visible(fn (RoomAssetTemplate $record) => $record->version > 1)
                ->modalSubmitAction(false)->modalCancelActionLabel('Tutup')
                ->modalContent(function (RoomAssetTemplate $record) {
                    $prev = RoomAssetTemplate::where('code', $record->code)->where('version', '<', $record->version)->orderByDesc('version')->with('items')->first();
                    if (! $prev) {
                        return null;
                    }
                    $rows = app(RoomTemplateService::class)->diff($prev, $record->load('items'))->map(fn ($r) => '<tr><td>'.e($r['item_key']).'</td><td>'.e($r['old']).'</td><td>'.e($r['new']).'</td><td><b>'.e($r['change']).'</b></td></tr>')->join('');

                    return new HtmlString('<div class="arah"><p class="arah-muted">v'.$prev->version.' → v'.$record->version.'. Selisih tidak mengubah aset aktual; kamar yang sudah memakai versi lama tetap tercatat.</p><table class="arah-table"><thead><tr><th>Item</th><th>Lama</th><th>Baru</th><th>Perubahan</th></tr></thead><tbody>'.$rows.'</tbody></table></div>');
                }),
            Action::make('terapkan')->label('Terapkan ke kamar')->icon('heroicon-o-squares-plus')
                ->visible(fn (RoomAssetTemplate $record) => $record->status === 'published' && $u()->hasPermission('generation.run'))
                ->url(fn (RoomAssetTemplate $record) => GenerateAssets::getUrl(['template' => $record->id])),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomAssetTemplates::route('/'),
            'create' => Pages\CreateRoomAssetTemplate::route('/create'),
            'edit' => Pages\EditRoomAssetTemplate::route('/{record}/edit'),
        ];
    }
}
