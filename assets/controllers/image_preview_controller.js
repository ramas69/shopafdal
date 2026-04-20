import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview'];

    connect() {
        this._files = [];
    }

    show() {
        const picked = Array.from(this.inputTarget.files || []);
        picked.forEach((f) => {
            if (!f.type.startsWith('image/')) return;
            // Dédoublonne par nom+taille pour éviter les doublons si l'user re-sélectionne les mêmes
            const dup = this._files.find((x) => x.name === f.name && x.size === f.size);
            if (!dup) this._files.push(f);
        });
        this._sync();
        this._render();
    }

    remove(event) {
        const idx = Number(event.currentTarget.dataset.idx);
        this._files.splice(idx, 1);
        this._sync();
        this._render();
    }

    _sync() {
        const dt = new DataTransfer();
        this._files.forEach((f) => dt.items.add(f));
        this.inputTarget.files = dt.files;
    }

    _render() {
        this.previewTarget.innerHTML = '';
        if (this._files.length === 0) {
            this.previewTarget.classList.add('hidden');
            return;
        }
        this.previewTarget.classList.remove('hidden');
        this._files.forEach((file, idx) => {
            const url = URL.createObjectURL(file);
            const thumb = document.createElement('div');
            thumb.className = 'relative rounded-lg overflow-hidden border border-[var(--color-border-soft)] aspect-square bg-slate-100';
            thumb.innerHTML = `
                <img src="${url}" alt="" class="w-full h-full object-cover">
                <div class="absolute inset-x-0 bottom-0 px-2 py-1 bg-gradient-to-t from-black/70 to-transparent">
                    <div class="text-[10px] text-white truncate">${file.name}</div>
                </div>
                <div class="absolute top-1 left-1 px-1.5 py-0.5 rounded bg-[var(--color-primary)] text-white text-[10px] font-semibold uppercase tracking-wide">Nouveau</div>
                <button type="button" data-action="click->image-preview#remove" data-idx="${idx}"
                        class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 hover:bg-black/80 text-white flex items-center justify-center"
                        aria-label="Retirer l'image">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            `;
            const img = thumb.querySelector('img');
            img.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
            this.previewTarget.appendChild(thumb);
        });
    }
}
