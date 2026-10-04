import { Controller } from '@hotwired/stimulus';

/**
 * Generic toggle/delete-in-place helper for admin list rows (screen-off
 * periods, slides): posts to the button's data-url, then updates or removes
 * the row without a full page reload.
 */
export default class extends Controller {
    async toggle(event) {
        const btn = event.currentTarget;
        const row = btn.closest('[data-period-row], [data-slide-row]');
        if (!row || btn.disabled) return;

        btn.disabled = true;
        try {
            const response = await fetch(btn.dataset.url, { method: 'POST' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            const active = data.enabled ?? data.active;

            row.classList.toggle('inactive', !active);
            const small = row.querySelector('.caption small');
            if (small) small.textContent = active ? 'Active' : 'Désactivée';
            btn.textContent = active ? 'Désactiver' : 'Activer';
        } catch (error) {
            console.error(error);
        } finally {
            btn.disabled = false;
        }
    }

    async delete(event) {
        const btn = event.currentTarget;
        const row = btn.closest('[data-period-row], [data-slide-row]');
        if (!row || btn.disabled) return;

        if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;

        btn.disabled = true;
        try {
            const response = await fetch(btn.dataset.url, { method: 'POST' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            row.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            row.style.opacity = '0';
            row.style.transform = 'scale(0.97)';
            setTimeout(() => {
                const list = row.parentElement;
                row.remove();

                const kind = btn.dataset.removeTarget || 'period';
                const countEl = document.getElementById(`${kind}-count`);
                if (countEl) countEl.textContent = String(Math.max(0, parseInt(countEl.textContent, 10) - 1));

                if (list && list.children.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'empty';
                    empty.id = `${kind}-empty`;
                    empty.textContent = kind === 'slide' ? 'Aucune image ajoutée pour l\'instant.' : 'Aucune plage d\'extinction. L\'écran reste toujours allumé.';
                    list.appendChild(empty);
                }
            }, 200);
        } catch (error) {
            console.error(error);
            btn.disabled = false;
        }
    }
}
