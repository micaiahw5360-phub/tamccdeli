/*
 * TAMCC Deli – Main JavaScript (Normal Mode)
 * Enhanced: fixed cart count, AJAX add to cart, modal handling, toast improvements.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    if (menuToggle) {
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.addEventListener('click', () => {
            const expanded = navLinks.classList.contains('show') ? 'false' : 'true';
            navLinks.classList.toggle('show');
            menuToggle.setAttribute('aria-expanded', expanded);
            if (expanded === 'true') {
                const firstLink = navLinks.querySelector('a');
                if (firstLink) firstLink.focus();
            } else {
                menuToggle.focus();
            }
        });
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            if (navLinks && navLinks.classList.contains('show')) {
                navLinks.classList.remove('show');
                if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });

    // Smooth scroll for category links (dropdown)
    const categoryLinks = document.querySelectorAll('.dropdown-content a');
    categoryLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (window.location.pathname.includes('menu.php')) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });

    // If page loads with a hash, scroll to it
    if (window.location.pathname.includes('menu.php') && window.location.hash) {
        const targetId = window.location.hash.substring(1);
        const target = document.getElementById(targetId);
        if (target) {
            setTimeout(() => {
                target.scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }
    }

    // Live menu search
    const searchInput = document.getElementById('menu-search');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const term = this.value.toLowerCase().trim();
                document.querySelectorAll('.menu-item').forEach(item => {
                    const name = item.querySelector('.menu-item-name')?.textContent.toLowerCase() || '';
                    item.style.display = name.includes(term) ? '' : 'none';
                });
            }, 300);
        });
    }

    // Category filter buttons (regular menu)
    const filterButtons = document.querySelectorAll('.filter-btn');
    const categories = document.querySelectorAll('.category');
    if (filterButtons.length && categories.length) {
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const selectedCat = this.dataset.category;
                if (selectedCat === 'all') {
                    categories.forEach(cat => cat.style.display = 'block');
                } else {
                    categories.forEach(cat => {
                        cat.style.display = cat.id === selectedCat ? 'block' : 'none';
                    });
                }
            });
        });
    }

    // Dropdown click toggle for touch devices
    if ('ontouchstart' in window) {
        document.querySelectorAll('.dropdown > a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = this.parentElement;
                dropdown.classList.toggle('active');
            });
        });
    }

    // Close mobile dropdown after selecting a link
    document.querySelectorAll('.dropdown-content a').forEach(link => {
        link.addEventListener('click', () => {
            const dropdown = link.closest('.dropdown');
            if (dropdown && window.innerWidth <= 768) {
                dropdown.classList.remove('active');
            }
        });
    });

    // Auto‑dismiss alerts
    document.querySelectorAll('.error, .success').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    // Helper to build URL with kiosk parameter if needed
    function kioskUrl(baseUrl) {
        if (typeof kioskMode !== 'undefined' && kioskMode) {
            const separator = baseUrl.includes('?') ? '&' : '?';
            return baseUrl + separator + 'kiosk=1';
        }
        return baseUrl;
    }

    // Generic fetch with JSON validation
    async function fetchJson(url, options = {}) {
        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error(`HTTP error ${response.status}`);
        }
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response from:', url);
            console.error('Response starts with:', text.substring(0, 200));
            throw new Error('Response is not JSON');
        }
    }

    // AJAX Add to Cart (enhanced)
    document.querySelectorAll('.add-to-cart-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            const formData = new FormData(this);
            try {
                const url = kioskUrl('/cart.php?action=add');
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Network error');
                const result = await response.json();
                if (result.success) {
                    showToast('Item added to cart!', 'success');
                    updateCartCount();
                } else {
                    showToast(result.error || 'Error adding item', 'error');
                }
            } catch (error) {
                console.error('Add to cart error:', error);
                showToast('Failed to add item. Please try again.', 'error');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    });

    // Update cart count (used in header and kiosk floating cart)
    async function updateCartCount() {
        try {
            const url = kioskUrl('/get-cart-count.php');
            const data = await fetchJson(url);
            const countSpan = document.getElementById('cart-count');
            if (countSpan) {
                countSpan.textContent = data.count;
                countSpan.style.transform = 'scale(1.2)';
                setTimeout(() => countSpan.style.transform = 'scale(1)', 200);
            }
            const kioskCountSpan = document.getElementById('cart-count-kiosk');
            if (kioskCountSpan) {
                kioskCountSpan.textContent = data.count;
            }
        } catch (error) {
            console.error('Failed to update cart count:', error);
        }
    }
    updateCartCount();

    // Scroll to top button
    const scrollButton = document.getElementById('scroll-to-top');
    if (scrollButton) {
        scrollButton.setAttribute('aria-label', 'Scroll to top');
        scrollButton.setAttribute('title', 'Scroll to top');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollButton.classList.add('show');
            } else {
                scrollButton.classList.remove('show');
            }
        });
        scrollButton.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Dropdown keyboard navigation
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const link = dropdown.querySelector('a');
        const content = dropdown.querySelector('.dropdown-content');
        if (!link || !content) return;
        link.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                content.style.display = 'block';
                const firstItem = content.querySelector('a');
                if (firstItem) firstItem.focus();
            }
        });
        content.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                content.style.display = '';
                link.focus();
            }
        });
        const focusableItems = content.querySelectorAll('a');
        if (focusableItems.length > 0) {
            const first = focusableItems[0];
            const last = focusableItems[focusableItems.length - 1];
            content.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === first) {
                            e.preventDefault();
                            last.focus();
                        }
                    } else {
                        if (document.activeElement === last) {
                            e.preventDefault();
                            first.focus();
                        }
                    }
                }
            });
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown-content[style*="display: block"]').forEach(content => {
                content.style.display = '';
            });
        }
    });

    // Service worker (optional)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('SW registered', reg))
            .catch(err => console.log('SW failed', err));
    }
});

// Toast function – improved
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed; bottom:20px; right:20px; z-index:99999;';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3'};
        color: white;
        padding: 12px 20px;
        margin-top: 10px;
        border-radius: 5px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        opacity: 0;
        transition: opacity 0.3s ease;
        font-size: 1rem;
        pointer-events: none;
    `;
    container.appendChild(toast);
    setTimeout(() => toast.style.opacity = 1, 10);
    setTimeout(() => {
        toast.style.opacity = 0;
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Flash message helper
function showFlashMessage(message, type = 'info', duration = 3000) {
    let container = document.getElementById('flash-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'flash-container';
        container.style.cssText = 'position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:100000;';
        document.body.appendChild(container);
    }
    const flash = document.createElement('div');
    flash.className = `flash flash-${type}`;
    flash.textContent = message;
    flash.style.cssText = `
        background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3'};
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        margin-bottom: 10px;
        opacity: 0;
        transition: opacity 0.3s ease;
        font-size: 1rem;
        text-align: center;
        min-width: 200px;
        pointer-events: none;
    `;
    container.appendChild(flash);
    setTimeout(() => flash.style.opacity = 1, 10);
    setTimeout(() => {
        flash.style.opacity = 0;
        setTimeout(() => flash.remove(), 300);
    }, duration);
}

// Confirmation modal
function showConfirmModal(options) {
    const {
        message,
        title = 'Confirm',
        confirmText = 'Yes',
        cancelText = 'Cancel',
        onConfirm = () => {},
        onCancel = () => {}
    } = options;

    const existingModal = document.getElementById('custom-confirm-modal');
    if (existingModal) existingModal.remove();

    const modal = document.createElement('div');
    modal.id = 'custom-confirm-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100001;
        font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    `;

    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        border-radius: 12px;
        max-width: 400px;
        width: 90%;
        padding: 20px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        text-align: center;
    `;

    const titleEl = document.createElement('h3');
    titleEl.textContent = title;
    titleEl.style.margin = '0 0 15px 0';
    titleEl.style.fontSize = '1.25rem';

    const messageEl = document.createElement('p');
    messageEl.textContent = message;
    messageEl.style.margin = '0 0 20px 0';
    messageEl.style.lineHeight = '1.5';

    const buttonContainer = document.createElement('div');
    buttonContainer.style.display = 'flex';
    buttonContainer.style.gap = '12px';
    buttonContainer.style.justifyContent = 'center';

    const confirmBtn = document.createElement('button');
    confirmBtn.textContent = confirmText;
    confirmBtn.style.cssText = `
        background: #4caf50;
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
    `;
    const cancelBtn = document.createElement('button');
    cancelBtn.textContent = cancelText;
    cancelBtn.style.cssText = `
        background: #f44336;
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
    `;

    const closeModal = (callback) => {
        if (typeof callback === 'function') callback();
        modal.remove();
    };

    confirmBtn.addEventListener('click', () => closeModal(onConfirm));
    cancelBtn.addEventListener('click', () => closeModal(onCancel));
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal(onCancel);
    });
    document.addEventListener('keydown', function handler(e) {
        if (e.key === 'Escape') {
            closeModal(onCancel);
            document.removeEventListener('keydown', handler);
        }
    });

    buttonContainer.appendChild(confirmBtn);
    buttonContainer.appendChild(cancelBtn);
    modalContent.appendChild(titleEl);
    modalContent.appendChild(messageEl);
    modalContent.appendChild(buttonContainer);
    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    confirmBtn.focus();
}

// Attach loading state to forms
function attachFormLoading(form, onSubmit) {
    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (!submitBtn) {
        console.warn('attachFormLoading: No submit button found');
        return { startLoading: () => {}, stopLoading: () => {} };
    }

    let originalText = submitBtn.textContent;
    let originalDisabled = false;
    let loading = false;

    const setLoading = (isLoading) => {
        loading = isLoading;
        if (isLoading) {
            originalDisabled = submitBtn.disabled;
            originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Loading...';
            if (!submitBtn.querySelector('.spinner')) {
                const spinner = document.createElement('span');
                spinner.className = 'spinner';
                spinner.textContent = ' ⏳';
                submitBtn.appendChild(spinner);
            }
        } else {
            submitBtn.disabled = originalDisabled;
            submitBtn.textContent = originalText;
            const spinner = submitBtn.querySelector('.spinner');
            if (spinner) spinner.remove();
        }
    };

    const startLoading = () => setLoading(true);
    const stopLoading = () => setLoading(false);

    if (onSubmit && typeof onSubmit === 'function') {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (loading) return;
            startLoading();
            try {
                await onSubmit(form, e);
            } catch (error) {
                console.error('Error in onSubmit callback:', error);
                showFlashMessage('An error occurred. Please try again.', 'error');
            } finally {
                stopLoading();
            }
        });
    }

    return { startLoading, stopLoading };
}