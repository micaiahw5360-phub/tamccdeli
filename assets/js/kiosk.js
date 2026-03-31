// kiosk.js – Shared state and utilities

// Mock menu data (replace with PHP-generated JSON)
const menu = {
  "Combo": [
    { id: 1, name: "Chicken Combo", price: 9.99, image: "https://images.unsplash.com/photo-1551782450-17144efb9c50?w=400" },
    { id: 2, name: "Veggie Combo", price: 8.99, image: "https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400" }
  ],
  "Drinks": [
    { id: 3, name: "Fresh Lemonade", price: 2.99, image: "https://images.unsplash.com/photo-1519923834699-ef0b7cde4712?w=400" },
    { id: 4, name: "Iced Coffee", price: 3.49, image: "https://images.unsplash.com/photo-1517701604599-bb9b56dc32c7?w=400" }
  ],
  "Breakfast": [
    { id: 5, name: "Breakfast Wrap", price: 5.99, image: "https://images.unsplash.com/photo-1623428454612-2b7a00cf9a9b?w=400" },
    { id: 6, name: "Pancakes", price: 4.99, image: "https://images.unsplash.com/photo-1528207776546-365bb710ee93?w=400" }
  ],
  "À la carte": [
    { id: 7, name: "Grilled Chicken", price: 7.99, image: "https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?w=400" },
    { id: 8, name: "Fish Fillet", price: 8.49, image: "https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=400" }
  ],
  "Dessert": [
    { id: 9, name: "Chocolate Cake", price: 3.99, image: "https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400" },
    { id: 10, name: "Ice Cream", price: 2.49, image: "https://images.unsplash.com/photo-1501443762994-82bd5dace89a?w=400" }
  ]
};

// Cart helpers
function getCart() {
  return JSON.parse(localStorage.getItem('cart')) || [];
}

function saveCart(cart) {
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartDisplay();
}

function addToCart(item, quantity = 1) {
  let cart = getCart();
  const existing = cart.find(i => i.id === item.id);
  if (existing) {
    existing.quantity += quantity;
  } else {
    cart.push({ ...item, quantity });
  }
  saveCart(cart);
}

function updateQuantity(itemId, delta) {
  let cart = getCart();
  const item = cart.find(i => i.id === itemId);
  if (item) {
    item.quantity += delta;
    if (item.quantity <= 0) {
      cart = cart.filter(i => i.id !== itemId);
    }
    saveCart(cart);
  }
}

function removeItem(itemId) {
  let cart = getCart();
  cart = cart.filter(i => i.id !== itemId);
  saveCart(cart);
}

function getCartTotal() {
  const cart = getCart();
  return cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
}

function getCartCount() {
  return getCart().reduce((sum, item) => sum + item.quantity, 0);
}

function updateCartDisplay() {
  const countEls = document.querySelectorAll('.cart-count');
  const total = getCartCount();
  countEls.forEach(el => el.textContent = total);
}

// Wallet helpers (mock)
function getWalletBalance() {
  return parseFloat(localStorage.getItem('walletBalance')) || 20.00;
}

function updateWalletBalance(amount) {
  const newBalance = getWalletBalance() + amount;
  localStorage.setItem('walletBalance', newBalance);
  return newBalance;
}

function deductWallet(amount) {
  const balance = getWalletBalance();
  if (balance >= amount) {
    updateWalletBalance(-amount);
    return true;
  }
  return false;
}

// Staff login (mock)
function isStaffLoggedIn() {
  return localStorage.getItem('staffLoggedIn') === 'true';
}

function staffLogin(username, password) {
  // For demo, accept any non-empty credentials
  if (username && password) {
    localStorage.setItem('staffLoggedIn', 'true');
    localStorage.setItem('staffName', username);
    return true;
  }
  return false;
}

function staffLogout() {
  localStorage.removeItem('staffLoggedIn');
  localStorage.removeItem('staffName');
}

// Selected category
function setSelectedCategory(category) {
  sessionStorage.setItem('selectedCategory', category);
}

function getSelectedCategory() {
  return sessionStorage.getItem('selectedCategory');
}

// Time display
function updateTime() {
  const now = new Date();
  const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  const timeEl = document.querySelector('.time');
  if (timeEl) timeEl.textContent = timeStr;
}
setInterval(updateTime, 1000);
updateTime();

// Greeting
function getGreeting() {
  const hour = new Date().getHours();
  if (hour < 12) return "Good Morning";
  if (hour < 18) return "Good Afternoon";
  return "Good Evening";
}

