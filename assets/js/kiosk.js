/*
 * TAMCC Deli – Kiosk Mode JavaScript
 * Enhanced: cart management, dynamic price updates, option dialogs, payment simulation.
 * Note: This file works with both localStorage (fallback) and server-side cart via AJAX.
 */

// ==================== CART HELPERS (SERVER-SYNCED) ====================
async function getCart() {
    try {
        const response = await fetch('/get-cart-count.php');
        const data = await response.json();
        // We'll also fetch full cart contents if needed, but count is sufficient for display.
        return { count: data.count };
    } catch (e) {
        console.error('Failed to fetch cart count:', e);
        return { count: 0 };
    }
}

async function addToCart(itemId, quantity, options = {}) {
    const formData = new URLSearchParams();
    formData.append('csrf_token', getCsrfToken());
    formData.append('item_id', itemId);
    formData.append('quantity', quantity);
    formData.append('options', JSON.stringify(options));
    try {
        const response = await fetch('/cart.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            updateCartDisplay();
            return true;
        } else {
            showToast(data.error || 'Error adding item', 'error');
            return false;
        }
    } catch (error) {
        console.error('Add to cart error:', error);
        showToast('Network error. Please try again.', 'error');
        return false;
    }
}

async function updateCartDisplay() {
    try {
        const response = await fetch('/get-cart-count.php');
        const data = await response.json();
        const countSpans = document.querySelectorAll('.cart-count');
        countSpans.forEach(span => span.textContent = data.count);
    } catch (e) {
        console.error('Failed to update cart display:', e);
    }
}

// ==================== DYNAMIC PRICE UPDATE (for items with options) ====================
function attachPriceUpdater(card) {
    const basePriceElem = card.querySelector('.price');
    if (!basePriceElem) return;
    const basePrice = parseFloat(basePriceElem.dataset.basePrice);
    const radios = card.querySelectorAll('input[type="radio"][data-price]');
    const updatePrice = () => {
        let modifier = 0;
        radios.forEach(radio => {
            if (radio.checked) modifier += parseFloat(radio.dataset.price || 0);
        });
        basePriceElem.textContent = `$${(basePrice + modifier).toFixed(2)}`;
    };
    radios.forEach(radio => radio.addEventListener('change', updatePrice));
    updatePrice();
}

// ==================== QUANTITY CONTROLS ====================
function attachQuantityControls(card) {
    const qtySpan = card.querySelector('.qty-value');
    const decBtn = card.querySelector('.dec');
    const incBtn = card.querySelector('.inc');
    if (!qtySpan || !decBtn || !incBtn) return;
    let quantity = 1;
    decBtn.addEventListener('click', () => {
        if (quantity > 1) quantity--;
        qtySpan.textContent = quantity;
    });
    incBtn.addEventListener('click', () => {
        quantity++;
        qtySpan.textContent = quantity;
    });
    return () => quantity;
}

// ==================== OPTIONS DIALOG (REUSABLE) ====================
let currentOptionsDialog = null;
let currentItemData = null;
let currentSelectedOptions = {};

