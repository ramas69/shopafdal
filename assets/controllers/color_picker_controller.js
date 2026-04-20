import { Controller } from '@hotwired/stimulus';

// Couleurs textiles courantes
const PRESETS = [
    ['#FFFFFF', 'Blanc'], ['#000000', 'Noir'], ['#6B7280', 'Gris'], ['#111827', 'Anthracite'],
    ['#1E3A8A', 'Marine'], ['#3B82F6', 'Bleu'], ['#06B6D4', 'Cyan'], ['#0D9488', 'Teal'],
    ['#16A34A', 'Vert'], ['#65A30D', 'Olive'], ['#CA8A04', 'Moutarde'], ['#F59E0B', 'Ambre'],
    ['#EA580C', 'Orange'], ['#DC2626', 'Rouge'], ['#BE123C', 'Bordeaux'], ['#DB2777', 'Rose'],
    ['#9333EA', 'Violet'], ['#7C2D12', 'Marron'], ['#D6A878', 'Beige'], ['#FCA5A5', 'Rose pâle'],
];

export default class extends Controller {
    static targets = ['input', 'trigger', 'swatch', 'panel', 'hex'];

    connect() {
        this._open = false;
        this._render();
        this._syncSwatch();
        this._onDocClick = (e) => {
            if (!this._open) return;
            if (this.element.contains(e.target)) return;
            this.close();
        };
        this._onKey = (e) => { if (e.key === 'Escape') this.close(); };
        document.addEventListener('click', this._onDocClick);
        document.addEventListener('keydown', this._onKey);
    }

    disconnect() {
        document.removeEventListener('click', this._onDocClick);
        document.removeEventListener('keydown', this._onKey);
    }

    toggle(event) {
        event.stopPropagation();
        this._open ? this.close() : this.open();
    }

    open() {
        this._open = true;
        this.panelTarget.classList.remove('hidden');
        this.hexTarget.value = this.inputTarget.value || '#FFFFFF';
    }

    close() {
        this._open = false;
        this.panelTarget.classList.add('hidden');
    }

    pick(event) {
        const hex = event.currentTarget.dataset.hex;
        this._set(hex);
        this.close();
    }

    inputHex(event) {
        let v = event.currentTarget.value.trim();
        if (v && !v.startsWith('#')) v = '#' + v;
        if (/^#[0-9a-fA-F]{6}$/.test(v)) this._set(v);
    }

    _set(hex) {
        this.inputTarget.value = hex.toUpperCase();
        this.hexTarget.value = hex.toUpperCase();
        this._syncSwatch();
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    _syncSwatch() {
        this.swatchTarget.style.backgroundColor = this.inputTarget.value || '#FFFFFF';
    }

    _render() {
        const grid = PRESETS.map(([hex, name]) => `
            <button type="button" data-action="click->color-picker#pick" data-hex="${hex}"
                    title="${name}"
                    style="width:28px;height:28px;border-radius:6px;border:1px solid var(--color-border);background-color:${hex};cursor:pointer;transition:box-shadow .15s;"
                    onmouseover="this.style.boxShadow='0 0 0 2px var(--color-primary)'"
                    onmouseout="this.style.boxShadow=''"></button>
        `).join('');
        this.panelTarget.innerHTML = `
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-bottom:12px;">${grid}</div>
            <label style="display:block;font-size:10px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;color:var(--color-secondary);margin-bottom:4px;">Hex personnalisé</label>
            <input type="text" data-color-picker-target="hex" data-action="input->color-picker#inputHex"
                   placeholder="#FFFFFF" maxlength="7"
                   style="width:100%;padding:6px 8px;border:1px solid var(--color-border);border-radius:6px;font-family:ui-monospace,SFMono-Regular,monospace;font-size:12px;">
        `;
    }
}
