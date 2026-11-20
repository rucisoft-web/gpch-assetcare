<?php

namespace App\Filament\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Catatan percakapan antarpetugas (tidak dapat diedit setelah dikirim). */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Catatan';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([Textarea::make('body')->label('Catatan')->required()->rows(3)->maxLength(2000)]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                TextColumn::make('user.name')->label('Oleh'),
                TextColumn::make('body')->label('Catatan')->wrap(),
            ])
            ->emptyStateHeading('Belum ada catatan')
            ->headerActions([
                CreateAction::make()->label('Tambah catatan')->mutateDataUsing(fn (array $data) => $data + ['user_id' => auth()->id()]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
