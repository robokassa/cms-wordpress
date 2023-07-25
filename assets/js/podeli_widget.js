window.addEventListener('DOMContentLoaded', () => {

    // Pop-up script

    let modalTrigger = document.querySelector('#openModal'),
        modal = document.querySelector('.modal-wrapper'),
        modalCloseButton = document.querySelector('#closeModal'),
        scroll = calcScroll();

    modalTrigger.addEventListener('click', () => {
        modal.classList.add('show-modal');
        // document.body.style.overflow = 'hidden';
        // document.body.style.paddingRight = `${scroll}px`;
    });

    function closeModal() {
        modal.classList.remove('show-modal');
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

    modalCloseButton.addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.code === "Escape" && modal.classList.contains('show-modal')) {
            closeModal();
        }
    });

    // END Pop-up script
});

