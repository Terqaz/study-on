import {Controller} from 'stimulus';
import {Modal} from 'bootstrap';

export default class extends Controller {
    static targets = ['modal'];
    static values = {
        isShown: Boolean
    }

    connect()
    {
        if (this.isShownValue) {
            this.#openModal();
        }
    }

    openModal(event)
    {
        this.#openModal();
    }

    #openModal()
    {
        const modal = new Modal(this.modalTarget);
        modal.show();
    }
}
