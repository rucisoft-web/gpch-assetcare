<?php

namespace App\Filament\App\Resources\SupplyRequests;

use App\Enums\SupplyRequestState;
use App\Filament\App\Resources\SupplyRequests\Pages\CreateSupplyRequest;
use App\Filament\App\Resources\SupplyRequests\Pages\EditSupplyRequest;
use App\Filament\App\Resources\SupplyRequests\Pages\ListSupplyRequests;
use App\Filament\App\Resources\SupplyRequests\Pages\ViewSupplyRequest;
use App\Filament\Concerns\OperationalPermissions;
use App\Filament\Support\Domain;
use App\Models\SupplyItem;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupplyRequestResource extends Resource
{
    use OperationalPermissions;

    protected static ?string $model = SupplyRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'ATK & Persediaan';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Permintaan barang';

    protected static ?string $pluralModelLabel = 'Permintaan Barang';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $count = static::canOperate() ? SupplyRequest::query()->open()->count() : 0;

        return $count ?: null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Permintaan belum diserahkan';
    }

    public static function canEdit(Model $record): bool
    {
        return static::canOperate() && $record->state === SupplyRequestState::Submitted;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                Select::make('requester_id')->label('Pemohon')->options(fn () => static::memberOptions())->searchable()->default(fn () => auth()->id()),
                Select::make('department_id')->label('Departemen')->options(fn () => Domain::departmentOptions())->searchable(),
                DatePicker::make('needed_by')->label('Dibutuhkan tanggal')->default(today()->addDays(2)),
                Textarea::make('purpose')->label('Keperluan')->columnSpanFull(),
            ]),
            Section::make('Barang')->columnSpanFull()->schema([
                Repeater::make('lines')->hiddenLabel()->relationship()->minItems(1)->defaultItems(1)->columns(3)
                    ->addActionLabel('Tambah barang')
                    ->schema([
                        Select::make('supply_item_id')->label('Barang')->required()->searchable()->columnSpan(2)
                            ->options(fn () => SupplyItem::query()->where('is_active', true)->orderBy('name')->get()
                                ->mapWithKeys(fn (SupplyItem $item) => [$item->id => "{$item->name} · stok ".Domain::quantity($item->stock)." {$item->unit}"]))
                            ->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('quantity')->label('Jumlah')->numeric()->minValue(0.01)->required(),
                    ]),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('number')->label('Nomor')->weight('bold'),
                TextEntry::make('state')->label('Status')->badge(),
                TextEntry::make('requester.name')->label('Pemohon')->placeholder('—'),
                TextEntry::make('department.name')->label('Departemen')->placeholder('—'),
                TextEntry::make('requested_at')->label('Diajukan')->dateTime('d/m/Y H:i'),
                TextEntry::make('needed_by')->label('Dibutuhkan')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('approver.name')->label('Diproses oleh')->placeholder('—'),
                TextEntry::make('issued_at')->label('Diserahkan')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('purpose')->label('Keperluan')->placeholder('—')->columnSpan(2),
                TextEntry::make('note')->label('Catatan')->placeholder('—')->columnSpan(2),
            ]),
            Section::make('Barang')->columnSpanFull()->schema([
                RepeatableEntry::make('lines')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('item.name')->label('Barang')->columnSpan(2),
                    TextEntry::make('quantity')->label('Diminta')
                        ->formatStateUsing(fn (SupplyRequestLine $record) => Domain::quantity($record->quantity).' '.$record->item?->unit),
                    TextEntry::make('issued_quantity')->label('Diserahkan')->placeholder('—')
                        ->formatStateUsing(fn (SupplyRequestLine $record) => Domain::quantity($record->issued_quantity).' '.$record->item?->unit),
                ]),
            ]),
        ]);
    }

    public static function workflowActions(): array
    {
        return [
            Action::make('approve')->label('Setujui')->icon(Heroicon::OutlinedCheck)->color('info')
                ->visible(fn (SupplyRequest $record) => static::canManage() && $record->state === SupplyRequestState::Submitted)
                ->action(fn (SupplyRequest $record) => Domain::attempt(fn () => $record->approve(), 'Permintaan disetujui.')),
            Action::make('issue')->label('Serahkan barang')->icon(Heroicon::OutlinedHandRaised)->color('success')
                ->visible(fn (SupplyRequest $record) => static::canOperate() && in_array($record->state, [SupplyRequestState::Submitted, SupplyRequestState::Approved], true))
                ->modalDescription('Jumlah yang diserahkan mengurangi stok. Ubah bila hanya sebagian yang tersedia.')
                ->fillForm(fn (SupplyRequest $record) => ['quantities' => $record->lines->mapWithKeys(fn ($line) => [$line->id => $line->quantity])->all()])
                ->schema(fn (SupplyRequest $record) => $record->lines()->with('item')->get()->map(fn (SupplyRequestLine $line) => TextInput::make("quantities.{$line->id}")
                    ->label("{$line->item->name} (stok ".Domain::quantity($line->item->stock)." {$line->item->unit})")
                    ->numeric()->minValue(0)->maxValue($line->quantity)->required())->all())
                ->action(fn (SupplyRequest $record, array $data) => Domain::attempt(fn () => $record->issue($data['quantities'] ?? []), 'Barang diserahkan dan stok dikurangi.')),
            Action::make('reject')->label('Tolak')->icon(Heroicon::OutlinedXMark)->color('danger')
                ->visible(fn (SupplyRequest $record) => static::canManage() && in_array($record->state, [SupplyRequestState::Submitted, SupplyRequestState::Approved], true))
                ->schema([Textarea::make('note')->label('Alasan')->required()])
                ->action(fn (SupplyRequest $record, array $data) => Domain::attempt(fn () => $record->reject($data['note']), 'Permintaan ditolak.')),
            Action::make('cancel')->label('Batalkan')->icon(Heroicon::OutlinedNoSymbol)->color('gray')->requiresConfirmation()
                ->visible(fn (SupplyRequest $record) => static::canOperate() && in_array($record->state, [SupplyRequestState::Submitted, SupplyRequestState::Approved], true))
                ->action(fn (SupplyRequest $record) => Domain::attempt(fn () => $record->cancel(), 'Permintaan dibatalkan.')),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('Nomor')->searchable(),
                TextColumn::make('requested_at')->label('Diajukan')->dateTime('d/m/Y')->sortable(),
                TextColumn::make('requester.name')->label('Pemohon')->placeholder('—')
                    ->description(fn (SupplyRequest $record) => $record->department?->name),
                TextColumn::make('lines_summary')->label('Barang')->wrap()
                    ->state(fn (SupplyRequest $record) => $record->lines->map(fn ($line) => $line->item?->name.' × '.Domain::quantity($line->quantity))->join(', ')),
                TextColumn::make('needed_by')->label('Dibutuhkan')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('state')->label('Status')->badge(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['lines.item', 'requester', 'department']))
            ->filters([
                SelectFilter::make('state')->label('Status')->options(SupplyRequestState::class)->multiple(),
                SelectFilter::make('department_id')->label('Departemen')->relationship('department', 'name'),
            ])
            ->recordActions([ViewAction::make(), ActionGroup::make([...static::workflowActions(), EditAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplyRequests::route('/'),
            'create' => CreateSupplyRequest::route('/create'),
            'view' => ViewSupplyRequest::route('/{record}'),
            'edit' => EditSupplyRequest::route('/{record}/edit'),
        ];
    }
}
