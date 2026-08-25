const cartStorageKey = 'carrinho_sd';
let cart = JSON.parse(localStorage.getItem(cartStorageKey) || '[]');

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function saveCart() {
    localStorage.setItem(cartStorageKey, JSON.stringify(cart));
}

function renderCart() {
    const container = document.getElementById('cart-items');
    const cartData = document.getElementById('cart-data');
    const checkoutButton = document.getElementById('checkout-button');
    const cartCount = document.getElementById('cart-count');
    const totalElement = document.getElementById('cart-total');
    const summaryTotalElement = document.getElementById('cart-total-summary');

    if (!container) {
        return;
    }

    const itemCount = cart.reduce((total, item) => total + item.qtd, 0);
    const total = cart.reduce((sum, item) => sum + (Number(item.preco) * item.qtd), 0);
    cartCount.textContent = itemCount;
    totalElement.textContent = formatCurrency(total);
    summaryTotalElement.textContent = formatCurrency(total);
    cartData.value = JSON.stringify(cart);
    checkoutButton.disabled = cart.length === 0;

    if (cart.length === 0) {
        container.innerHTML = '<div class="empty-state">Seu carrinho ainda está vazio.</div>';
        return;
    }

    container.innerHTML = cart.map((item, index) => `
        <div class="cart-item">
            <div>
                <div class="cart-item-name">${escapeHtml(item.nome)}</div>
                <div class="cart-item-price">${formatCurrency(item.preco)} por unidade</div>
            </div>
            <div class="quantity-control" aria-label="Quantidade de ${escapeHtml(item.nome)}">
                <button type="button" onclick="changeQuantity(${index}, -1)" aria-label="Diminuir quantidade">-</button>
                <span>${item.qtd}</span>
                <button type="button" onclick="changeQuantity(${index}, 1)" aria-label="Aumentar quantidade">+</button>
            </div>
            <button type="button" class="remove-button" onclick="removeFromCart(${index})">Remover</button>
        </div>
    `).join('');
}

function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
    }[character]));
}

function addToCart(id, name, price) {
    const existingItem = cart.find(item => item.id === id);
    if (existingItem) {
        existingItem.qtd += 1;
    } else {
        cart.push({ id, nome: name, preco: Number(price), qtd: 1 });
    }
    saveCart();
    renderCart();
    showToast('Produto adicionado ao carrinho');
}

function changeQuantity(index, amount) {
    cart[index].qtd += amount;
    if (cart[index].qtd <= 0) {
        cart.splice(index, 1);
    }
    saveCart();
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    saveCart();
    renderCart();
}

function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) {
        return;
    }
    toast.textContent = message;
    toast.classList.add('visible');
    window.setTimeout(() => toast.classList.remove('visible'), 2200);
}

function filterProducts(searchTerm) {
    const normalizedTerm = searchTerm.toLowerCase().trim();
    document.querySelectorAll('.product-card').forEach(card => {
        const productText = card.textContent.toLowerCase();
        card.hidden = normalizedTerm !== '' && !productText.includes(normalizedTerm);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    renderCart();
    const search = document.getElementById('product-search');
    if (search) {
        search.addEventListener('input', event => filterProducts(event.target.value));
    }
    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', () => localStorage.removeItem(cartStorageKey));
    }
});