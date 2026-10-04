import { Controller } from '@hotwired/stimulus';

const DATA_REFRESH_MS = 20000;
const SLIDE_DURATION_MS = 5000;
const CLOCK_TICK_MS = 1000;

export default class extends Controller {
    static targets = ['slide', 'dots', 'clock'];
    static values = { url: String };

    connect() {
        this.slides = [];
        this.currentIndex = 0;

        this.fetchData();
        this.dataTimer = setInterval(() => this.fetchData(), DATA_REFRESH_MS);
        this.slideTimer = setInterval(() => this.nextSlide(), SLIDE_DURATION_MS);
        this.tickClock();
        this.clockTimer = setInterval(() => this.tickClock(), CLOCK_TICK_MS);
    }

    disconnect() {
        clearInterval(this.dataTimer);
        clearInterval(this.slideTimer);
        clearInterval(this.clockTimer);
    }

    tickClock() {
        this.clockTarget.textContent = new Date().toLocaleTimeString('fr-FR');
    }

    async fetchData() {
        try {
            const response = await fetch(this.urlValue);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = await response.json();
            this.slides = data.slides || [];
            if (this.currentIndex >= this.slides.length) {
                this.currentIndex = 0;
            }
            this.renderDots();
            this.renderSlide();
        } catch (error) {
            console.error(error);
        }
    }

    nextSlide() {
        if (this.slides.length === 0) {
            return;
        }
        this.currentIndex = (this.currentIndex + 1) % this.slides.length;
        this.renderDots();
        this.renderSlide();
    }

    renderDots() {
        this.dotsTarget.innerHTML = this.slides
            .map((_, i) => `<span class="dot${i === this.currentIndex ? ' active' : ''}"></span>`)
            .join('');
    }

    renderSlide() {
        const slide = this.slides[this.currentIndex];
        if (!slide) {
            this.slideTarget.innerHTML = '<p class="loading">Chargement…</p>';
            return;
        }

        if (slide.type === 'image') {
            this.slideTarget.innerHTML = this.renderImageSlide(slide);
            return;
        }

        if (slide.type === 'itinerary') {
            this.slideTarget.innerHTML = this.renderItinerarySlide(slide);
            return;
        }

        if (slide.type === 'bikes') {
            this.slideTarget.innerHTML = this.renderBikesSlide(slide);
            return;
        }

        const icon = slide.mode === 'TRAM' ? '🚊' : '🚌';
        const columnsHtml = slide.columns.map((c) => this.renderColumn(c)).join('');

        this.slideTarget.innerHTML = `
            <div class="slide-title mode-${slide.mode.toLowerCase()}">
                <span class="icon">${icon}</span>
                <span class="name">${slide.title}</span>
            </div>
            ${this.renderAlerts(slide.alerts)}
            <div class="columns">${columnsHtml}</div>
        `;
    }

    renderItinerarySlide(slide) {
        const primary = slide.primary;
        const metaHtml = `
            <div class="itinerary-meta">
                ${slide.drivingMinutes !== null ? `<span>🚗 ${slide.drivingMinutes} min</span>` : ''}
                <span>🚊 ~${slide.transitEstimateMinutes} min <small>(estimation)</small></span>
            </div>
        `;

        const primaryHtml = primary
            ? `
                <div class="destination">→ ${primary.stopLibelle}</div>
                ${primary.ligne ? `<span class="ligne">${primary.ligne}</span>` : ''}
                ${this.renderAlerts(primary.alerts)}
                ${primary.passages.map((p, i) => this.renderDeparture(p, i + 1)).join('')}
            `
            : '<p class="empty">Aucune ligne TBM trouvée à proximité</p>';

        const nearbyHtml = (slide.nearby || []).length
            ? slide.nearby.map((n) => `
                <div class="itinerary-nearby-item">
                    ${n.ligne ? `<span class="ligne">${n.ligne}</span>` : ''}
                    <span class="destination">${n.stopLibelle}</span>
                    ${n.passages[0] ? `<span class="departure-wait">${n.passages[0].attenteMinutes}<span class="unit">min</span></span>` : ''}
                </div>
            `).join('')
            : '<p class="empty">Pas d\'autre ligne proche</p>';

        return `
            <div class="slide-title mode-itinerary">
                <span class="icon">🧭</span>
                <span class="name">${slide.presetName}</span>
            </div>
            <div class="columns">
                <div class="column itinerary-left">
                    ${primaryHtml}
                    ${metaHtml}
                </div>
                <div class="column itinerary-right">
                    <div class="itinerary-qr">
                        <img src="${slide.qrCodeUrl}" alt="QR code itinéraire">
                        <div class="itinerary-address">${slide.address}</div>
                    </div>
                    ${nearbyHtml}
                </div>
            </div>
        `;
    }

    renderBikesSlide(slide) {
        const rows = slide.stations.map((s) => `
            <div class="bike-row">
                <span class="bike-name">${s.nom}</span>
                <span class="bike-count">🚲 ${s.velos}</span>
                <span class="bike-count">⚡ ${s.elec}</span>
                <span class="bike-count">🅿️ ${s.places}</span>
            </div>
        `).join('');

        return `
            <div class="slide-title mode-bikes">
                <span class="icon">🚲</span>
                <span class="name">${slide.title}</span>
            </div>
            <div class="column bikes-column">${rows}</div>
        `;
    }

    renderImageSlide(slide) {
        const caption = slide.caption ? `<div class="image-caption">${slide.caption}</div>` : '';

        return `
            <div class="image-slide">
                <img src="${slide.imageUrl}" alt="${slide.caption || ''}">
                ${caption}
            </div>
        `;
    }

    severityRank(severite) {
        const m = String(severite).match(/^(\d+)/);
        return m ? parseInt(m[1], 10) : 1;
    }

    renderAlerts(alerts) {
        if (!alerts || alerts.length === 0) {
            return '';
        }
        const sorted = [...alerts].sort((a, b) => this.severityRank(b.severite) - this.severityRank(a.severite));
        const topRank = this.severityRank(sorted[0].severite);
        const level = topRank >= 3 ? 'danger' : topRank === 2 ? 'warning' : 'info';
        const text = sorted.map((a) => a.titre).join('  •  ');

        return `<div class="alert alert--${level}"><span class="alert-icon">⚠️</span><span class="alert-text">${text}</span></div>`;
    }

    renderColumn(column) {
        if (column.passages.length === 0) {
            return `<div class="column"><p class="empty">Aucun passage prévu</p></div>`;
        }

        const rows = column.passages.map((p, i) => this.renderDeparture(p, i + 1)).join('');

        return `<div class="column">${rows}</div>`;
    }

    renderDeparture(departure, rank) {
        const destination = departure.destination || '?';
        const isImminent = departure.attenteMinutes <= 0;
        const wait = isImminent ? 'Proche' : `${departure.attenteMinutes}<span class="unit">min</span>`;

        return `
            <div class="departure rank-${rank}${isImminent ? ' departure--imminent' : ''}">
                <div class="destination">→ ${destination}</div>
                <div class="departure-wait">${wait}</div>
                <div class="departure-time">${departure.heure}</div>
            </div>
        `;
    }
}
