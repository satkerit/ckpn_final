/**
 * Enhanced Upload Progress Manager
 * Manages upload progress dialogs with real-time updates
 */
class UploadProgressManager {
    constructor() {
        this.activeUploads = new Map();
        this.setupEventListeners();
    }

    /**
     * Start new upload with progress tracking
     */
    startUpload(uploadId, options = {}) {
        const uploadConfig = {
            id: uploadId,
            title: options.title || 'Mengupload Data...',
            subtitle: options.subtitle || 'Mohon tunggu, proses upload sedang berlangsung',
            fileInfo: options.fileInfo || '',
            showCancel: options.showCancel || false,
            onComplete: options.onComplete || null,
            onError: options.onError || null,
            onCancel: options.onCancel || null,
            startTime: Date.now()
        };

        this.activeUploads.set(uploadId, uploadConfig);

        // Show progress modal
        this.dispatchEvent('upload-start', uploadConfig);
        
        return uploadId;
    }

    /**
     * Update upload progress
     */
    updateProgress(uploadId, data) {
        const upload = this.activeUploads.get(uploadId);
        if (!upload) return;

        const progressData = {
            uploadId,
            percentage: Math.min(Math.max(data.percentage || 0, 0), 100),
            statusTitle: data.statusTitle || upload.statusTitle,
            statusText: data.statusText || upload.statusText,
            fileInfo: data.fileInfo || upload.fileInfo,
            currentFile: data.currentFile || null,
            processedCount: data.processedCount || 0,
            totalCount: data.totalCount || 0,
            speed: this.calculateSpeed(upload, data.percentage),
            eta: this.calculateETA(upload, data.percentage)
        };

        // Update stored upload data
        Object.assign(upload, progressData);

        // Dispatch progress event
        this.dispatchEvent('upload-progress', progressData);
    }

    /**
     * Complete upload
     */
    completeUpload(uploadId, data = {}) {
        const upload = this.activeUploads.get(uploadId);
        if (!upload) return;

        const completeData = {
            uploadId,
            message: data.message || 'Upload berhasil diselesaikan',
            details: data.details || null,
            callback: upload.onComplete
        };

        this.dispatchEvent('upload-complete', completeData);
        
        // Clean up after delay
        setTimeout(() => {
            this.activeUploads.delete(uploadId);
        }, 2000);
    }

    /**
     * Handle upload error
     */
    handleError(uploadId, error) {
        const upload = this.activeUploads.get(uploadId);
        if (!upload) return;

        const errorData = {
            uploadId,
            message: error.message || 'Terjadi kesalahan saat upload',
            details: error.details || null,
            callback: upload.onError
        };

        this.dispatchEvent('upload-error', errorData);
        
        // Keep in activeUploads for manual cleanup
    }

    /**
     * Cancel upload
     */
    cancelUpload(uploadId) {
        const upload = this.activeUploads.get(uploadId);
        if (!upload) return;

        if (upload.onCancel) {
            upload.onCancel(uploadId);
        }

        this.activeUploads.delete(uploadId);
        this.dispatchEvent('upload-cancel', { uploadId });
    }

    /**
     * Calculate upload speed
     */
    calculateSpeed(upload, currentPercentage) {
        if (!currentPercentage || currentPercentage === 0) return 0;
        
        const elapsed = (Date.now() - upload.startTime) / 1000; // seconds
        const progressMade = currentPercentage / 100;
        
        return progressMade / elapsed; // progress per second
    }

    /**
     * Calculate estimated time of arrival
     */
    calculateETA(upload, currentPercentage) {
        if (!currentPercentage || currentPercentage === 0) return null;
        
        const speed = this.calculateSpeed(upload, currentPercentage);
        if (speed === 0) return null;
        
        const remainingProgress = (100 - currentPercentage) / 100;
        return Math.round(remainingProgress / speed); // seconds
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Listen for cancel events
        window.addEventListener('upload-cancel', (event) => {
            const { uploadId } = event.detail;
            if (uploadId && this.activeUploads.has(uploadId)) {
                this.cancelUpload(uploadId);
            }
        });
    }

    /**
     * Dispatch custom events
     */
    dispatchEvent(eventName, data) {
        window.dispatchEvent(new CustomEvent(eventName, {
            detail: data,
            bubbles: true
        }));
    }

    /**
     * Get active upload info
     */
    getActiveUpload(uploadId) {
        return this.activeUploads.get(uploadId);
    }

    /**
     * Get all active uploads
     */
    getAllActiveUploads() {
        return Array.from(this.activeUploads.values());
    }

    /**
     * Clear all uploads
     */
    clearAll() {
        this.activeUploads.clear();
    }
}

// Initialize global upload manager
window.uploadManager = new UploadProgressManager();

// Enhanced SweetAlert2 integration with upload manager
window.showAdvancedUploadProgress = (options = {}) => {
    const uploadId = options.uploadId || 'upload_' + Date.now();
    
    return window.uploadManager.startUpload(uploadId, options);
};

// Batch progress dialog for multiple files
window.showBatchUploadProgress = (files, options = {}) => {
    const uploadId = 'batch_' + Date.now();
    
    const batchOptions = {
        ...options,
        title: options.title || 'Upload Multiple Files',
        subtitle: `Mengupload ${files.length} file...`,
        fileInfo: `0 dari ${files.length} file selesai`,
        showCancel: true
    };

    return window.uploadManager.startUpload(uploadId, batchOptions);
};

// Utility functions for common upload scenarios
window.uploadUtils = {
    // Excel file upload
    uploadExcel: (file, options = {}) => {
        const uploadId = window.showAdvancedUploadProgress({
            title: 'Upload File Excel',
            subtitle: 'Memproses file Excel...',
            fileInfo: `File: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`,
            showCancel: true,
            ...options
        });

        return uploadId;
    },

    // Data import upload
    uploadDataImport: (type, file, options = {}) => {
        const typeNames = {
            'master': 'Data Master',
            'period': 'Data Periode',
            'collateral': 'Data Jaminan',
            'office': 'Data Kantor',
            'collateral_type': 'Jenis Jaminan'
        };

        const uploadId = window.showAdvancedUploadProgress({
            title: `Upload ${typeNames[type] || 'Data'}`,
            subtitle: 'Memvalidasi dan memproses data...',
            fileInfo: `File: ${file.name}`,
            showCancel: false,
            ...options
        });

        return uploadId;
    },

    // Bulk calculation upload
    uploadCalculation: (calculationType, options = {}) => {
        const calcNames = {
            'pd_netflow': 'PD Netflow',
            'pd_migration': 'PD Migration',
            'lgd_er': 'LGD Expected Recoveries',
            'lgd_cs': 'LGD Collateral Shortfall',
            'ckpn_individual': 'CKPN Individual',
            'ckpn_collective': 'CKPN Kolektif'
        };

        const uploadId = window.showAdvancedUploadProgress({
            title: `Perhitungan ${calcNames[calculationType] || calculationType}`,
            subtitle: 'Menjalankan kalkulasi dan menyimpan hasil...',
            showCancel: false,
            ...options
        });

        return uploadId;
    }
};