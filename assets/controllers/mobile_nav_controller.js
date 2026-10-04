import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel'];

    toggle() {
        this.panelTarget.classList.toggle('open');
    }

    close() {
        this.panelTarget.classList.remove('open');
    }
}
