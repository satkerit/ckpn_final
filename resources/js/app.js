import Swal from 'sweetalert2';

// Setup global SweetAlert2
window.Swal = Swal;

// SweetAlert2 dengan tema custom untuk CKPN
window.Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

// Progress dialog untuk upload
window.showUploadProgress = (title = 'Mengupload Data...') => {
    return Swal.fire({
        title: title,
        html: `
            <div class="mb-4">
                <div class="flex items-center justify-center mb-3">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
                <div class="bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                    <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <div id="progress-text" class="text-sm text-gray-600 mt-2">Memulai upload...</div>
                <div id="file-info" class="text-xs text-gray-500 mt-1"></div>
            </div>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        customClass: {
            popup: 'animate__animated animate__fadeIn'
        }
    });
};

// Update progress dialog
window.updateProgress = (percentage, text = '', fileInfo = '') => {
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const fileInfoElement = document.getElementById('file-info');
    
    if (progressBar) {
        progressBar.style.width = percentage + '%';
    }
    if (progressText) {
        progressText.textContent = text;
    }
    if (fileInfoElement) {
        fileInfoElement.textContent = fileInfo;
    }
};

// Confirmation dialog utilities
window.confirmAction = (options = {}) => {
    const defaults = {
        title: 'Konfirmasi Tindakan',
        text: 'Apakah Anda yakin ingin melakukan tindakan ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        reverseButtons: true
    };
    
    return Swal.fire({ ...defaults, ...options });
};

window.confirmDelete = (itemName = 'item') => {
    return Swal.fire({
        title: 'Hapus Data?',
        text: `Data ${itemName} yang dihapus tidak dapat dikembalikan!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        reverseButtons: true
    });
};

window.confirmCreate = (itemName = 'data') => {
    return Swal.fire({
        title: 'Buat Data Baru?',
        text: `Apakah Anda yakin ingin membuat ${itemName} baru?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buat',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    });
};

window.confirmUpdate = (itemName = 'data') => {
    return Swal.fire({
        title: 'Simpan Perubahan?',
        text: `Apakah Anda yakin ingin menyimpan perubahan ${itemName}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Simpan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#007bff',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    });
};

// Success/Error notifications
window.showSuccess = (message, title = 'Berhasil!') => {
    return Toast.fire({
        icon: 'success',
        title: title,
        text: message
    });
};

window.showError = (message, title = 'Gagal!') => {
    return Toast.fire({
        icon: 'error',
        title: title,
        text: message
    });
};

window.showWarning = (message, title = 'Peringatan!') => {
    return Toast.fire({
        icon: 'warning',
        title: title,
        text: message
    });
};

window.showInfo = (message, title = 'Info') => {
    return Toast.fire({
        icon: 'info',
        title: title,
        text: message
    });
};

// Alpine dimuat otomatis via @livewireScripts (bundled dengan Livewire).
// Jangan start Alpine manual di sini — dua instance Alpine membuat
// direktif wire:* tidak ter-handle sehingga semua tombol Livewire mati.
