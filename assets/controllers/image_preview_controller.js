import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview'];

    show() {
        const files = this.inputTarget.files;
        this.previewTarget.innerHTML = '';
        if (!files || files.length === 0) {
            this.previewTarget.classList.add('hidden');
            return;
        }
        this.previewTarget.classList.remove('hidden');
        Array.from(files).forEach((file) => {
            if (!file.type.startsWith('image/')) return;
            const url = URL.createObjectURL(file);
            const thumb = document.createElement('div');
            thumb.className = 'relative rounded-lg overflow-hidden border border-[var(--color-border-soft)] aspect-square bg-slate-100';
            thumb.innerHTML = `
                <img src="${url}" alt="" class="w-full h-full object-cover" onload="URL.revokeObjectURL(this.src)">
                <div class="absolute inset-x-0 bottom-0 px-2 py-1 bg-gradient-to-t from-black/70 to-transparent">
                    <div class="text-[10px] text-white truncate">${file.name}</div>
                </div>
                <div class="absolute top-1 left-1 px-1.5 py-0.5 rounded bg-[var(--color-primary)] text-white text-[10px] font-semibold uppercase tracking-wide">Nouveau</div>
            `;
            this.previewTarget.appendChild(thumb);
        });
    }
}
