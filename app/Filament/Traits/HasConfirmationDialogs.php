<?php

namespace App\Filament\Traits;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;

trait HasConfirmationDialogs
{
    /**
     * Get standard create action with confirmation
     */
    protected function getCreateActionWithConfirmation(string $itemName = 'data'): Actions\CreateAction
    {
        return Actions\CreateAction::make()
            ->requiresConfirmation()
            ->modalHeading("Buat {$itemName} Baru")
            ->modalDescription("Apakah Anda yakin ingin membuat {$itemName} baru dengan data ini?")
            ->modalSubmitActionLabel('Ya, Buat')
            ->modalCancelActionLabel('Batal')
            ->after(function () use ($itemName) {
                Notification::make()
                    ->title(ucfirst($itemName).' berhasil dibuat!')
                    ->success()
                    ->send();
            });
    }

    /**
     * Get standard save action with confirmation
     */
    protected function getSaveActionWithConfirmation(string $itemName = 'data'): Actions\Action
    {
        return Actions\Action::make('save')
            ->label('Simpan Perubahan')
            ->action('save')
            ->requiresConfirmation()
            ->modalHeading('Simpan Perubahan')
            ->modalDescription("Apakah Anda yakin ingin menyimpan perubahan {$itemName}?")
            ->modalSubmitActionLabel('Ya, Simpan')
            ->modalCancelActionLabel('Batal')
            ->after(function () {
                Notification::make()
                    ->title('Perubahan berhasil disimpan!')
                    ->success()
                    ->send();
            });
    }

    /**
     * Get standard cancel action with confirmation
     */
    protected function getCancelActionWithConfirmation(string $action = 'pembuatan'): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Batal')
            ->color('gray')
            ->url($this->getResource()::getUrl('index'))
            ->requiresConfirmation()
            ->modalHeading(ucfirst($action === 'pembuatan' ? 'Batalkan Pembuatan' : 'Batalkan Perubahan'))
            ->modalDescription($action === 'pembuatan'
                ? 'Data yang telah diisi akan hilang. Apakah Anda yakin ingin membatalkan?'
                : 'Perubahan yang belum disimpan akan hilang. Apakah Anda yakin?'
            )
            ->modalSubmitActionLabel('Ya, Batalkan')
            ->modalCancelActionLabel($action === 'pembuatan' ? 'Lanjutkan Input' : 'Lanjutkan Edit');
    }

    /**
     * Get standard delete header action with confirmation
     */
    protected function getDeleteHeaderActionWithConfirmation(string $itemName = 'data'): Actions\DeleteAction
    {
        return Actions\DeleteAction::make()
            ->requiresConfirmation()
            ->modalHeading("Hapus {$itemName}")
            ->modalDescription("{$itemName} yang dihapus tidak dapat dikembalikan!")
            ->modalSubmitActionLabel('Ya, Hapus')
            ->modalCancelActionLabel('Batal')
            ->after(function () use ($itemName) {
                Notification::make()
                    ->title(ucfirst($itemName).' berhasil dihapus!')
                    ->success()
                    ->send();
            });
    }

    /**
     * Get standard edit table action with confirmation
     */
    public static function getEditActionWithConfirmation(string $itemName = 'data'): EditAction
    {
        return EditAction::make()
            ->requiresConfirmation()
            ->modalHeading("Konfirmasi Edit {$itemName}")
            ->modalDescription("Apakah Anda yakin ingin mengedit {$itemName} ini?")
            ->modalSubmitActionLabel('Ya, Edit')
            ->modalCancelActionLabel('Batal');
    }

    /**
     * Get standard delete table action with confirmation
     */
    public static function getDeleteActionWithConfirmation(string $itemName = 'data'): DeleteAction
    {
        return DeleteAction::make()
            ->requiresConfirmation()
            ->modalHeading("Hapus {$itemName}")
            ->modalDescription("{$itemName} yang dihapus tidak dapat dikembalikan!")
            ->modalSubmitActionLabel('Ya, Hapus')
            ->modalCancelActionLabel('Batal');
    }

    /**
     * Get standard bulk delete action with confirmation
     */
    public static function getDeleteBulkActionWithConfirmation(string $itemName = 'data'): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->requiresConfirmation()
            ->modalHeading("Hapus {$itemName} Terpilih")
            ->modalDescription('Data yang dihapus tidak dapat dikembalikan!')
            ->modalSubmitActionLabel('Ya, Hapus Semua')
            ->modalCancelActionLabel('Batal');
    }

    /**
     * Get custom action with confirmation
     */
    public static function getCustomActionWithConfirmation(
        string $name,
        string $label,
        string $heading,
        string $description,
        callable $action,
        array $options = []
    ): Actions\Action {
        $actionInstance = Actions\Action::make($name)
            ->label($label)
            ->requiresConfirmation()
            ->modalHeading($heading)
            ->modalDescription($description)
            ->modalSubmitActionLabel($options['submitLabel'] ?? 'Ya, Lanjutkan')
            ->modalCancelActionLabel($options['cancelLabel'] ?? 'Batal')
            ->action($action);

        if (isset($options['icon'])) {
            $actionInstance->icon($options['icon']);
        }

        if (isset($options['color'])) {
            $actionInstance->color($options['color']);
        }

        if (isset($options['visible'])) {
            $actionInstance->visible($options['visible']);
        }

        return $actionInstance;
    }

    /**
     * Get standard form actions for create page
     */
    protected function getCreateFormActions(string $itemName = 'data'): array
    {
        return [
            $this->getCreateActionWithConfirmation($itemName),
            $this->getCancelActionWithConfirmation('pembuatan'),
        ];
    }

    /**
     * Get standard form actions for edit page
     */
    protected function getEditFormActions(string $itemName = 'data'): array
    {
        return [
            $this->getSaveActionWithConfirmation($itemName),
            $this->getCancelActionWithConfirmation('perubahan'),
        ];
    }

    /**
     * Get standard header actions for edit page
     */
    protected function getEditHeaderActions(string $itemName = 'data'): array
    {
        return [
            $this->getDeleteHeaderActionWithConfirmation($itemName),
        ];
    }

    /**
     * Get standard table actions with confirmation
     */
    public static function getStandardTableActions(string $itemName = 'data'): array
    {
        return [
            static::getEditActionWithConfirmation($itemName),
            static::getDeleteActionWithConfirmation($itemName),
        ];
    }

    /**
     * Get standard bulk actions with confirmation
     */
    public static function getStandardBulkActions(string $itemName = 'data'): array
    {
        return [
            static::getDeleteBulkActionWithConfirmation($itemName),
        ];
    }
}
