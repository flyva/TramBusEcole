import { Controller } from '@hotwired/stimulus';

/**
 * Pick a destination chip, then tap days in the grid to assign/clear it in
 * place (no page reload) - the fast way to bulk-fill a month.
 */
export default class extends Controller {
    static targets = ['chip', 'cell'];
    static values = { url: String };

    connect() {
        this.selected = null;
    }

    pick(event) {
        const chip = event.currentTarget;

        if (this.selected && this.selected.id === chip.dataset.presetId) {
            this.selected = null;
        } else {
            this.selected = { id: chip.dataset.presetId, name: chip.dataset.presetName || '' };
        }

        this.chipTargets.forEach((c) => c.classList.toggle('active', this.selected !== null && c.dataset.presetId === this.selected.id));
    }

    async paint(event) {
        if (!this.selected) return;
        const cell = event.currentTarget;
        if (cell.classList.contains('saving')) return;

        const date = cell.dataset.date;
        const presetId = this.selected.id === '' ? null : this.selected.id;

        cell.classList.add('saving');
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ date, presetId }),
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();

            const label = cell.querySelector('.day-preset');
            if (data.preset) {
                label.textContent = data.preset.name;
                label.hidden = false;
                cell.dataset.presetId = data.preset.id;
            } else {
                label.textContent = '';
                label.hidden = true;
                cell.dataset.presetId = '';
            }
        } catch (error) {
            console.error(error);
        } finally {
            cell.classList.remove('saving');
        }
    }
}
