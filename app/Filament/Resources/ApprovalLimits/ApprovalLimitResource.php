<?php

namespace App\Filament\Resources\ApprovalLimits;

use App\Filament\Concerns\HasPermissions;
use App\Filament\Support\F;
use App\Models\ApprovalLimit;
use App\Support\Fmt;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Batas persetujuan berjenjang dengan masa berlaku & pengganti sementara. Nominal ditetapkan manajemen. */
class ApprovalLimitResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = ApprovalLimit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Batas persetujuan';

    protected static ?string $pluralModelLabel = 'Batas persetujuan';

    protected static ?string $viewPermission = 'settings.manage';

    protected static ?string $editPermission = 'settings.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('object_type')->label('Objek')->options(['work_order' => 'Work order (biaya / lingkup)'])->default('work_order')->required()->native(false),
            F::user('user_id', 'Penyetuju', 'wo.approve')->required(),
            TextInput::make('max_amount')->label('Batas nilai')->numeric()->prefix('Rp')->minValue(0)->helperText('Kosong = tanpa batas nominal'),
            F::user('delegate_user_id', 'Pengganti sementara', 'wo.approve'),
            DatePicker::make('valid_from')->label('Berlaku mulai')->required()->default(now()),
            DatePicker::make('valid_to')->label('Berlaku sampai'),
            TextInput::make('note')->label('Dasar penetapan')->columnSpanFull()->placeholder('mis. SK GM no. …'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('Penyetuju')->description(fn ($record) => $record->user?->job_title),
            TextColumn::make('max_amount')->label('Batas')->formatStateUsing(fn ($state) => $state === null ? 'Tanpa batas' : Fmt::rp($state))->placeholder('Tanpa batas'),
            TextColumn::make('delegate.name')->label('Pengganti'),
            TextColumn::make('valid_from')->label('Mulai')->date('d M Y'),
            TextColumn::make('valid_to')->label('Sampai')->date('d M Y')->placeholder('—'),
            TextColumn::make('note')->label('Dasar')->wrap(),
        ])->recordActions([EditAction::make()])
            ->description('Sistem menolak persetujuan biaya yang belum mempunyai jalur persetujuan sah. Pembuat pengajuan tidak dapat menyetujui sendiri.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageApprovalLimits::route('/')];
    }
}
