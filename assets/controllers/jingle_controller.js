import { Controller } from '@hotwired/stimulus';

const CHECK_INTERVAL_MS = 3000;

/**
 * Polls for the admin's "test jingle" signal and plays a short chime
 * through the board's speakers when it changes. Synthesized with the Web
 * Audio API so no audio file needs hosting.
 *
 * Browser autoplay policies normally block audio with no user gesture;
 * Chromium must be launched with --autoplay-policy=no-user-gesture-required
 * for this to actually produce sound on the kiosk.
 */
export default class extends Controller {
    static values = { url: String };

    connect() {
        this.lastJingleAt = null;
        this.initialized = false;
        this.check();
        this.timer = setInterval(() => this.check(), CHECK_INTERVAL_MS);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    async check() {
        try {
            const response = await fetch(this.urlValue);
            if (!response.ok) return;
            const data = await response.json();

            if (!this.initialized) {
                // Don't play on first load just because a jingle was requested earlier.
                this.lastJingleAt = data.jingleAt;
                this.initialized = true;
                return;
            }

            if (data.jingleAt && data.jingleAt !== this.lastJingleAt) {
                this.lastJingleAt = data.jingleAt;
                this.play();
            }
        } catch (error) {
            console.error(error);
        }
    }

    play() {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;

        const ctx = new Ctx();
        const notes = [523.25, 659.25, 783.99, 1046.5]; // C5 E5 G5 C6
        const noteDuration = 0.16;

        notes.forEach((freq, i) => {
            const start = ctx.currentTime + i * noteDuration;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, start);
            gain.gain.linearRampToValueAtTime(0.3, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, start + noteDuration);

            osc.connect(gain).connect(ctx.destination);
            osc.start(start);
            osc.stop(start + noteDuration);
        });

        setTimeout(() => ctx.close(), (notes.length * noteDuration + 0.3) * 1000);
    }
}
