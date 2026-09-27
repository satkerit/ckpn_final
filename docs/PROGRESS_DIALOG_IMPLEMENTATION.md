# Progress Dialog & Confirmation Dialog Implementation

Implementasi lengkap progress dialog untuk upload data dan confirmation dialog untuk semua fungsi CRUD menggunakan SweetAlert2, Alpine.js, dan Laravel.

## Features

### ✅ Confirmation Dialogs
- Standard confirmation untuk Create, Update, Delete operations
- Bulk action confirmations
- Custom confirmation dialogs dengan konfigurasi fleksibel
- Trait `HasConfirmationDialogs` untuk konsistensi di semua Resources

### ✅ Progress Dialogs
- Real-time progress tracking untuk upload jobs
- Advanced progress features: speed calculation, ETA, file info
- Alpine.js component untuk UI yang responsive
- JavaScript upload manager untuk tracking multiple uploads
- Trait `HasProgressTracking` untuk implementasi di job classes

### ✅ Notifications
- Toast notifications untuk success, error, warning, info
- Customizable styling dan positioning
- Auto-hide dengan timer

## Quick Usage

### 1. Confirmation Dialogs

#### Pada Filament Resources:
```php
use App\Filament\Traits\HasConfirmationDialogs;

class YourResource extends Resource
{
    use HasConfirmationDialogs;
    
    public static function table(Table $table): Table
    {
        return $table
            ->actions(static::getStandardTableActions('nama item'))
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make(
                    static::getStandardBulkActions('nama item')
                ),
            ]);
    }
}
```

#### Pada Filament Pages:
```php
use App\Filament\Traits\HasConfirmationDialogs;

class CreateYourResource extends CreateRecord
{
    use HasConfirmationDialogs;
    
    protected function getFormActions(): array
    {
        return $this->getCreateFormActions('nama item');
    }
}

class EditYourResource extends EditRecord
{
    use HasConfirmationDialogs;
    
    protected function getHeaderActions(): array
    {
        return $this->getEditHeaderActions('nama item');
    }
    
    protected function getFormActions(): array
    {
        return $this->getEditFormActions('nama item');
    }
}
```

#### JavaScript:
```javascript
// Standard confirmations
window.confirmCreate('data pembiayaan').then((result) => {
    if (result.isConfirmed) {
        // Execute create action
    }
});

window.confirmUpdate('segmen risiko').then((result) => {
    if (result.isConfirmed) {
        // Execute update action
    }
});

window.confirmDelete('data anomali').then((result) => {
    if (result.isConfirmed) {
        // Execute delete action
    }
});

// Custom confirmation
window.confirmAction({
    title: 'Custom Title',
    text: 'Custom description',
    icon: 'question',
    confirmButtonText: 'Ya, Lanjutkan'
}).then((result) => {
    if (result.isConfirmed) {
        // Execute custom action
    }
});
```

### 2. Progress Dialogs

#### Pada Job Classes:
```php
use App\Traits\HasProgressTracking;

class YourUploadJob implements ShouldQueue
{
    use HasProgressTracking;
    
    public function handle(): void
    {
        // Initialize progress
        $this->initializeProgress((string) $this->batchId, $totalRows);
        
        foreach ($data as $row) {
            // Process row...
            
            // Update progress every batch
            if ($processedRows % 100 === 0) {
                $this->updateBatchProgress(
                    $processedRows, 
                    $importedRows, 
                    $skippedRows, 
                    $failedRows
                );
            }
        }
        
        // Complete progress
        $this->completeProgress('Upload selesai!');
    }
}
```

#### JavaScript Usage:
```javascript
// Basic progress
window.showUploadProgress('Mengupload Data...');

// Excel upload
const uploadId = window.uploadUtils.uploadExcel(file, {
    onComplete: () => console.log('Upload complete'),
    onError: (error) => console.log('Upload failed', error)
});

// Data import
const uploadId = window.uploadUtils.uploadDataImport('master', file);

// Batch upload
const uploadId = window.showBatchUploadProgress(files);

// Calculation progress
const uploadId = window.uploadUtils.uploadCalculation('pd_netflow');

// Manual progress update
window.updateProgress(percentage, statusText, fileInfo);
```

#### Alpine.js Component:
```html
<!-- Include the component in your blade template -->
<x-upload-progress-modal />

<!-- Trigger via JavaScript events -->
<script>
window.dispatchEvent(new CustomEvent('upload-start', {
    detail: {
        title: 'Upload Title',
        subtitle: 'Upload description',
        fileInfo: 'filename.xlsx (1.2 MB)',
        showCancel: true
    }
}));
</script>
```

### 3. Notifications

```javascript
// Success notification
window.showSuccess('Operasi berhasil!', 'Sukses');

// Error notification
window.showError('Terjadi kesalahan!', 'Error');

// Warning notification
window.showWarning('Perhatian!', 'Warning');

// Info notification
window.showInfo('Informasi penting', 'Info');
```

## API Endpoints

### Progress Tracking API:
- `GET /api/upload-progress/{uploadId}` - Get progress data
- `GET /api/upload-progress` - Get all active uploads
- `POST /api/upload-progress/{uploadId}/cancel` - Cancel upload

## Advanced Features

### Progress Manager
```javascript
// Start upload with detailed options
const uploadId = window.uploadManager.startUpload('unique_id', {
    title: 'Custom Upload',
    subtitle: 'Processing data...',
    fileInfo: 'file.xlsx',
    showCancel: true,
    onComplete: (data) => console.log('Complete', data),
    onError: (error) => console.log('Error', error),
    onCancel: (id) => console.log('Cancelled', id)
});

// Update progress
window.uploadManager.updateProgress(uploadId, {
    percentage: 50,
    statusTitle: 'Processing...',
    statusText: 'Half done',
    processedCount: 500,
    totalCount: 1000
});

// Complete upload
window.uploadManager.completeUpload(uploadId, {
    message: 'Success!',
    details: 'All data processed'
});

// Handle error
window.uploadManager.handleError(uploadId, {
    message: 'Failed to process',
    details: 'Connection timeout'
});
```

### Custom Confirmation Actions
```php
// Dalam Resource
Tables\Actions\Action::make('customAction')
    ->label('Custom Action')
    ->requiresConfirmation()
    ->modalHeading('Custom Confirmation')
    ->modalDescription('Are you sure?')
    ->modalSubmitActionLabel('Yes, Proceed')
    ->modalCancelActionLabel('Cancel')
    ->action(fn ($record) => $this->doCustomAction($record));
```

## Files Structure

```
app/
├── Filament/Traits/
│   └── HasConfirmationDialogs.php      # Trait untuk confirmation dialogs
├── Traits/
│   └── HasProgressTracking.php         # Trait untuk progress tracking
├── Http/Controllers/Api/
│   └── UploadProgressController.php    # API controller untuk progress
resources/
├── js/
│   ├── app.js                          # Main app with SweetAlert2 setup
│   └── upload-manager.js               # Upload progress manager
└── views/
    ├── components/
    │   └── upload-progress-modal.blade.php  # Alpine.js component
    └── test-dialogs.blade.php          # Test page untuk development
```

## Development & Testing

Visit `/test-dialogs` (hanya dalam development mode) untuk testing semua dialog dan progress tracking features.

## Browser Support

- Chrome/Edge 88+
- Firefox 85+
- Safari 14+

## Dependencies

- SweetAlert2 ^11.0
- Alpine.js ^3.16
- Laravel ^13.0
- Filament ^4.x