function showOptionsDialog(item) {
    if (currentOptionsDialog) currentOptionsDialog.remove();
    currentItemData = item;
    currentSelectedOptions = {};

    const overlay = document.createElement('div');
    overlay.className = 'option-dialog-overlay';
    overlay.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    `;
    const dialog = document.createElement('div');
    dialog.className = 'option-dialog';
    dialog.style.cssText = `
        background: white;
        border-radius: 1rem;
        max-width: 500px;
        width: 90%;
        max-height: 85vh;
        overflow-y: auto;
        padding: 1.5rem;
    `;

    // Header
    const header = document.createElement('div');
    header.style.display = 'flex';
    header.style.justifyContent = 'space-between';
    header.style.alignItems = 'center';
    header.style.marginBottom = '1rem';
    const title = document.createElement('h2');
    title.textContent = item.name;
    title.style.fontSize = '1.5rem';
    const closeBtn = document.createElement('button');
    closeBtn.textContent = '×';
    closeBtn.style.background = 'none';
    closeBtn.style.border = 'none';
    closeBtn.style.fontSize = '2rem';
    closeBtn.style.cursor = 'pointer';
    closeBtn.addEventListener('click', () => overlay.remove());
    header.appendChild(title);
    header.appendChild(closeBtn);
    dialog.appendChild(header);

    // Image
    if (item.image) {
        const img = document.createElement('img');
        img.src = item.image;
        img.alt = item.name;
        img.style.width = '100%';
        img.style.borderRadius = '0.5rem';
        img.style.marginBottom = '1rem';
        dialog.appendChild(img);
    }

    // Description
    if (item.description) {
        const desc = document.createElement('p');
        desc.textContent = item.description;
        desc.style.marginBottom = '1rem';
        desc.style.color = '#4b5563';
        dialog.appendChild(desc);
    }

    // Options
    const optionsContainer = document.createElement('div');
    optionsContainer.style.marginBottom = '1rem';
    item.options.forEach(opt => {
        const optGroup = document.createElement('div');
        optGroup.style.marginBottom = '1rem';
        const label = document.createElement('label');
        label.textContent = opt.option_name + (opt.required ? ' *' : '');
        label.style.fontWeight = '600';
        label.style.display = 'block';
        label.style.marginBottom = '0.5rem';
        optGroup.appendChild(label);
        const radioGroup = document.createElement('div');
        radioGroup.style.display = 'flex';
        radioGroup.style.flexDirection = 'column';
        radioGroup.style.gap = '0.5rem';
        opt.values.forEach(val => {
            const radioWrapper = document.createElement('div');
            radioWrapper.style.display = 'flex';
            radioWrapper.style.alignItems = 'center';
            radioWrapper.style.gap = '0.5rem';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = `opt_${opt.id}`;
            radio.value = val.id;
            radio.dataset.price = val.price_modifier;
            if (opt.required && !currentSelectedOptions[opt.id]) {
                radio.checked = true;
                currentSelectedOptions[opt.id] = val.id;
            }
            radio.addEventListener('change', () => {
                currentSelectedOptions[opt.id] = val.id;
                updateTotalPrice();
            });
            const valLabel = document.createElement('label');
            valLabel.textContent = val.value_name;
            if (val.price_modifier !== 0) {
                const sign = val.price_modifier > 0 ? '+' : '-';
                valLabel.textContent += ` (${sign}$${Math.abs(val.price_modifier).toFixed(2)})`;
            }
            radioWrapper.appendChild(radio);
            radioWrapper.appendChild(valLabel);
            radioGroup.appendChild(radioWrapper);
        });
        optGroup.appendChild(radioGroup);
        optionsContainer.appendChild(optGroup);
    });
    dialog.appendChild(optionsContainer);

    // Total price
    const totalDiv = document.createElement('div');
    totalDiv.style.display = 'flex';
    totalDiv.style.justifyContent = 'space-between';
    totalDiv.style.alignItems = 'center';
    totalDiv.style.marginBottom = '1rem';
    const totalLabel = document.createElement('span');
    totalLabel.textContent = 'Total:';
    totalLabel.style.fontWeight = 'bold';
    const totalPrice = document.createElement('span');
    totalPrice.id = 'dialog-total-price';
    totalPrice.style.fontSize = '1.5rem';
    totalPrice.style.fontWeight = 'bold';
    totalPrice.style.color = '#074af2';
    totalDiv.appendChild(totalLabel);
    totalDiv.appendChild(totalPrice);
    dialog.appendChild(totalDiv);

    // Add to cart button
    const addBtn = document.createElement('button');
    addBtn.textContent = 'Add to Cart';
    addBtn.className = 'btn btn-primary';
    addBtn.style.width = '100%';
    addBtn.style.padding = '0.75rem';
    addBtn.style.fontSize = '1rem';
    addBtn.addEventListener('click', async () => {
        // Validate required options
        let valid = true;
        item.options.forEach(opt => {
            if (opt.required && !currentSelectedOptions[opt.id]) {
                showToast(`Please select ${opt.option_name}`, 'error');
                valid = false;
            }
        });
        if (!valid) return;

        const success = await addToCart(item.id, 1, currentSelectedOptions);
        if (success) {
            overlay.remove();
            showToast('Added to cart!', 'success');
        }
    });
    dialog.appendChild(addBtn);

    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    currentOptionsDialog = overlay;

    function updateTotalPrice() {
        let modifiers = 0;
        item.options.forEach(opt => {
            const selectedId = currentSelectedOptions[opt.id];
            const val = opt.values.find(v => v.id == selectedId);
            if (val) modifiers += parseFloat(val.price_modifier || 0);
        });
        const total = item.price + modifiers;
        totalPrice.textContent = `$${total.toFixed(2)}`;
    }
    updateTotalPrice();
}

// ==================== INITIALIZE PAGE ====================
document.addEventListener('DOMContentLoaded', () => {
    updateCartDisplay();

    // Handle menu items with options: attach click handlers
    document.querySelectorAll('.menu-item').forEach(card => {
        const optionsData = card.dataset.options;
        let options = [];
        try {
            options = JSON.parse(optionsData || '[]');
        } catch(e) { console.warn(e); }

        const addBtn = card.querySelector('.add-to-cart-btn');
        const itemId = parseInt(card.dataset.id);
        const itemName = card.dataset.name;
        const itemPrice = parseFloat(card.dataset.price);
        const itemImage = card.dataset.image;
        const itemDesc = card.dataset.description;

        if (options.length > 0) {
            // Show dialog on card click (except on add button)
            card.addEventListener('click', (e) => {
                if (e.target === addBtn || addBtn?.contains(e.target)) return;
                showOptionsDialog({
                    id: itemId,
                    name: itemName,
                    price: itemPrice,
                    image: itemImage,
                    description: itemDesc,
                    options: options
                });
            });
            // Add button also shows dialog
            if (addBtn) {
                addBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    showOptionsDialog({
                        id: itemId,
                        name: itemName,
                        price: itemPrice,
                        image: itemImage,
                        description: itemDesc,
                        options: options
                    });
                });
            }
        } else {
            // No options: direct add
            if (addBtn) {
                addBtn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const success = await addToCart(itemId, 1, {});
                    if (success) showToast('Added to cart!', 'success');
                });
            }
        }

        // Attach price updater if options exist and radio buttons present
        if (card.querySelectorAll('input[type="radio"][data-price]').length) {
            attachPriceUpdater(card);
        }
        // Attach quantity controls if present
        attachQuantityControls(card);
    });

    // Cart page: attach update/remove handlers
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', async function() {
            const key = this.dataset.key;
            const qty = parseInt(this.value);
            if (isNaN(qty) || qty < 1) return;
            const formData = new FormData();
            formData.append('csrf_token', getCsrfToken());
            formData.append('key', key);
            formData.append('quantity', qty);
            try {
                const response = await fetch('/cart.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (data.success) location.reload();
                else showToast('Error updating quantity', 'error');
            } catch(e) {
                showToast('Network error', 'error');
            }
        });
    });

    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', async () => {
            const key = btn.dataset.key;
            const formData = new FormData();
            formData.append('csrf_token', getCsrfToken());
            formData.append('key', key);
            formData.append('action', 'remove');
            try {
                const response = await fetch('/cart.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (data.success) location.reload();
                else showToast('Error removing item', 'error');
            } catch(e) {
                showToast('Network error', 'error');
            }
        });
    });
});

// Helper to get CSRF token from meta or from PHP output
function getCsrfToken() {
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    if (tokenInput) return tokenInput.value;
    return '';
}

// Toast function
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