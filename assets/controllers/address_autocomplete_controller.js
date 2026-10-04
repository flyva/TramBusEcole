import { Controller } from '@hotwired/stimulus';

const DEBOUNCE_MS = 300;
const BAN_API = 'https://api-adresse.data.gouv.fr/search/';

export default class extends Controller {
    static targets = ['input', 'lat', 'lng', 'suggestions'];

    connect() {
        this.timer = null;
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    onInput() {
        clearTimeout(this.timer);
        const query = this.inputTarget.value.trim();

        if (query.length < 3) {
            this.clearSuggestions();
            return;
        }

        this.timer = setTimeout(() => this.search(query), DEBOUNCE_MS);
    }

    async search(query) {
        try {
            const url = `${BAN_API}?q=${encodeURIComponent(query)}&limit=5`;
            const response = await fetch(url);
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            this.renderSuggestions(data.features || []);
        } catch (error) {
            console.error(error);
        }
    }

    renderSuggestions(features) {
        if (features.length === 0) {
            this.clearSuggestions();
            return;
        }

        this.suggestionsTarget.innerHTML = features
            .map((f, i) => `<li data-index="${i}">${f.properties.label}</li>`)
            .join('');
        this.suggestionsTarget.hidden = false;
        this.features = features;

        this.suggestionsTarget.querySelectorAll('li').forEach((li) => {
            li.addEventListener('click', () => this.select(parseInt(li.dataset.index, 10)));
        });
    }

    select(index) {
        const feature = this.features[index];
        if (!feature) {
            return;
        }

        this.inputTarget.value = feature.properties.label;
        this.latTarget.value = feature.geometry.coordinates[1];
        this.lngTarget.value = feature.geometry.coordinates[0];
        this.clearSuggestions();
    }

    clearSuggestions() {
        this.suggestionsTarget.innerHTML = '';
        this.suggestionsTarget.hidden = true;
    }
}
