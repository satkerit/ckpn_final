<?php

declare(strict_types=1);

namespace App\Filament\Resources\CkpnCollectiveResultResource\Pages;

use App\Filament\Resources\CkpnCollectiveResultResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\ViewRecord;

final class ViewCkpnCollectiveResults extends ViewRecord
{
    protected static string $resource = CkpnCollectiveResultResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('calculation_period')
                    ->disabled()
                    ->label('Period (YYYYMM)'),
                TextInput::make('usage_type')
                    ->disabled(),
                TextInput::make('financing_account.account_number')
                    ->disabled()
                    ->label('Account Number'),
                TextInput::make('office_code')
                    ->disabled(),
                TextInput::make('pd_method_used')
                    ->disabled()
                    ->label('PD Method'),
                TextInput::make('pd_rate')
                    ->disabled()
                    ->step(0.000001)
                    ->label('PD Rate'),
                TextInput::make('lgd_method_used')
                    ->disabled()
                    ->label('LGD Method'),
                TextInput::make('lgd_rate')
                    ->disabled()
                    ->step(0.000001)
                    ->label('LGD Rate'),
                TextInput::make('ead')
                    ->disabled()
                    ->step(0.01)
                    ->label('EAD (Exposure at Default)'),
                TextInput::make('ckpn_amount')
                    ->disabled()
                    ->step(0.01)
                    ->label('CKPN Amount (PD × LGD × EAD)'),
                TextInput::make('calculationRunLog.status')
                    ->disabled()
                    ->label('Job Status'),
                TextInput::make('notes')
                    ->disabled(),
                TextInput::make('created_at')
                    ->disabled()
                    ->label('Created At'),
            ]);
    }
}
