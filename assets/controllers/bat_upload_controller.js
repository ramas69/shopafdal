import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview', 'thumb', 'filename', 'filesize', 'submit', 'dropzone'];

    connect() {
        if (this.hasSubmitTarget) this.submitTarget.disabled = true;
        this._currentUrl = null;
    }

    disconnect() {
        if (this._currentUrl) URL.revokeObjectURL(this._currentUrl);
    }

    pick(event) {
        event.preventDefault();
        this.inputTarget.click();
    }

    select() {
        const file = this.inputTarget.files?.[0];
        if (!file) {
            this._clearPreview();
            return;
        }
        this._renderPreview(file);
    }

    clear(event) {
        event.preventDefault();
        this.inputTarget.value = '';
        this._clearPreview();
    }

    _renderPreview(file) {
        if (this._currentUrl) URL.revokeObjectURL(this._currentUrl);
        this._currentUrl = URL.createObjectURL(file);

        if (this.hasPreviewTarget) this.previewTarget.classList.remove('hidden');
        if (this.hasDropzoneTarget) this.dropzoneTarget.classList.add('hidden');
        if (this.hasFilenameTarget) this.filenameTarget.textContent = file.name;
        if (this.hasFilesizeTarget) this.filesizeTarget.textContent = this._formatSize(file.size);

        if (this.hasThumbTarget) {
            if (file.type.startsWith('image/')) {
                this.thumbTarget.innerHTML = `<img src="${this._currentUrl}" alt="" class="w-full h-full object-contain">`;
            } else {
                const icon = file.type === 'application/pdf' ? 'PDF' : 'Fichier';
                this.thumbTarget.innerHTML = `
                    <div class="flex flex-col items-center justify-center gap-1 text-[var(--color-secondary)]">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        <span class="text-[10px] font-semibold uppercase tracking-wide">${icon}</span>
                    </div>
                `;
            }
        }

        if (this.hasSubmitTarget) this.submitTarget.disabled = false;
    }

    _clearPreview() {
        if (this._currentUrl) {
            URL.revokeObjectURL(this._currentUrl);
            this._currentUrl = null;
        }
        if (this.hasPreviewTarget) this.previewTarget.classList.add('hidden');
        if (this.hasDropzoneTarget) this.dropzoneTarget.classList.remove('hidden');
        if (this.hasThumbTarget) this.thumbTarget.innerHTML = '';
        if (this.hasSubmitTarget) this.submitTarget.disabled = true;
    }

    _formatSize(bytes) {
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
        return (bytes / (1024 * 1024)).toFixed(2) + ' Mo';
    }
}
