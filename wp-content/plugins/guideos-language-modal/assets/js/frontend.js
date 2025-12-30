(function() {
    'use strict';

    // Session storage key
    const STORAGE_KEY = 'golm_modal_closed';

    /**
     * Check if user's browser language is German
     * Detects: de, de-DE, de-CH, de-AT, etc.
     */
    function isGermanLanguage() {
        const language = navigator.language || navigator.userLanguage;

        if (!language) {
            return false;
        }

        // Convert to lowercase for comparison
        const lang = language.toLowerCase();

        // Check if it starts with 'de' (covers de, de-de, de-ch, de-at, etc.)
        return lang.startsWith('de');
    }

    /**
     * Check if modal was already closed in this session
     */
    function wasModalClosed() {
        try {
            return sessionStorage.getItem(STORAGE_KEY) === 'true';
        } catch (e) {
            // SessionStorage not available
            return false;
        }
    }

    /**
     * Mark modal as closed in session storage
     */
    function markModalAsClosed() {
        try {
            sessionStorage.setItem(STORAGE_KEY, 'true');
        } catch (e) {
            // SessionStorage not available, fail silently
            console.warn('SessionStorage not available');
        }
    }

    /**
     * Show the modal
     */
    function showModal() {
        const modal = document.getElementById('golm-modal-overlay');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Hide the modal
     */
    function hideModal() {
        const modal = document.getElementById('golm-modal-overlay');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            markModalAsClosed();
        }
    }

    /**
     * Initialize modal functionality
     */
    function initModal() {
        // Check if we should show the modal
        if (isGermanLanguage()) {
            console.log('German language detected - modal will not be shown');
            return;
        }

        if (wasModalClosed()) {
            console.log('Modal was already closed in this session');
            return;
        }

        // Show modal after a short delay to ensure page is loaded
        setTimeout(function() {
            showModal();
        }, 500);

        // Attach close button handlers
        const closeButton = document.getElementById('golm-close-button');
        const closeFooterButton = document.getElementById('golm-close-footer-button');
        const overlay = document.getElementById('golm-modal-overlay');

        if (closeButton) {
            closeButton.addEventListener('click', function(e) {
                e.preventDefault();
                hideModal();
            });
        }

        if (closeFooterButton) {
            closeFooterButton.addEventListener('click', function(e) {
                e.preventDefault();
                hideModal();
            });
        }

        // Close on overlay click (outside modal content)
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    hideModal();
                }
            });
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                hideModal();
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModal);
    } else {
        initModal();
    }

})();
