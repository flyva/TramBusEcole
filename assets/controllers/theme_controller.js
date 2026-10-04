import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'admin-theme';

export default class extends Controller {
    static targets = ['icon'];

    connect() {
        const saved = this.readSaved();
        if (saved) {
            document.documentElement.setAttribute('data-theme', saved);
        }
        this.updateIcon();
    }

    toggle() {
        const current = document.documentElement.getAttribute('data-theme') || this.systemPreference();
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Private browsing or storage disabled: theme just won't persist across reloads.
        }
        this.updateIcon();
    }

    updateIcon() {
        if (!this.hasIconTarget) return;
        const current = document.documentElement.getAttribute('data-theme') || this.systemPreference();
        this.iconTarget.textContent = current === 'dark' ? '☀️' : '🌙';
    }

    systemPreference() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    readSaved() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch {
            return null;
        }
    }
}
