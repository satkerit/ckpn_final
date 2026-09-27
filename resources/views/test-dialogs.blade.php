@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0">
        <div class="border-4 border-dashed border-gray-200 rounded-lg p-6">
            <h1 class="text-3xl font-bold mb-6">Test Progress Dialog & Confirmation</h1>
            
            <!-- Test Confirmation Dialogs -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Test Confirmation Dialogs</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <button onclick="testCreateConfirmation()" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Test Create
                    </button>
                    <button onclick="testUpdateConfirmation()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Test Update
                    </button>
                    <button onclick="testDeleteConfirmation()" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        Test Delete
                    </button>
                    <button onclick="testCustomConfirmation()" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
                        Test Custom
                    </button>
                </div>
            </div>

            <!-- Test Progress Dialogs -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Test Progress Dialogs</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <button onclick="testBasicProgress()" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                        Basic Progress
                    </button>
                    <button onclick="testExcelUploadProgress()" class="bg-teal-500 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded">
                        Excel Upload
                    </button>
                    <button onclick="testDataImportProgress()" class="bg-orange-500 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded">
                        Data Import
                    </button>
                    <button onclick="testBatchProgress()" class="bg-pink-500 hover:bg-pink-700 text-white font-bold py-2 px-4 rounded">
                        Batch Upload
                    </button>
                    <button onclick="testCalculationProgress()" class="bg-gray-700 hover:bg-gray-900 text-white font-bold py-2 px-4 rounded">
                        Calculation
                    </button>
                </div>
            </div>

            <!-- Test Notifications -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Test Notifications</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <button onclick="window.showSuccess('Operasi berhasil!', 'Sukses')" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Success
                    </button>
                    <button onclick="window.showError('Terjadi kesalahan!', 'Error')" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        Error
                    </button>
                    <button onclick="window.showWarning('Perhatian!', 'Warning')" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                        Warning
                    </button>
                    <button onclick="window.showInfo('Informasi penting', 'Info')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Info
                    </button>
                </div>
            </div>

            <!-- Alpine.js Progress Modal -->
            <x-upload-progress-modal />
        </div>
    </div>
</div>

<script>
// Test Confirmation Functions
function testCreateConfirmation() {
    window.confirmCreate('data pembiayaan').then((result) => {
        if (result.isConfirmed) {
            window.showSuccess('Data pembiayaan berhasil dibuat!');
        }
    });
}

function testUpdateConfirmation() {
    window.confirmUpdate('data segmen risiko').then((result) => {
        if (result.isConfirmed) {
            window.showSuccess('Data segmen risiko berhasil diperbarui!');
        }
    });
}

function testDeleteConfirmation() {
    window.confirmDelete('data anomali').then((result) => {
        if (result.isConfirmed) {
            window.showSuccess('Data anomali berhasil dihapus!');
        }
    });
}

function testCustomConfirmation() {
    window.confirmAction({
        title: 'Jalankan Perhitungan CKPN?',
        text: 'Perhitungan akan memakan waktu beberapa menit. Lanjutkan?',
        icon: 'question',
        confirmButtonText: 'Ya, Jalankan',
        confirmButtonColor: '#10B981'
    }).then((result) => {
        if (result.isConfirmed) {
            window.showInfo('Perhitungan CKPN dimulai...');
        }
    });
}

// Test Progress Functions
function testBasicProgress() {
    window.showUploadProgress('Test Basic Progress');
    
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress >= 100) {
            progress = 100;
            clearInterval(interval);
            window.updateProgress(progress, 'Selesai!', 'Upload berhasil diselesaikan');
            setTimeout(() => {
                Swal.close();
            }, 2000);
        } else {
            window.updateProgress(progress, `Progress: ${Math.round(progress)}%`, `Processing data...`);
        }
    }, 500);
}

function testExcelUploadProgress() {
    // Simulate file object
    const fakeFile = { name: 'data_pembiayaan.xlsx', size: 2458624 };
    const uploadId = window.uploadUtils.uploadExcel(fakeFile);
    
    simulateRealTimeProgress(uploadId, 'Excel');
}

function testDataImportProgress() {
    const fakeFile = { name: 'master_data.xlsx' };
    const uploadId = window.uploadUtils.uploadDataImport('master', fakeFile);
    
    simulateRealTimeProgress(uploadId, 'Data Import');
}

function testBatchProgress() {
    const fakeFiles = [
        { name: 'file1.xlsx' },
        { name: 'file2.xlsx' },
        { name: 'file3.xlsx' }
    ];
    const uploadId = window.showBatchUploadProgress(fakeFiles);
    
    simulateRealTimeProgress(uploadId, 'Batch', fakeFiles);
}

function testCalculationProgress() {
    const uploadId = window.uploadUtils.uploadCalculation('pd_netflow');
    
    simulateRealTimeProgress(uploadId, 'Calculation');
}

// Simulate real-time progress updates
function simulateRealTimeProgress(uploadId, type, files = null) {
    let step = 0;
    const totalSteps = files ? files.length * 100 : 1000;
    let currentFile = 0;
    
    const interval = setInterval(() => {
        step += Math.floor(Math.random() * 50) + 10;
        
        if (step >= totalSteps) {
            step = totalSteps;
            clearInterval(interval);
            
            // Complete the upload
            window.dispatchEvent(new CustomEvent('upload-complete', {
                detail: {
                    uploadId: uploadId,
                    message: `${type} upload berhasil diselesaikan!`
                }
            }));
        } else {
            let statusTitle = 'Memproses data...';
            let statusText = '';
            let fileInfo = '';
            
            if (files) {
                currentFile = Math.floor(step / 100);
                statusTitle = `Memproses file ${Math.min(currentFile + 1, files.length)} dari ${files.length}`;
                statusText = `${Math.min(currentFile, files.length)} file selesai`;
                if (currentFile < files.length) {
                    fileInfo = `Current: ${files[currentFile].name}`;
                }
            } else {
                const percentage = (step / totalSteps) * 100;
                statusTitle = `${type} Progress`;
                statusText = `${Math.round(percentage)}% selesai`;
                fileInfo = `Processed: ${step}/${totalSteps} items`;
            }
            
            // Update progress
            window.dispatchEvent(new CustomEvent('upload-progress', {
                detail: {
                    uploadId: uploadId,
                    percentage: (step / totalSteps) * 100,
                    statusTitle: statusTitle,
                    statusText: statusText,
                    fileInfo: fileInfo,
                    currentFile: files ? (currentFile < files.length ? files[currentFile].name : null) : null,
                    processedCount: step,
                    totalCount: totalSteps
                }
            }));
        }
    }, 200);
}

// Test Alpine.js modal
function testAlpineModal() {
    // Trigger the Alpine modal
    window.dispatchEvent(new CustomEvent('upload-start', {
        detail: {
            title: 'Alpine.js Upload',
            subtitle: 'Testing Alpine.js progress modal',
            fileInfo: 'test-file.xlsx (1.2 MB)',
            showCancel: true
        }
    }));
    
    // Simulate progress
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 10;
        if (progress >= 100) {
            progress = 100;
            clearInterval(interval);
            window.dispatchEvent(new CustomEvent('upload-complete', {
                detail: {
                    message: 'Alpine.js upload complete!'
                }
            }));
        } else {
            window.dispatchEvent(new CustomEvent('upload-progress', {
                detail: {
                    percentage: progress,
                    statusTitle: 'Processing...',
                    statusText: `${Math.round(progress)}% complete`
                }
            }));
        }
    }, 300);
}
</script>
@endsection