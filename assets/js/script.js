/**
 * TAMCC Deli – Main JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('show');
        });
    }

    // Close mobile menu on window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && navLinks.classList.contains('show')) {
            navLinks.classList.remove('show');
        }
    });

    // Smooth scroll for category links
    document.querySelectorAll('.dropdown-content a').forEach(link => {
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

    // Live menu search
    const searchInput = document.getElementById('menu-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.menu-item').forEach(item => {
                const title = item.querySelector('.menu-item-title')?.textContent.toLowerCase() || '';
                item.style.display = title.includes(term) ? '' : 'none';
            });
        });
    }

    // Category filter buttons
    const filterBtns = document.querySelectorAll('.filter-btn');
    const categories = document.querySelectorAll('.category');
    if (filterBtns.length && categories.length) {
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const selected = btn.dataset.category;
                categories.forEach(cat => {
                    cat.style.display = (selected === 'all' || cat.id === selected) ? '' : 'none';
                });
            });
        });
    }

    // AJAX Add to Cart
    document.querySelectorAll('.add-to-cart-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            const formData = new FormData(this);
            const url = window.kioskMode ? '/cart.php?action=add&kiosk=1' : '/cart.php?action=add';
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();
                if (result.success) {
                    showToast('Item added to cart!', 'success');
                    updateCartCount();
                } else {
                    showToast(result.error || 'Error adding item', 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('Failed to add item. Please try again.', 'error');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    });

    // Update cart count
    async function updateCartCount() {
        try {
            const url = window.kioskMode ? '/get-cart-count.php?kiosk=1' : '/get-cart-count.php';
            const response = await fetch(url);
            const data = await response.json();
            const countSpan = document.getElementById('cart-count');
            if (countSpan) countSpan.textContent = data.count;
            const kioskCount = document.getElementById('cart-count-kiosk');
            if (kioskCount) kioskCount.textContent = data.count;
        } catch (error) {
            console.error('Failed to update cart count:', error);
        }
    }
    updateCartCount();

    // Modal handling for menu options
    let currentModal = null;
    window.openItemModal = function(itemId, name, price, image, description, optionsHtml) {
        if (currentModal) closeModal();
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">${escapeHtml(name)}</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <img src="${escapeHtml(image)}" alt="${escapeHtml(name)}" class="w-full h-48 object-cover rounded-lg mb-4">
                    <p class="text-gray-600 mb-4">${escapeHtml(description)}</p>
                    <div class="options-container" data-base-price="${price}">
                        ${optionsHtml}
                    </div>
                    <div class="mt-4 text-right">
                        <span class="text-xl font-bold text-primary">$<span class="item-total-price">${price}</span></span>
                    </div>
                    <form class="add-to-cart-form mt-4">
                        <input type="hidden" name="item_id" value="${itemId}">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="btn btn-primary w-full">Add to Cart</button>
                    </form>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.style.display = 'flex';
        currentModal = modal;

        // Close button
        modal.querySelector('.modal-close').addEventListener('click', () => closeModal());
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        // Dynamic price update
        const optionsContainer = modal.querySelector('.options-container');
        const basePrice = parseFloat(optionsContainer.dataset.basePrice);
        const priceSpan = modal.querySelector('.item-total-price');
        const updatePrice = () => {
            let modifier = 0;
            optionsContainer.querySelectorAll('input[type="radio"]:checked, select').forEach(el => {
                const selected = el.selectedOptions ? el.selectedOptions[0] : el;
                const priceMod = parseFloat(selected.dataset.price || 0);
                if (!isNaN(priceMod)) modifier += priceMod;
            });
            const total = basePrice + modifier;
            priceSpan.textContent = total.toFixed(2);
        };
        optionsContainer.querySelectorAll('input[type="radio"], select').forEach(el => {
            el.addEventListener('change', updatePrice);
        });
        updatePrice();

        // Handle form submission inside modal
        const modalForm = modal.querySelector('.add-to-cart-form');
        modalForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(modalForm);
            const submitBtn = modalForm.querySelector('button');
            if (submitBtn) submitBtn.disabled = true;
            const url = window.kioskMode ? '/cart.php?action=add&kiosk=1' : '/cart.php?action=add';
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();
                if (result.success) {
                    showToast('Item added to cart!', 'success');
                    updateCartCount();
                    closeModal();
                } else {
                    showToast(result.error || 'Error adding item', 'error');
                }
            } catch (error) {
                showToast('Failed to add item', 'error');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    };

    function closeModal() {
        if (currentModal) {
            currentModal.remove();
            currentModal = null;
        }
    }

    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Toast function
    window.showToast = function(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    };
});