// Populate items screen
function loadItems() {
  const category = getSelectedCategory();
  const items = menu[category] || [];
  const container = document.querySelector('.items-container');
  if (container) {
    container.innerHTML = items.map(item => `
      <div class="item-card" data-id="${item.id}">
        <img src="${item.image}" alt="${item.name}">
        <div class="item-card-content">
          <h3>${item.name}</h3>
          <div class="item-price">$${item.price.toFixed(2)}</div>
          <div class="item-actions">
            <div class="qty-control">
              <button class="qty-btn dec">-</button>
              <span class="qty-value">1</span>
              <button class="qty-btn inc">+</button>
            </div>
            <button class="btn btn-small add-to-cart">Add</button>
          </div>
        </div>
      </div>
    `).join('');

    // Attach event listeners
    container.querySelectorAll('.item-card').forEach(card => {
      const id = parseInt(card.dataset.id);
      const item = items.find(i => i.id === id);
      const qtySpan = card.querySelector('.qty-value');
      const decBtn = card.querySelector('.dec');
      const incBtn = card.querySelector('.inc');
      const addBtn = card.querySelector('.add-to-cart');

      let quantity = 1;
      const updateQtyDisplay = () => { qtySpan.textContent = quantity; };
      decBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (quantity > 1) quantity--;
        updateQtyDisplay();
      });
      incBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        quantity++;
        updateQtyDisplay();
      });
      addBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        addToCart(item, quantity);
        quantity = 1;
        updateQtyDisplay();
        showToast(`Added ${item.name} to cart`);
      });
    });
  }
}

// Toast notification (simple)
function showToast(message) {
  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.style.opacity = '1';
  setTimeout(() => { toast.style.opacity = '0'; }, 2000);
}

// Payment simulation
function processPayment(name, password) {
  const total = getCartTotal();
  if (!name || !password) {
    showToast('Please enter name and password');
    return false;
  }
  if (deductWallet(total)) {
    // Clear cart
    localStorage.removeItem('cart');
    // Store order for receipt
    const order = {
      id: Date.now(),
      items: getCart(), // cart is cleared after this, so we store before clearing
      total: total,
      timestamp: new Date().toISOString(),
      customer: name
    };
    // Actually cart is already cleared after deductWallet? We need to store before clearing.
    const cart = getCart();
    order.items = cart;
    localStorage.setItem('lastOrder', JSON.stringify(order));
    localStorage.removeItem('cart');
    return true;
  } else {
    showToast('Insufficient wallet balance');
    return false;
  }
}

// Load receipt on confirmation page
function loadReceipt() {
  const order = JSON.parse(localStorage.getItem('lastOrder'));
  if (!order) return;
  const container = document.querySelector('.receipt-items');
  if (container) {
    container.innerHTML = order.items.map(item => `
      <div class="receipt-item">
        <span>${item.quantity}x ${item.name}</span>
        <span>$${(item.price * item.quantity).toFixed(2)}</span>
      </div>
    `).join('');
    document.querySelector('.receipt-total').textContent = `Total: $${order.total.toFixed(2)}`;
    document.querySelector('.order-id').textContent = `Order #${order.id}`;
    document.querySelector('.order-time').textContent = new Date(order.timestamp).toLocaleString();
    document.querySelector('.customer-name').textContent = order.customer;
  }
}

// Cart page display
function loadCart() {
  const cart = getCart();
  const container = document.querySelector('.cart-items');
  const totalSpan = document.querySelector('.cart-total');
  if (container) {
    if (cart.length === 0) {
      container.innerHTML = '<p>Your cart is empty.</p>';
      totalSpan.textContent = '$0.00';
      return;
    }
    container.innerHTML = cart.map(item => `
      <tr>
        <td>${item.name}</td>
        <td>
          <div class="cart-actions">
            <button class="qty-btn dec" data-id="${item.id}">-</button>
            <span class="qty-value">${item.quantity}</span>
            <button class="qty-btn inc" data-id="${item.id}">+</button>
            <button class="btn-small remove" data-id="${item.id}">Remove</button>
          </div>
        </td>
        <td>$${item.price.toFixed(2)}</td>
        <td>$${(item.price * item.quantity).toFixed(2)}</td>
      </tr>
    `).join('');
    const total = getCartTotal();
    totalSpan.textContent = `$${total.toFixed(2)}`;

    // Attach event handlers
    container.querySelectorAll('.dec').forEach(btn => {
      btn.addEventListener('click', () => updateQuantity(parseInt(btn.dataset.id), -1));
    });
    container.querySelectorAll('.inc').forEach(btn => {
      btn.addEventListener('click', () => updateQuantity(parseInt(btn.dataset.id), 1));
    });
    container.querySelectorAll('.remove').forEach(btn => {
      btn.addEventListener('click', () => removeItem(parseInt(btn.dataset.id)));
    });
  }
}

// Initialize per page
document.addEventListener('DOMContentLoaded', () => {
  updateCartDisplay();
  if (document.querySelector('.items-container')) loadItems();
  if (document.querySelector('.cart-items')) loadCart();
  if (document.querySelector('.receipt-items')) loadReceipt();

  // Login form handling
  const loginForm = document.querySelector('#login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const username = document.querySelector('#staff-name').value;
      const password = document.querySelector('#staff-password').value;
      if (staffLogin(username, password)) {
        window.location.href = 'home.html';
      } else {
        showToast('Invalid credentials');
      }
    });
  }

  // Payment form handling
  const paymentForm = document.querySelector('#payment-form');
  if (paymentForm) {
    paymentForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const name = document.querySelector('#customer-name').value;
      const password = document.querySelector('#customer-password').value;
      if (processPayment(name, password)) {
        window.location.href = 'confirmation.html';
      }
    });
  }
});