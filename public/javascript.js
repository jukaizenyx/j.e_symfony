document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const money = value => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
    const productsNode = document.getElementById('je-products');
    let products = [];

    if (productsNode) {
        try {
            products = JSON.parse(productsNode.textContent);
        } catch (error) {
            console.error('Unable to read the product catalogue.', error);
        }
    }

    const navToggle = document.getElementById('navToggle');
    const mainNav = document.getElementById('mainNav');
    if (navToggle && mainNav) {
        navToggle.addEventListener('click', () => {
            const open = mainNav.classList.toggle('is-open');
            navToggle.classList.toggle('is-open', open);
            navToggle.setAttribute('aria-expanded', String(open));
        });
        mainNav.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
            mainNav.classList.remove('is-open');
            navToggle.classList.remove('is-open');
            navToggle.setAttribute('aria-expanded', 'false');
        }));
    }

    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    if (profileTrigger && profileDropdown) {
        profileTrigger.addEventListener('click', event => {
            event.stopPropagation();
            const open = profileDropdown.classList.toggle('is-open');
            profileTrigger.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('click', event => {
            if (!profileDropdown.contains(event.target) && !profileTrigger.contains(event.target)) {
                profileDropdown.classList.remove('is-open');
                profileTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    const notificationTrigger = document.getElementById('notificationTrigger');
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (notificationTrigger && notificationDropdown) {
        notificationTrigger.addEventListener('click', event => {
            event.stopPropagation();
            const open = notificationDropdown.classList.toggle('is-open');
            notificationTrigger.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('click', event => {
            if (!event.target.closest('#notificationNav')) {
                notificationDropdown.classList.remove('is-open');
                notificationTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    const cartOverlay = document.getElementById('cartModalOverlay');
    const cartItems = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartCount');
    const cartSubtotal = document.getElementById('cartSubtotal');
    const cartError = document.getElementById('checkoutMessage');
    const cartToast = document.getElementById('cartToast');
    const cartUrl = body.dataset.cartActionUrl;
    const token = body.dataset.cartToken;
    let currentCart = { items: [], count: 0, subtotal: 0 };

    async function cartRequest(action, data = {}) {
        const url = action === 'get' ? cartUrl : `${cartUrl}/${action}`;
        const options = { headers: { Accept: 'application/json' } };
        if (action !== 'get') {
            const formData = new URLSearchParams({ ...data, _token: token });
            options.method = 'POST';
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.body = formData;
        }
        const response = await fetch(url, options);
        const payload = await response.json();
        if (!response.ok || payload.ok === false) {
            throw new Error(payload.error || 'The cart could not be updated.');
        }
        return payload;
    }

    function renderCart(cart) {
        currentCart = cart;
        if (cartCount) cartCount.textContent = cart.count;
        if (cartSubtotal) cartSubtotal.textContent = money(cart.subtotal);
        if (!cartItems) return;

        cartItems.replaceChildren();
        if (!cart.items.length) {
            const empty = document.createElement('p');
            empty.className = 'cart-empty';
            empty.textContent = 'Your cart is empty.';
            cartItems.append(empty);
            return;
        }

        cart.items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'cart-item';
            const image = document.createElement('img');
            image.src = item.image.startsWith('/') ? item.image : `/${item.image}`;
            image.alt = item.name;
            image.className = 'cart-item-img';
            const info = document.createElement('div');
            info.className = 'cart-item-info';
            const name = document.createElement('p');
            name.className = 'cart-item-name';
            name.textContent = item.name;
            const size = document.createElement('p');
            size.className = 'cart-item-size';
            size.textContent = item.size;
            const quantity = document.createElement('div');
            quantity.className = 'cart-item-qty';
            const minus = document.createElement('button');
            minus.type = 'button';
            minus.className = 'qty-btn cart-qty-minus';
            minus.setAttribute('aria-label', 'Decrease quantity');
            minus.textContent = '−';
            const count = document.createElement('span');
            count.textContent = item.qty;
            const plus = document.createElement('button');
            plus.type = 'button';
            plus.className = 'qty-btn cart-qty-plus';
            plus.setAttribute('aria-label', 'Increase quantity');
            plus.textContent = '+';
            quantity.append(minus, count, plus);
            info.append(name, size, quantity);

            const right = document.createElement('div');
            right.className = 'cart-item-right';
            const total = document.createElement('p');
            total.className = 'cart-item-price';
            total.textContent = money(item.line_total);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'cart-item-remove';
            remove.textContent = 'Remove';
            right.append(total, remove);
            row.append(image, info, right);
            cartItems.append(row);

            const update = async qty => {
                try {
                    renderCart(await cartRequest('update', { product_id: item.product_id, size: item.size, qty }));
                } catch (error) {
                    showCartError(error.message);
                }
            };
            minus.addEventListener('click', () => update(item.qty - 1));
            plus.addEventListener('click', () => update(item.qty + 1));
            remove.addEventListener('click', async () => {
                try {
                    renderCart(await cartRequest('remove', { product_id: item.product_id, size: item.size, qty: 1 }));
                } catch (error) {
                    showCartError(error.message);
                }
            });
        });
    }

    function showCartError(message) {
        if (!cartError) return;
        cartError.textContent = message;
        cartError.style.display = 'block';
    }

    function openCart() {
        cartOverlay?.classList.add('is-open');
        body.classList.add('modal-open');
    }
    function closeCart() {
        cartOverlay?.classList.remove('is-open');
        body.classList.remove('modal-open');
        if (cartError) {
            cartError.textContent = '';
            cartError.style.display = 'none';
        }
    }

    document.getElementById('cartModalClose')?.addEventListener('click', closeCart);
    cartOverlay?.addEventListener('click', event => {
        if (event.target === cartOverlay) closeCart();
    });
    document.getElementById('cartTrigger')?.addEventListener('click', async event => {
        event.preventDefault();
        try {
            renderCart(await cartRequest('get'));
            openCart();
        } catch (error) {
            showCartError(error.message);
            openCart();
        }
    });
    document.getElementById('cartCheckoutBtn')?.addEventListener('click', () => {
        if (!currentCart.items.length) {
            showCartError('Please add at least one product to your cart before checking out.');
            return;
        }
        window.location.assign(body.dataset.checkoutUrl);
    });

    const productOverlay = document.getElementById('productModalOverlay');
    if (productOverlay) {
        const image = document.getElementById('productModalImage');
        const title = document.getElementById('productModalTitle');
        const price = document.getElementById('productModalPrice');
        const description = document.getElementById('productModalDesc');
        const sizeOptions = document.getElementById('sizeOptions');
        const qtyInput = document.getElementById('qtyInput');
        const addButton = document.getElementById('modalAddToCart');
        const addError = document.getElementById('modalAddError');
        let activeProduct;
        let activeSize;

        function closeProduct() {
            productOverlay.classList.remove('is-open');
            body.classList.remove('modal-open');
        }
        function updatePrice() {
            if (activeProduct && activeSize) price.textContent = money(activeProduct.sizes[activeSize]);
        }
        function openProduct(id) {
            activeProduct = products.find(product => String(product.id) === String(id));
            if (!activeProduct) return;
            const sizes = Object.keys(activeProduct.sizes || {});
            if (!sizes.length) return;
            activeSize = sizes[0];
            image.src = activeProduct.image.startsWith('/') ? activeProduct.image : `/${activeProduct.image}`;
            image.alt = activeProduct.name;
            title.textContent = activeProduct.name;
            description.textContent = activeProduct.description || '';
            qtyInput.value = '1';
            addError.textContent = '';
            sizeOptions.replaceChildren();
            sizes.forEach(size => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `size-chip${size === activeSize ? ' is-selected' : ''}`;
                button.textContent = size;
                button.addEventListener('click', () => {
                    activeSize = size;
                    sizeOptions.querySelectorAll('.size-chip').forEach(option => option.classList.remove('is-selected'));
                    button.classList.add('is-selected');
                    updatePrice();
                });
                sizeOptions.append(button);
            });
            updatePrice();
            productOverlay.classList.add('is-open');
            body.classList.add('modal-open');
        }

        document.querySelectorAll('.btn-add[data-product-id]').forEach(button => {
            button.addEventListener('click', () => openProduct(button.dataset.productId));
        });
        document.getElementById('productModalClose')?.addEventListener('click', closeProduct);
        productOverlay.addEventListener('click', event => {
            if (event.target === productOverlay) closeProduct();
        });
        document.getElementById('qtyMinus')?.addEventListener('click', () => {
            qtyInput.value = String(Math.max(1, Number(qtyInput.value || 1) - 1));
        });
        document.getElementById('qtyPlus')?.addEventListener('click', () => {
            qtyInput.value = String(Math.min(20, Number(qtyInput.value || 1) + 1));
        });
        qtyInput?.addEventListener('change', () => {
            qtyInput.value = String(Math.max(1, Math.min(20, Number(qtyInput.value || 1))));
        });

        const zoomFrame = document.getElementById('zoomFrame');
        const zoomPane = document.getElementById('zoomPane');
        if (zoomFrame && zoomPane) {
            const setupZoom = () => {
                zoomPane.style.backgroundImage = `url("${image.src}")`;
                zoomPane.style.backgroundSize = '220%';
            };
            zoomFrame.addEventListener('mouseenter', () => {
                setupZoom();
                zoomPane.style.opacity = '1';
            });
            zoomFrame.addEventListener('mouseleave', () => { zoomPane.style.opacity = '0'; });
            zoomFrame.addEventListener('mousemove', event => {
                const rect = zoomFrame.getBoundingClientRect();
                const x = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
                const y = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));
                zoomPane.style.backgroundPosition = `${x * 100}% ${y * 100}%`;
            });
        }
        addButton?.addEventListener('click', async () => {
            if (!activeProduct || !activeSize) return;
            addButton.disabled = true;
            addError.textContent = '';
            try {
                const result = await cartRequest('add', {
                    product_id: activeProduct.id,
                    size: activeSize,
                    qty: qtyInput.value,
                });
                renderCart(result);
                cartToast?.classList.add('is-visible');
                window.setTimeout(() => cartToast?.classList.remove('is-visible'), 1800);
                closeProduct();
                openCart();
            } catch (error) {
                addError.textContent = error.message;
            } finally {
                addButton.disabled = false;
            }
        });
    }

    const addressOptions = document.querySelectorAll('input[name="address_type"], input[name$="[address_type]"]');
    const newAddressFields = document.getElementById('new-address-fields');
    if (addressOptions.length && newAddressFields) {
        const updateAddressFields = () => {
            const selected = document.querySelector('input[name="address_type"]:checked, input[name$="[address_type]"]:checked');
            const useNewAddress = selected?.value === 'new';
            newAddressFields.style.display = useNewAddress ? 'grid' : 'none';
            newAddressFields.querySelectorAll('input').forEach(input => { input.required = useNewAddress; });
        };
        addressOptions.forEach(option => option.addEventListener('change', updateAddressFields));
        updateAddressFields();
    }

    const paymentOptions = document.querySelectorAll('input[name="payment_method"], input[name$="[payment_method]"]');
    const gcashPayment = document.getElementById('gcash-payment');
    if (paymentOptions.length && gcashPayment) {
        const updatePaymentMethod = () => {
            const selected = document.querySelector('input[name="payment_method"]:checked, input[name$="[payment_method]"]:checked');
            gcashPayment.style.display = selected?.value === 'GCash' ? 'block' : 'none';
        };
        paymentOptions.forEach(option => option.addEventListener('change', updatePaymentMethod));
        updatePaymentMethod();
    }

    document.querySelectorAll('.password-toggle[data-target]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.target);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            button.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeCart();
            productOverlay?.classList.remove('is-open');
            body.classList.remove('modal-open');
        }
    });
});
