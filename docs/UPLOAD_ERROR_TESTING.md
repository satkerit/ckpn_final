# Upload Error Handling - Test Scenarios

## Overview
Dokumentasi ini menjelaskan berbagai skenario error yang telah diperbaiki dalam fitur upload Excel dan cara untuk menguji setiap skenario.

## Error Types yang Telah Diperbaiki

### 1. Validation Errors (Per Row)
**Error yang ditangani:**
- Nomor kontrak kosong
- Tipe penggunaan tidak valid (harus 1, 2, atau 3)
- Field terlalu panjang (customer_name > 255, product_code > 50, dll.)

**Test Data untuk Validasi Error:**
```excel
account_number | customer_name                                    | usage_type
               | John Doe                                        | 1           // Error: account_number kosong
12345         | Nama yang sangat panjang... (>255 characters) | 1           // Error: nama terlalu panjang  
12346         | Jane Doe                                        | 5           // Error: usage_type tidak valid
```

### 2. Database Errors
**Error yang ditangani:**
- Constraint violations (duplicate keys, foreign key errors)
- Connection timeouts
- SQL syntax errors

**Test Scenarios:**
1. Upload file dengan duplikat account_number
2. Upload dengan foreign key yang tidak ada
3. Simulate database connection issues

### 3. System Errors
**Error yang ditangani:**
- File corruption
- Memory exhaustion
- Disk space issues
- Permission errors

## Improved Error Display

### 1. Upload Interface (UploadIndex)
**Sebelum:**
- Hanya menampilkan "gagal diproses"
- Tidak ada detail error

**Sesudah:**
- Pesan spesifik berdasarkan status
- Info jumlah error ditemukan
- Link ke detail error
- Multiple states: success, warning, error, info

**Test di UI:**
1. Upload file valid → Pesan success hijau
2. Upload file dengan beberapa error → Pesan warning kuning dengan count error
3. Upload file yang gagal total → Pesan error merah dengan detail

### 2. History Interface (UploadBatchIndex)
**Sebelum:**
- Kolom "Gagal" hanya menampilkan angka
- Tidak ada cara melihat detail error

**Sesudah:**
- Kolom "Gagal" menjadi clickable jika ada error
- Modal popup menampilkan detail error per row
- Error breakdown berdasarkan field dan baris

**Test di UI:**
1. Klik angka di kolom "Gagal" → Modal error detail terbuka
2. Modal menampilkan list error dengan:
   - Nomor baris
   - Field yang error
   - Pesan error
   - Nilai yang menyebabkan error

## Testing Instructions

### Preparation
1. Pastikan queue worker berjalan:
   ```bash
   php artisan queue:work --queue=ckpn-calculation
   ```

2. Monitor logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Test Scenario 1: Validation Errors
1. Buat file Excel dengan data invalid:
   - Baris dengan account_number kosong
   - Baris dengan usage_type = 99
   - Baris dengan nama > 255 karakter

2. Upload via `/upload`
3. Verify:
   - Progress dialog menunjukkan error
   - Message menunjukkan warning dengan count error
   - Di `/upload/batches` klik error count
   - Modal menampilkan detail error per row

### Test Scenario 2: Database Errors
1. Upload file dengan account_number duplikat
2. Verify:
   - Error dilog dengan detail
   - Single record fallback works
   - Database errors tercatat dengan jelas

### Test Scenario 3: System Errors
1. Upload file yang corrupted/invalid format
2. Upload file yang sangat besar (> 20MB)
3. Verify:
   - System errors tertangkap
   - Error message informatif
   - No silent failures

## Error Structure

### New Error Format
```php
[
    'row' => 5,                    // Baris Excel (1-based)
    'field' => 'customer_name',    // Field yang error
    'value' => 'Too long name...',  // Nilai yang menyebabkan error
    'error' => 'Nama terlalu panjang (maksimal 255 karakter)'  // Pesan error
]
```

### System Error Format
```php
[
    'row' => 'system',
    'field' => 'critical_error',
    'value' => '',
    'error' => 'Database connection failed',
    'details' => [
        'error_code' => 1045,
        'file' => '/path/to/file.php',
        'line' => 123,
        'processed_rows' => 1500,
        'imported_rows' => 1450
    ]
]
```

## Log Monitoring

### Successful Processing
```
ProcessFinancingMasterUploadJob: Batch insert successful
- batch_id: 123
- inserted_count: 1000
- total_imported: 1000
```

### Error Processing  
```
ProcessFinancingMasterUploadJob: Batch insert failed
- batch_id: 123
- error: Duplicate entry '12345' for key 'PRIMARY'
- buffer_count: 1000
- stack_trace: ...
```

### Critical Failures
```
ProcessFinancingMasterUploadJob: Critical failure
- batch_id: 123
- file_path: /path/to/upload.xlsx
- error: Out of memory
- processed_rows: 50000
- imported_rows: 45000
```

## Expected Behavior

### UI States
1. **Processing**: Blue info message dengan spinner
2. **Success**: Green success message dengan stats
3. **Warning**: Yellow warning dengan error count
4. **Error**: Red error dengan main error message

### Error Modal
1. **Header**: Batch ID dan context
2. **Summary**: Total error count dengan icon
3. **List**: Detailed error per row dengan badges
4. **Actions**: Close button

### Database Records
1. **error_summary**: Array of structured errors
2. **progress_log**: Processing metadata dengan error breakdown
3. **failed_rows**: Accurate count dari actual errors

## Files Modified

### Backend
- `app/Jobs/ProcessFinancingMasterUploadJob.php` - Enhanced error handling
- `app/Livewire/UploadData/UploadIndex.php` - Better message display
- `app/Traits/HasProgressTracking.php` - Progress tracking integration

### Frontend  
- `resources/views/livewire/upload-data/upload-index.blade.php` - Enhanced messages
- `resources/views/livewire/upload-data/upload-batch-index.blade.php` - Error modal

### Integration
- Error tracking terintegrasi dengan progress dialog
- Real-time error count updates
- Comprehensive logging untuk debugging