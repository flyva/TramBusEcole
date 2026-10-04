import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'status'];
    static values = { url: String };

    async trigger() {
        this.buttonTarget.disabled = true;
        this.statusTarget.textContent = 'Envoi…';
        try {
            const response = await fetch(this.urlValue, { method: 'POST' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            this.statusTarget.textContent = '🔔 Signal envoyé, ça sonne sur le tableau dans quelques secondes.';
        } catch (error) {
            console.error(error);
            this.statusTarget.textContent = "Échec de l'envoi.";
        } finally {
            this.buttonTarget.disabled = false;
            setTimeout(() => { this.statusTarget.textContent = ''; }, 4000);
        }
    }
}
