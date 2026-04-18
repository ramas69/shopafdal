import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['backdrop', 'dialog', 'title', 'backBtn', 'listPanel', 'detailPanel', 'grid', 'search', 'empty', 'data', 'detailContent', 'subtotal'];
    static values = { actionUrl: String };

    connect() {
        this.products = [];
        try {
            this.products = JSON.parse(this.dataTarget.textContent || '[]');
        } catch (_) {
            this.products = [];
        }
        this._renderGrid(this.products);
        this._onEscape = this._onEscape.bind(this);
    }

    open() {
        this.backdropTarget.style.display = 'flex';
        this.backdropTarget.classList.remove('hidden');
        this._showList();
        document.addEventListener('keydown', this._onEscape);
        document.body.style.overflow = 'hidden';
        setTimeout(() => this.searchTarget?.focus(), 50);
    }

    close() {
        this.backdropTarget.style.display = 'none';
        this.backdropTarget.classList.add('hidden');
        document.removeEventListener('keydown', this._onEscape);
        document.body.style.overflow = '';
    }

    backdropClick(event) {
        if (event.target === this.backdropTarget) this.close();
    }

    _onEscape(e) { if (e.key === 'Escape') this.close(); }

    filter(event) {
        const q = (event.target.value || '').toLowerCase().trim();
        const filtered = q.length === 0
            ? this.products
            : this.products.filter(p =>
                p.name.toLowerCase().includes(q)
                || (p.category || '').toLowerCase().includes(q));
        this._renderGrid(filtered);
    }

    showList() { this._showList(); }

    _showList() {
        this.listPanelTarget.classList.remove('hidden');
        this.detailPanelTarget.classList.add('hidden');
        this.backBtnTarget.classList.add('hidden');
        this.titleTarget.textContent = 'Ajouter un article';
    }

    selectProduct(event) {
        const id = parseInt(event.currentTarget.dataset.productId, 10);
        const product = this.products.find(p => p.id === id);
        if (!product) return;
        this._renderDetail(product);
        this.listPanelTarget.classList.add('hidden');
        this.detailPanelTarget.classList.remove('hidden');
        this.backBtnTarget.classList.remove('hidden');
        this.backBtnTarget.classList.add('inline-flex');
        this.titleTarget.textContent = product.name;
    }

    _renderGrid(products) {
        if (products.length === 0) {
            this.gridTarget.innerHTML = '';
            this.emptyTarget.classList.remove('hidden');
            return;
        }
        this.emptyTarget.classList.add('hidden');
        this.gridTarget.innerHTML = products.map(p => `
            <button type="button"
                    data-action="click->add-article#selectProduct"
                    data-product-id="${p.id}"
                    class="group card block overflow-hidden text-left cursor-pointer transition-all hover:border-[var(--color-primary)] hover:shadow-[var(--shadow-md)]">
                <div class="aspect-[4/3] bg-gradient-to-br from-[var(--color-muted)] to-[var(--color-border)] overflow-hidden flex items-center justify-center">
                    ${p.image
                        ? `<img src="${this._escape(p.image)}" alt="" class="w-full h-full object-cover">`
                        : `<span class="font-display text-5xl font-bold text-white/80" style="-webkit-text-stroke:2px rgba(0,0,0,0.15)">${this._escape(p.name[0].toUpperCase())}</span>`}
                </div>
                <div class="p-3">
                    ${p.category ? `<div class="text-[10px] font-medium text-[var(--color-secondary)] uppercase tracking-wide mb-0.5">${this._escape(p.category)}</div>` : ''}
                    <div class="font-medium text-sm text-[var(--color-foreground)] line-clamp-1">${this._escape(p.name)}</div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs text-[var(--color-secondary)]">${p.variants.length} variantes</span>
                        <span class="font-semibold text-sm text-[var(--color-primary)]">${this._formatPrice(p.price)}</span>
                    </div>
                </div>
            </button>
        `).join('');
    }

    _renderDetail(product) {
        // Group by color
        const variantsByColor = {};
        for (const v of product.variants) {
            if (!variantsByColor[v.color]) variantsByColor[v.color] = { hex: v.hex, sizes: {} };
            variantsByColor[v.color].sizes[v.size] = v;
        }
        // Collect unique sorted sizes
        const sizeOrder = ['TU', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        const sizesSet = new Set();
        for (const group of Object.values(variantsByColor)) {
            for (const s of Object.keys(group.sizes)) sizesSet.add(s);
        }
        const allSizes = Array.from(sizesSet).sort((a, b) => {
            const ia = sizeOrder.indexOf(a), ib = sizeOrder.indexOf(b);
            return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib);
        });

        this.detailContentTarget.innerHTML = `
            <div class="flex items-start gap-4 mb-5">
                <div class="w-20 h-20 shrink-0 rounded-lg overflow-hidden bg-gradient-to-br from-[var(--color-muted)] to-[var(--color-border)] flex items-center justify-center">
                    ${product.image
                        ? `<img src="${this._escape(product.image)}" alt="" class="w-full h-full object-cover">`
                        : `<span class="font-display text-2xl font-bold text-white">${this._escape(product.name[0].toUpperCase())}</span>`}
                </div>
                <div class="flex-1">
                    <div class="text-xs uppercase tracking-wide text-[var(--color-secondary)]">${this._escape(product.category || 'Produit')}</div>
                    <div class="font-display font-semibold text-lg text-[var(--color-foreground)]">${this._escape(product.name)}</div>
                    <div class="text-sm text-[var(--color-primary)] font-semibold">${this._formatPrice(product.price)} HT / pièce</div>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase tracking-wide text-[var(--color-secondary)]">Sous-total</div>
                    <div class="font-display font-semibold text-lg text-[var(--color-primary)]" data-add-article-target="subtotal">0,00 €</div>
                </div>
            </div>

            <div class="mb-5 overflow-x-auto">
                <table class="w-full border-separate" style="border-spacing: 0 0.5rem">
                    <thead>
                        <tr>
                            <th class="text-left text-[11px] uppercase font-semibold text-[var(--color-secondary)] tracking-wide px-3 py-1">Couleur</th>
                            ${allSizes.map(s => `<th class="text-center text-[11px] uppercase font-semibold text-[var(--color-secondary)] tracking-wide px-1 py-1">${this._escape(s)}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${Object.entries(variantsByColor).map(([color, group]) => `
                            <tr>
                                <td class="px-3 py-1 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="w-4 h-4 rounded-full border border-[var(--color-border)] shrink-0" style="background-color:${this._escape(group.hex || '#fff')}"></span>
                                        <span class="text-sm font-medium text-[var(--color-foreground)]">${this._escape(color)}</span>
                                    </div>
                                </td>
                                ${allSizes.map(s => {
                                    const v = group.sizes[s];
                                    if (!v) return '<td class="px-1 py-1 text-center text-[var(--color-secondary)]/40">—</td>';
                                    return `<td class="px-1 py-1">
                                        <input type="number" name="quantities[${v.id}]" min="0" value="0" step="1"
                                               data-action="input->add-article#recomputeSubtotal"
                                               class="form-input !w-16 text-center font-semibold">
                                    </td>`;
                                }).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-4 border-t border-[var(--color-border-soft)]">
                <label class="block">
                    <span class="form-label">Marquage (zone)</span>
                    <select name="marking_zone" class="form-input mt-1.5">
                        <option value="">Aucun</option>
                        <option value="poitrine">Poitrine</option>
                        <option value="dos">Dos</option>
                        <option value="manche">Manche</option>
                    </select>
                </label>
                <label class="block">
                    <span class="form-label">Taille marquage</span>
                    <select name="marking_size" class="form-input mt-1.5">
                        <option value="A6">A6</option>
                        <option value="A5">A5</option>
                        <option value="A4" selected>A4</option>
                        <option value="A3">A3</option>
                    </select>
                </label>
            </div>
        `;

        // Expose product price for subtotal computation
        this._currentProductPrice = product.price;
        this.detailPanelTarget.dataset.productPrice = String(product.price);
    }

    recomputeSubtotal() {
        const price = this._currentProductPrice ?? 0;
        let qty = 0;
        this.detailPanelTarget.querySelectorAll('input[type="number"][name^="quantities["]').forEach(i => {
            qty += Math.max(0, parseInt(i.value || '0', 10));
        });
        const subtotalEl = this.detailPanelTarget.querySelector('[data-add-article-target="subtotal"]');
        if (subtotalEl) {
            subtotalEl.textContent = this._formatPrice(price * qty);
        }
    }

    _formatPrice(cents) {
        return (cents / 100).toFixed(2).replace('.', ',') + ' €';
    }

    _escape(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }
}
