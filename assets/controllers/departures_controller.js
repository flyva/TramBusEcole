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

        const icon = slide.mode === 'TRAM' ? '🚊' : '🚌';
        const columnsHtml = slide.columns.map((c) => this.renderColumn(c)).join('');

        this.slideTarget.innerHTML = `
            <div class="slide-title mode-${slide.mode.toLowerCase()}">
                <span class="icon">${icon}</span>
                <span class="name">${slide.title}</span>
            </div>
            <div class="columns">${columnsHtml}</div>
        `;
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
