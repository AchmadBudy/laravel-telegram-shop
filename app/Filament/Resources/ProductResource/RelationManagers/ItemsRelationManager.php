<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Textarea::make('item')
                            ->required()
                            ->maxLength(100),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item')
            ->columns([
                TextColumn::make('item')
                    ->searchable(),
                IconColumn::make('is_sold')
                    ->label('Is Item Available?')
                    ->trueIcon('heroicon-o-x-mark')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-badge')
                    ->falseColor('success')
                    ->boolean()
            ])
            ->filters([
                SelectFilter::make('is_sold')
                    ->label('Is Item Available?')
                    ->options([
                        false => 'Available',
                        true => 'Sold',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data, string $model): Model {
                        $items = explode("\n", $data['item']);

                        DB::beginTransaction();
                        try {
                            $dataToInsert = [];
                            foreach ($items as $item) {
                                $dataToInsert[] = [
                                    'item' => $item,
                                    'is_sold' => false,
                                    'product_id' => $this->ownerRecord->id,
                                    'created_at' => now(),
                                ];
                            }

                            $this->ownerRecord->items()->insert($dataToInsert);

                            // update stock
                            $product = $this->ownerRecord;
                            $product->stock += count($items);
                            $product->save();

                            DB::commit();
                        } catch (\Throwable $th) {
                            DB::rollBack();
                            return null;
                        }

                        return $this->ownerRecord;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
