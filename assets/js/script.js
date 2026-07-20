(function() {
    'use strict';

    class MFB {
        constructor(container) {
            this.container = container;
            this.mainBtn = container.querySelector('.mfb-main-btn');
            this.buttonsContainer = container.querySelector('.mfb-buttons-container');
            this.isOpen = false;
            this.init();
        }

        init() {
            if (!this.mainBtn || !this.buttonsContainer) {
                return;
            }

            this.bindEvents();
            this.preloadImages();
        }

        bindEvents() {
            this.mainBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleMenu();
            });

            const buttons = this.container.querySelectorAll('.mfb-btn');
            buttons.forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const link = btn.getAttribute('data-link');
                    if (link) {
                        if (link.indexOf('tel:') === 0) {
                            window.location.href = link;
                        } else {
                            window.open(link, '_blank', 'noopener,noreferrer');
                        }
                    }
                    this.closeMenu();
                });
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-mfb="true"]') && this.isOpen) {
                    this.closeMenu();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.closeMenu();
                    this.mainBtn.focus();
                }
            });
        }

        toggleMenu() {
            this.isOpen ? this.closeMenu() : this.openMenu();
        }

        openMenu() {
            this.container.classList.add('mfb-open');
            this.buttonsContainer.classList.add('active');
            this.mainBtn.classList.add('active');
            this.mainBtn.setAttribute('aria-expanded', 'true');
            this.isOpen = true;
        }

        closeMenu() {
            this.container.classList.remove('mfb-open');
            this.buttonsContainer.classList.remove('active');
            this.mainBtn.classList.remove('active');
            this.mainBtn.setAttribute('aria-expanded', 'false');
            this.isOpen = false;
        }

        preloadImages() {
            const btnImages = this.container.querySelectorAll('img');
            btnImages.forEach((image) => {
                if (!image.src) {
                    return;
                }
                const preload = new Image();
                preload.src = image.src;
            });
        }
    }

    function initMFB() {
        const containers = document.querySelectorAll('[data-mfb="true"]:not([data-mfb-initialized="true"])');
        containers.forEach((container) => {
            container.setAttribute('data-mfb-initialized', 'true');
            new MFB(container);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMFB);
    } else {
        initMFB();
    }
})();
