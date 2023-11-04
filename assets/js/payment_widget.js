window.addEventListener('DOMContentLoaded', () => {

    // Pop-up script

    let modalTriggers = document.querySelectorAll('.wiget-action-button');
    let podeliModalTriggers = document.querySelectorAll('.podeli-action-button');
    let podeliModalTriggers2 = document.querySelectorAll('.podeli-action-button2');

    let modals = document.querySelectorAll('.rb-modal-wrapper');
    let podeliModals = document.querySelectorAll('.podeli-wrapper');

    let scroll = calcScroll();

    modalTriggers.forEach((modalTrigger, index) => {
        modalTrigger.addEventListener('click', () => {
            modals[index].classList.add('rb-show-modal');
            // document.body.style.overflow = 'hidden';
            // document.body.style.paddingRight = `${scroll}px`;
        });
    });

    podeliModalTriggers.forEach((modalTrigger, index) => {
        modalTrigger.addEventListener('click', () => {
            podeliModals[index].classList.add('rb-show-modal');
            // document.body.style.overflow = 'hidden';
            // document.body.style.paddingRight = `${scroll}px`;
        });
    });

    podeliModalTriggers2.forEach((modalTrigger, index) => {
        modalTrigger.addEventListener('click', () => {
            podeliModals[index].classList.add('rb-show-modal');
            // document.body.style.overflow = 'hidden';
            // document.body.style.paddingRight = `${scroll}px`;
        });
    });

    function closeModal() {
        modals.forEach((modal) => {
            modal.classList.remove('rb-show-modal');
        });
        podeliModals.forEach((modal) => {
            modal.classList.remove('rb-show-modal');
        });
        // document.body.style.overflow = '';
        // document.body.style.paddingRight = '0px';
    };

    function calcScroll() {
        let div = document.createElement('div');
        div.style.width = '50px';
        div.style.height = '50px';
        div.style.overflowY = 'scroll';
        div.style.visibility = 'hidden';

        document.body.appendChild(div);
        let scrollWidth = div.offsetWidth - div.clientWidth;
        div.remove();

        return scrollWidth;
    };

    let modalCloseButtons = document.querySelectorAll('.close-modal');
    modalCloseButtons.forEach((modalCloseButton) => {
        modalCloseButton.addEventListener('click', closeModal);
    });

    modals.forEach((modal) => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    });

    podeliModals.forEach((modal) => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.code === "Escape") {
            closeModal();
        }
    });

    // END Pop-up script

});

function toggleActive(element) {
    const parent = element.parentElement;
    const items = parent.getElementsByClassName("active");
    for (const item of items) {
        item.classList.remove("active");
    }

    element.classList.add("active");
}
