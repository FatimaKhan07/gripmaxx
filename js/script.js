﻿document.addEventListener("DOMContentLoaded", function () {
    function isPagesDirectory() {
        return window.location.pathname.toLowerCase().includes("/pages/");
    }

    function getRootPrefix() {
        return isPagesDirectory() ? "../" : "";
    }

    function getPhpBasePath() {
        return getRootPrefix() + "php/";
    }

    function getPagePath(pageName) {
        return isPagesDirectory() ? pageName : "pages/" + pageName;
    }

    function getHomePath() {
        return getRootPrefix() + "index.html";
    }

    function getImagePath(imageName) {
        return getRootPrefix() + "images/" + imageName;
    }

    function safeJsonParse(value, fallback) {
        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }

    function buildFormBody(data) {
        return new URLSearchParams(data).toString();
    }

    let csrfTokenPromise = null;

    function fetchCsrfToken(forceRefresh) {
        if (forceRefresh || !csrfTokenPromise) {
            csrfTokenPromise = fetch(getPhpBasePath() + "csrf_token.php")
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.status !== "success" || !data.csrf_token) {
                    throw new Error("Unable to initialize secure request token.");
                }

                return data.csrf_token;
            })
            .catch(function (error) {
                csrfTokenPromise = null;
                throw error;
            });
        }

        return csrfTokenPromise;
    }

    function showAppMessage(message, type, onClose) {
        let modal = document.getElementById("appMessageModal");
        const normalizedType = type === "success" ? "success" : "error";

        if (!modal) {
            modal = document.createElement("div");
            modal.id = "appMessageModal";
            modal.className = "modal-overlay app-message-modal";
            modal.innerHTML = `
                <div class="modal-box app-message-box">
                    <h3 id="appMessageTitle"></h3>
                    <p id="appMessageText"></p>
                    <button id="appMessageOk" type="button" class="submit-btn">OK</button>
                </div>
            `;
            document.body.appendChild(modal);

            modal.querySelector("#appMessageOk").addEventListener("click", function () {
                modal.classList.remove("show");

                if (typeof modal._onClose === "function") {
                    const callback = modal._onClose;
                    modal._onClose = null;
                    callback();
                }
            });
        }

        modal.querySelector("#appMessageTitle").textContent = normalizedType === "success" ? "Success" : "Notice";
        modal.querySelector("#appMessageText").textContent = message;
        modal.querySelector(".app-message-box").className = "modal-box app-message-box " + normalizedType;
        modal._onClose = onClose;
        modal.classList.add("show");
    }

    function formatCurrency(amount) {
        return "Rs." + Number(amount || 0).toFixed(2);
    }

    function formatOrderDateValue(order) {
        const rawValue = order && order.order_date_iso ? order.order_date_iso : (order ? order.order_date : "");
        const parsedDate = new Date(rawValue);

        if (Number.isNaN(parsedDate.getTime())) {
            return String(order && order.order_date ? order.order_date : "");
        }

        return parsedDate.toLocaleString("en-IN", {
            day: "numeric",
            month: "short",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit"
        });
    }

    function getStoredItems(key) {
        return safeJsonParse(localStorage.getItem(key) || "[]", []);
    }

    let currentUser = null;

    function getCurrentUser() {
        return currentUser;
    }

    function fetchCurrentUser() {
        return fetch(getPhpBasePath() + "session_user.php")
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.status === "success" && data.authenticated && data.user) {
                currentUser = data.user.username;
                return currentUser;
            }

            currentUser = null;
            return null;
        })
        .catch(function () {
            currentUser = null;
            return null;
        });
    }

    function getCartStorageKey() {
        const currentUser = getCurrentUser();
        return currentUser ? "cartItems:" + currentUser : null;
    }

    function getBuyNowStorageKey() {
        const currentUser = getCurrentUser();
        return currentUser ? "buyNowItem:" + currentUser : null;
    }

    function getCartItems() {
        const key = getCartStorageKey();
        return key ? getStoredItems(key) : [];
    }

    function setCartItems(items) {
        const key = getCartStorageKey();

        if (!key) {
            return;
        }

        localStorage.setItem(key, JSON.stringify(items));
    }

    function clearCartItems() {
        const key = getCartStorageKey();

        if (!key) {
            return;
        }

        localStorage.removeItem(key);
    }

    function getBuyNowItems() {
        const key = getBuyNowStorageKey();
        return key ? getStoredItems(key) : [];
    }

    function setBuyNowItems(items) {
        const key = getBuyNowStorageKey();

        if (!key) {
            return;
        }

        localStorage.setItem(key, JSON.stringify(items));
    }

    function clearBuyNowItems() {
        const key = getBuyNowStorageKey();

        if (!key) {
            return;
        }

        localStorage.removeItem(key);
    }

    function fetchActiveProducts() {
        return fetch(getPhpBasePath() + "get_products.php")
        .then(function (response) {
            return response.json();
        })
        .then(function (products) {
            return Array.isArray(products) ? products : [];
        })
        .catch(function () {
            return [];
        });
    }

    function buildCartValidation(items, products) {
        const productMap = new Map();
        let hasInvalidItems = false;

        products.forEach(function (product) {
            productMap.set(String(product.id), product);
        });

        const validatedItems = items.map(function (item) {
            const liveProduct = productMap.get(String(item.id));
            const validatedItem = Object.assign({}, item, {
                isUnavailable: false,
                warningMessage: "",
                availableStock: 0
            });

            if (!liveProduct) {
                validatedItem.isUnavailable = true;
                validatedItem.warningMessage = "This product is no longer available.";
                hasInvalidItems = true;
                return validatedItem;
            }

            validatedItem.price = liveProduct.price;
            validatedItem.name = liveProduct.name;
            validatedItem.size = liveProduct.size;
            validatedItem.image = liveProduct.image;
            validatedItem.availableStock = Number(liveProduct.stock || 0);
            validatedItem.shipping_mode = liveProduct.shipping_mode || "default";
            validatedItem.shipping_cost = Number(liveProduct.shipping_cost || 0);

            if (validatedItem.availableStock <= 0) {
                validatedItem.isUnavailable = true;
                validatedItem.warningMessage = "Out of stock.";
                hasInvalidItems = true;
            } else if (validatedItem.availableStock < Number(validatedItem.quantity || 0)) {
                validatedItem.isUnavailable = true;
                validatedItem.warningMessage = "Only " + validatedItem.availableStock + " available. Update or remove this item.";
                hasInvalidItems = true;
            }

            return validatedItem;
        });

        return {
            items: validatedItems,
            hasInvalidItems: hasInvalidItems,
            validItems: validatedItems.filter(function (item) {
                return !item.isUnavailable;
            })
        };
    }

    function getActiveCheckoutItems() {
        const buyNowItems = getBuyNowItems();
        return buyNowItems.length ? buyNowItems : getCartItems();
    }

    let storefrontSettings = {
        store_email: "support@gripmaxx.com",
        shipping_option: "free",
        shipping_label: "Free Shipping",
        shipping_cost: 0
    };
    const authReady = fetchCurrentUser();

    const cartLink = document.getElementById("cartLink");
    const ordersLink = document.getElementById("ordersLink");
    const loginLink = document.getElementById("loginLink");
    const logoutLink = document.getElementById("logoutLink");
    const userGreeting = document.getElementById("userGreeting");
    const logoutConfirmModal = document.getElementById("logoutConfirmModal");
    const cancelUserLogout = document.getElementById("cancelUserLogout");
    const confirmUserLogout = document.getElementById("confirmUserLogout");

    function applyStoreEmail() {
        document.querySelectorAll(".store-email").forEach(function (node) {
            node.textContent = storefrontSettings.store_email;
        });
    }

    function getPaymentMethodLabel(paymentMethod) {
        return "Cash on Delivery";
    }

    function getPaymentStatusClass(paymentStatus) {
        const normalizedStatus = String(paymentStatus || "").toLowerCase();

        if (normalizedStatus === "paid") {
            return "payment-status-paid";
        }

        if (normalizedStatus === "failed") {
            return "payment-status-failed";
        }

        if (normalizedStatus === "pending on delivery") {
            return "payment-status-cod";
        }

        return "payment-status-awaiting";
    }

    function formatShippingValue(shippingCost) {
        return shippingCost > 0 ? formatCurrency(shippingCost) : "Free Shipping";
    }

    function calculateShippingAmount(items) {
        let totalShipping = 0;
        let usesDefaultShipping = false;

        if (!Array.isArray(items) || !items.length) {
            return 0;
        }

        items.forEach(function (item) {
            const quantity = Math.max(0, Number(item.quantity || 0));
            const shippingMode = String(item.shipping_mode || "default");
            const shippingCost = Math.max(0, Number(item.shipping_cost || 0));

            if (quantity <= 0) {
                return;
            }

            if (shippingMode === "flat") {
                totalShipping += shippingCost * quantity;
                return;
            }

            if (shippingMode === "default") {
                usesDefaultShipping = true;
            }
        });

        if (usesDefaultShipping && String(storefrontSettings.shipping_option || "free") === "flat") {
            totalShipping += Math.max(0, Number(storefrontSettings.shipping_cost || 0));
        }

        return totalShipping;
    }

    function updateCartCounter() {
        if (!cartLink) {
            return;
        }

        const totalQuantity = getCartItems().reduce(function (total, item) {
            return total + Number(item.quantity || 0);
        }, 0);

        cartLink.textContent = "Cart (" + totalQuantity + ")";
    }

    function updateCartSummary(subtotal, items) {
        const shippingCost = subtotal > 0 ? calculateShippingAmount(items || []) : 0;
        const summaryRows = document.querySelectorAll(".cart-summary .summary-row");
        const subtotalValue = document.getElementById("cartSubtotal") || (summaryRows[0] ? summaryRows[0].querySelector("span:last-child") : null);
        const shippingValue = document.getElementById("cartShipping") || (summaryRows[1] ? summaryRows[1].querySelector("span:last-child") : null);
        const totalValue = document.getElementById("cartTotal") || document.querySelector(".cart-summary .total span:last-child");

        if (subtotalValue) {
            subtotalValue.textContent = formatCurrency(subtotal);
        }

        if (shippingValue) {
            shippingValue.textContent = formatShippingValue(shippingCost);
        }

        if (totalValue) {
            totalValue.textContent = formatCurrency(subtotal + shippingCost);
        }
    }

    function updateCheckoutSummary(subtotal, items) {
        const shippingCost = subtotal > 0 ? calculateShippingAmount(items || []) : 0;
        const shippingValue = document.getElementById("checkoutShipping");
        const totalValue = document.getElementById("checkoutTotal");

        if (shippingValue) {
            shippingValue.textContent = formatShippingValue(shippingCost);
        }

        if (totalValue) {
            totalValue.textContent = formatCurrency(subtotal + shippingCost);
        }
    }

    function applyUserAuthState() {
        const loggedInUser = getCurrentUser();

        if (loggedInUser) {
            if (ordersLink) {
                ordersLink.style.display = "inline";
            }

            if (loginLink) {
                loginLink.style.display = "none";
            }

            if (logoutLink) {
                logoutLink.style.display = "inline";
            }

            if (userGreeting) {
                userGreeting.style.display = "inline";
                userGreeting.textContent = loggedInUser;
            }
        } else {
            if (ordersLink) {
                ordersLink.style.display = "none";
            }

            if (loginLink) {
                loginLink.style.display = "inline";
            }

            if (logoutLink) {
                logoutLink.style.display = "none";
            }

            if (userGreeting) {
                userGreeting.style.display = "none";
                userGreeting.textContent = "";
            }
        }
    }

    function setupLogoutModal() {
        if (logoutLink && logoutConfirmModal) {
            logoutLink.addEventListener("click", function (event) {
                event.preventDefault();
                logoutConfirmModal.classList.add("show");
            });
        }

        if (cancelUserLogout && logoutConfirmModal) {
            cancelUserLogout.addEventListener("click", function () {
                logoutConfirmModal.classList.remove("show");
            });
        }

        if (confirmUserLogout && logoutConfirmModal) {
            confirmUserLogout.addEventListener("click", function () {
                fetchCsrfToken()
                .then(function (csrfToken) {
                    clearBuyNowItems();
                    return fetch(getPhpBasePath() + "logout.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: buildFormBody({
                            csrf_token: csrfToken
                        })
                    });
                })
                .then(function () {
                    currentUser = null;
                    logoutConfirmModal.classList.remove("show");
                    applyUserAuthState();
                    updateCartCounter();
                    window.location.href = getHomePath();
                })
                .catch(function () {
                    showAppMessage("Unable to logout right now.", "error");
                });
            });
        }
    }

    function loadStorefrontSettings() {
        return fetch(getPhpBasePath() + "public_settings.php")
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.status === "success" && data.settings) {
                storefrontSettings = data.settings;
            }

            applyStoreEmail();

        })
        .catch(function () {
            applyStoreEmail();
            return storefrontSettings;
        });
    }

    function initCartPage() {
        const cartTableBody = document.getElementById("cartTableBody");
        const clearCartBtn = document.getElementById("clearCartBtn");
        const clearCartModal = document.getElementById("clearCartModal");
        const cancelClear = document.getElementById("cancelClear");
        const confirmClear = document.getElementById("confirmClear");
        const checkoutBtn = document.getElementById("checkoutBtn");
        const emptyCartModal = document.getElementById("emptyCartModal");
        const closeEmptyCart = document.getElementById("closeEmptyCart");

        if (!cartTableBody) {
            return;
        }

        let cartItems = getCartItems();
        let validatedCartState = {
            items: cartItems,
            hasInvalidItems: false,
            validItems: cartItems
        };
        let activeProducts = [];

        function persistCart() {
            setCartItems(cartItems);
            updateCartCounter();
        }

        function syncCheckoutAvailability() {
            if (!checkoutBtn) {
                return;
            }

            checkoutBtn.disabled = !validatedCartState.validItems.length;
            checkoutBtn.title = !validatedCartState.validItems.length ? "Add at least one available item to continue." : "";
        }

        function renderCart() {
            let grandTotal = 0;

            cartTableBody.innerHTML = "";

            if (!cartItems.length) {
                cartTableBody.innerHTML = "<tr><td colspan='5'>Your cart is empty.</td></tr>";
                updateCartSummary(0, []);
                validatedCartState = {
                    items: [],
                    hasInvalidItems: false,
                    validItems: []
                };
                syncCheckoutAvailability();
                return;
            }

            validatedCartState = buildCartValidation(cartItems, activeProducts);

            validatedCartState.items.forEach(function (product, index) {
                const subtotal = Number(product.price) * Number(product.quantity || 0);
                if (!product.isUnavailable) {
                    grandTotal += subtotal;
                }

                const tr = document.createElement("tr");
                if (product.isUnavailable) {
                    tr.className = "cart-item-warning-row";
                }

                const tdInfo = document.createElement("td");
                const divInfo = document.createElement("div");
                divInfo.className = "product-info";

                const img = document.createElement("img");
                img.src = getImagePath(product.image);
                img.alt = product.name;

                const divText = document.createElement("div");
                const pName = document.createElement("p");
                pName.className = "product-name";
                pName.textContent = product.name;

                const spanSize = document.createElement("span");
                spanSize.textContent = product.size;

                divText.appendChild(pName);
                divText.appendChild(spanSize);

                if (product.isUnavailable) {
                    const pExcluded = document.createElement("p");
                    pExcluded.className = "cart-item-excluded";
                    pExcluded.textContent = "Excluded from checkout";
                    divText.appendChild(pExcluded);
                }

                if (product.warningMessage) {
                    const pWarning = document.createElement("p");
                    pWarning.className = "cart-item-warning";
                    pWarning.textContent = product.warningMessage;
                    divText.appendChild(pWarning);
                }

                divInfo.appendChild(img);
                divInfo.appendChild(divText);
                tdInfo.appendChild(divInfo);

                const tdQty = document.createElement("td");
                const divQty = document.createElement("div");
                divQty.className = "qty-control";

                const btnDec = document.createElement("span");
                btnDec.className = "qty-btn decrease";
                btnDec.setAttribute("data-index", index);
                btnDec.textContent = "-";

                const spanVal = document.createElement("span");
                spanVal.className = "qty-value";
                spanVal.textContent = product.quantity;

                const btnInc = document.createElement("span");
                btnInc.className = "qty-btn increase" + (product.isUnavailable ? " disabled" : "");
                btnInc.setAttribute("data-index", index);
                btnInc.textContent = "+";

                divQty.appendChild(btnDec);
                divQty.appendChild(spanVal);
                divQty.appendChild(btnInc);
                tdQty.appendChild(divQty);

                const tdPrice = document.createElement("td");
                tdPrice.textContent = formatCurrency(product.price);

                const tdSub = document.createElement("td");
                tdSub.textContent = product.isUnavailable ? "-" : formatCurrency(subtotal);

                const tdRemove = document.createElement("td");
                const spanRemove = document.createElement("span");
                spanRemove.className = "remove-btn";
                spanRemove.setAttribute("data-index", index);
                spanRemove.style.cursor = "pointer";
                spanRemove.textContent = "x";
                tdRemove.appendChild(spanRemove);

                tr.appendChild(tdInfo);
                tr.appendChild(tdQty);
                tr.appendChild(tdPrice);
                tr.appendChild(tdSub);
                tr.appendChild(tdRemove);

                cartTableBody.appendChild(tr);
            });

            updateCartSummary(grandTotal, validatedCartState.validItems);
            syncCheckoutAvailability();
            attachCartEvents();
        }

        function attachCartEvents() {
            document.querySelectorAll(".increase").forEach(function (button) {
                button.addEventListener("click", function () {
                    const index = Number(this.getAttribute("data-index"));
                    const liveItem = validatedCartState.items[index];
                    const maxStock = Number(liveItem ? liveItem.availableStock : cartItems[index].stock || 0);

                    if (liveItem && liveItem.isUnavailable) {
                        renderCart();
                        showAppMessage(liveItem.warningMessage || "This item is unavailable.", "error");
                        return;
                    }

                    if (maxStock > 0 && Number(cartItems[index].quantity) >= maxStock) {
                        renderCart();
                        showAppMessage("You cannot add more than the available stock.", "error");
                        return;
                    }

                    cartItems[index].quantity += 1;
                    persistCart();
                    renderCart();
                });
            });

            document.querySelectorAll(".decrease").forEach(function (button) {
                button.addEventListener("click", function () {
                    const index = Number(this.getAttribute("data-index"));

                    if (Number(cartItems[index].quantity) > 1) {
                        cartItems[index].quantity -= 1;
                        persistCart();
                        renderCart();
                    }
                });
            });

            document.querySelectorAll(".remove-btn").forEach(function (button) {
                button.addEventListener("click", function () {
                    const index = Number(this.getAttribute("data-index"));
                    cartItems.splice(index, 1);
                    persistCart();
                    renderCart();
                });
            });
        }

        if (clearCartBtn && clearCartModal) {
            clearCartBtn.addEventListener("click", function () {
                clearCartModal.classList.add("show");
            });
        }

        if (cancelClear && clearCartModal) {
            cancelClear.addEventListener("click", function () {
                clearCartModal.classList.remove("show");
            });
        }

        if (confirmClear && clearCartModal) {
            confirmClear.addEventListener("click", function () {
                cartItems = [];
                clearCartItems();
                updateCartCounter();
                updateCartSummary(0, []);
                renderCart();
                clearCartModal.classList.remove("show");
            });
        }

        if (checkoutBtn) {
            checkoutBtn.addEventListener("click", function () {
                if (!getCurrentUser()) {
                    showAppMessage("Please login before proceeding to checkout.", "error", function () {
                        window.location.href = getPagePath("login.html");
                    });
                    return;
                }

                if (!validatedCartState.validItems.length) {
                    if (emptyCartModal) {
                        emptyCartModal.classList.add("show");
                    }
                    return;
                }

                window.location.href = getPagePath("checkout.html");
            });
        }

        if (closeEmptyCart && emptyCartModal) {
            closeEmptyCart.addEventListener("click", function () {
                emptyCartModal.classList.remove("show");
            });
        }

        fetchActiveProducts().then(function (products) {
            activeProducts = products;
            renderCart();
        });
    }

    function initCheckoutPage() {
        const checkoutItems = document.getElementById("checkoutItems");
        const checkoutForm = document.getElementById("checkoutForm");
        const checkoutCsrfTokenInput = document.getElementById("checkoutCsrfToken");
        const continueShopping = document.getElementById("continueShopping");
        const successModal = document.getElementById("orderSuccessModal");
        const successTitle = document.getElementById("orderSuccessTitle");
        const successText = document.getElementById("orderSuccessText");
        const placeOrderButton = checkoutForm ? checkoutForm.querySelector(".place-order-btn") : null;
        const codConfirmation = document.getElementById("codConfirmation");
        const codSafeguard = document.getElementById("codSafeguard");
        let activeProducts = [];
        let checkoutValidation = {
            items: [],
            hasInvalidItems: false,
            validItems: []
        };

        if (!checkoutItems || !checkoutForm) {
            return;
        }

        function getSelectedPaymentMethod() {
            return "cod";
        }

        function updatePlaceOrderButtonLabel(paymentMethod) {
            if (!placeOrderButton) {
                return;
            }

            placeOrderButton.textContent = "Place Order (Cash on Delivery)";
        }

        function updateSuccessModal() {
            if (!successTitle || !successText) {
                return;
            }

            successTitle.textContent = "Order Placed Successfully";
            successText.textContent = "Your Cash on Delivery order has been received. We will review it shortly and contact you if any delivery clarification is needed.";
        }

        function updatePaymentPanels() {
            const paymentMethod = getSelectedPaymentMethod();

            updatePlaceOrderButtonLabel(paymentMethod);
            updateSuccessModal();

            if (codSafeguard) {
                codSafeguard.style.display = "block";
            }

            if (codConfirmation) {
                codConfirmation.required = true;
            }
        }

        function ensureCheckoutCsrfToken() {
            if (checkoutCsrfTokenInput && checkoutCsrfTokenInput.value.trim() !== "") {
                return Promise.resolve(checkoutCsrfTokenInput.value.trim());
            }

            return fetchCsrfToken()
            .then(function (csrfToken) {
                if (checkoutCsrfTokenInput) {
                    checkoutCsrfTokenInput.value = csrfToken;
                }

                return csrfToken;
            });
        }

        function renderCheckout() {
            const items = getActiveCheckoutItems();
            let total = 0;

            checkoutItems.innerHTML = "";

            if (!items.length) {
                const emptyMessage = document.createElement("p");
                emptyMessage.textContent = "Your cart is empty.";
                checkoutItems.appendChild(emptyMessage);
                updateCheckoutSummary(0, []);
                checkoutValidation = {
                    items: [],
                    hasInvalidItems: false,
                    validItems: []
                };
                updatePaymentPanels();
                return;
            }

            checkoutValidation = buildCartValidation(items, activeProducts);

            checkoutValidation.items.forEach(function (product) {
                const subtotal = Number(product.price) * Number(product.quantity || 0);
                if (!product.isUnavailable) {
                    total += subtotal;
                }

                const itemDiv = document.createElement("div");
                itemDiv.className = "checkout-item";

                const spanLabel = document.createElement("span");
                spanLabel.textContent = product.name + " (" + product.size + ") x " + product.quantity;

                const spanVal = document.createElement("span");
                spanVal.textContent = product.isUnavailable ? "Unavailable" : formatCurrency(subtotal);

                itemDiv.appendChild(spanLabel);
                itemDiv.appendChild(spanVal);
                checkoutItems.appendChild(itemDiv);

                if (product.warningMessage) {
                    const pWarn = document.createElement("p");
                    pWarn.className = "cart-item-warning checkout-warning";
                    pWarn.textContent = product.warningMessage;
                    checkoutItems.appendChild(pWarn);
                }
            });

            updateCheckoutSummary(total, checkoutValidation.validItems);
            updatePaymentPanels();
        }

        checkoutForm.addEventListener("submit", function (event) {
            const fullName = document.getElementById("fullName");
            const phone = document.getElementById("phone");
            const address = document.getElementById("address");
            const city = document.getElementById("city");
            const pincode = document.getElementById("pincode");
            const paymentMethod = getSelectedPaymentMethod();
            const phonePattern = /^[0-9]{10}$/;
            const pincodePattern = /^[0-9]{6}$/;
            const cartItems = checkoutValidation.validItems;
            const subtotal = cartItems.reduce(function (total, item) {
                return total + (Number(item.price) * Number(item.quantity || 0));
            }, 0);
            const shippingCost = calculateShippingAmount(cartItems);
            const total = subtotal + shippingCost;

            event.preventDefault();

            if (placeOrderButton) {
                placeOrderButton.disabled = true;
            }

            if (fullName.value.trim().length < 3) {
                showAppMessage("Please enter a valid full name.", "error");
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }
                return;
            }

            if (!phonePattern.test(phone.value)) {
                showAppMessage("Phone number must be 10 digits.", "error");
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }
                return;
            }

            if (address.value.trim().length < 5) {
                showAppMessage("Please enter a valid address.", "error");
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }
                return;
            }

            if (city.value.trim().length < 3) {
                showAppMessage("Please enter a valid city.", "error");
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }
                return;
            }

            if (!pincodePattern.test(pincode.value)) {
                showAppMessage("Pincode must be 6 digits.", "error");
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }
                return;
            }

            if (!cartItems.length) {
                showAppMessage("Your cart is empty.", "error");
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }
                return;
            }

            if (paymentMethod === "cod" && (!codConfirmation || !codConfirmation.checked)) {
                showAppMessage("Please confirm your COD contact details before placing the order.", "error");
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }
                return;
            }

            const csrfToken = checkoutCsrfTokenInput ? checkoutCsrfTokenInput.value.trim() : "";

            if (csrfToken === "") {
                showAppMessage("Checkout security token missing. Please refresh and try again.", "error");
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }
                return;
            }

            fetch(getPhpBasePath() + "place_order.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: buildFormBody({
                    csrf_token: csrfToken,
                    name: fullName.value,
                    username: getCurrentUser(),
                    phone: phone.value,
                    address: address.value,
                    city: city.value,
                    pincode: pincode.value,
                    total: total,
                    payment_method: paymentMethod,
                    cod_confirmation: codConfirmation && codConfirmation.checked ? 1 : 0,
                    cart: JSON.stringify(cartItems)
                })
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }

                if (data.status !== "success") {
                    showAppMessage(data.message || "Unable to place the order.", "error");
                    return;
                }

                if (getBuyNowItems().length) {
                    clearBuyNowItems();
                } else {
                    clearCartItems();
                }

                checkoutForm.reset();
                updateCartCounter();
                updateCartSummary(0, []);
                renderCheckout();
                updateSuccessModal(paymentMethod);

                if (successModal) {
                    successModal.classList.add("show");
                }
            })
            .catch(function () {
                if (placeOrderButton) {
                    placeOrderButton.disabled = false;
                }
                showAppMessage("Order request failed. Please try again.", "error");
            });
        });

        if (continueShopping) {
            continueShopping.addEventListener("click", function () {
                window.location.href = getHomePath();
            });
        }

        fetchActiveProducts().then(function (products) {
            activeProducts = products;
            renderCheckout();
        });

        ensureCheckoutCsrfToken().catch(function (error) {
            showAppMessage(error.message || "Unable to initialize checkout security.", "error");
        });
    }

    function initOrdersPage() {
        const ordersList = document.getElementById("ordersList");
        const ordersEmptyState = document.getElementById("ordersEmptyState");
        const ordersPageMessage = document.getElementById("ordersPageMessage");
        const currentUser = getCurrentUser();

        if (!ordersList) {
            return;
        }

        if (!currentUser) {
            window.location.href = getPagePath("login.html");
            return;
        }

        function getStatusClass(status) {
            const normalizedStatus = String(status || "").toLowerCase();

            if (normalizedStatus === "cancelled") {
                return "order-status-cancelled";
            }

            if (normalizedStatus === "delivered") {
                return "order-status-delivered";
            }

            if (normalizedStatus === "shipped") {
                return "order-status-shipped";
            }

            if (normalizedStatus === "processing") {
                return "order-status-processing";
            }

            return "order-status-pending";
        }

        function buildPaymentMetaRow(labelText, valueText, extraClassName) {
            const row = document.createElement("div");
            row.className = "order-payment-meta";

            const label = document.createElement("span");
            label.textContent = labelText;

            const value = document.createElement("strong");
            if (extraClassName) {
                value.className = extraClassName;
            }
            value.textContent = valueText;

            row.appendChild(label);
            row.appendChild(value);
            return row;
        }

        function renderOrders(orders) {
            ordersList.innerHTML = "";

            if (ordersPageMessage) {
                ordersPageMessage.textContent = "";
            }

            if (!orders.length) {
                if (ordersEmptyState) {
                    ordersEmptyState.style.display = "block";
                }
                return;
            }

            if (ordersEmptyState) {
                ordersEmptyState.style.display = "none";
            }

            orders.forEach(function (order) {
                const card = document.createElement("article");
                card.className = "order-card";
                const isCancelled = String(order.status || "").toLowerCase() === "cancelled";
                const header = document.createElement("div");
                header.className = "order-card-header";

                const headerInfo = document.createElement("div");
                const label = document.createElement("p");
                label.className = "order-label";
                label.textContent = "Order Details";
                const title = document.createElement("h3");
                title.textContent = formatOrderDateValue(order);
                headerInfo.appendChild(label);
                headerInfo.appendChild(title);

                const status = document.createElement("span");
                status.className = "order-status " + getStatusClass(order.status);
                status.textContent = String(order.status || "");

                header.appendChild(headerInfo);
                header.appendChild(status);

                const body = document.createElement("div");
                body.className = "order-card-body";

                if (isCancelled) {
                    const notice = document.createElement("div");
                    notice.className = "order-notice order-notice-cancelled";
                    notice.textContent = "This order has been cancelled by the store. If you already made plans around this purchase, please contact support before placing it again.";
                    body.appendChild(notice);
                }

                const paymentCard = document.createElement("div");
                paymentCard.className = "order-payment-card";
                paymentCard.appendChild(buildPaymentMetaRow("Payment Method", getPaymentMethodLabel(order.payment_method)));
                paymentCard.appendChild(buildPaymentMetaRow("Payment Status", String(order.payment_status || ""), "order-payment-status " + getPaymentStatusClass(order.payment_status)));

                body.appendChild(paymentCard);

                const itemsList = document.createElement("div");
                itemsList.className = "order-items-list";

                if (Array.isArray(order.items) && order.items.length) {
                    order.items.forEach(function (item) {
                        const row = document.createElement("div");
                        row.className = "order-item-row";

                        const left = document.createElement("div");
                        const productName = document.createElement("strong");
                        productName.textContent = String(item.product_name || "");
                        const productSize = document.createElement("span");
                        productSize.textContent = String(item.size || "");
                        left.appendChild(productName);
                        left.appendChild(productSize);

                        const meta = document.createElement("div");
                        meta.className = "order-item-meta";
                        const quantity = document.createElement("span");
                        quantity.textContent = "Qty: " + Number(item.quantity || 0);
                        const total = document.createElement("span");
                        total.textContent = formatCurrency(Number(item.price || 0) * Number(item.quantity || 0));
                        meta.appendChild(quantity);
                        meta.appendChild(total);

                        row.appendChild(left);
                        row.appendChild(meta);
                        itemsList.appendChild(row);
                    });
                } else {
                    const emptyItems = document.createElement("p");
                    emptyItems.className = "order-muted";
                    emptyItems.textContent = "No item details available.";
                    itemsList.appendChild(emptyItems);
                }

                body.appendChild(itemsList);

                const footer = document.createElement("div");
                footer.className = "order-card-footer";

                const addressBlock = document.createElement("div");
                const addressLabel = document.createElement("p");
                addressLabel.className = "order-label";
                addressLabel.textContent = "Delivery Address";
                const addressValue = document.createElement("p");
                addressValue.className = "order-muted";
                addressValue.textContent = String(order.address || "") + ", " + String(order.city || "") + " - " + String(order.pincode || "");
                addressBlock.appendChild(addressLabel);
                addressBlock.appendChild(addressValue);

                const totalBlock = document.createElement("div");
                totalBlock.className = "order-total-block";
                const totalLabel = document.createElement("p");
                totalLabel.className = "order-label";
                totalLabel.textContent = "Total";
                const totalValue = document.createElement("strong");
                totalValue.textContent = formatCurrency(order.total);
                totalBlock.appendChild(totalLabel);
                totalBlock.appendChild(totalValue);

                footer.appendChild(addressBlock);
                footer.appendChild(totalBlock);
                body.appendChild(footer);

                card.appendChild(header);
                card.appendChild(body);

                ordersList.appendChild(card);
            });
        }

        fetch(getPhpBasePath() + "get_user_orders.php")
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.status !== "success") {
                throw new Error(data.message || "Unable to load your orders.");
            }

            renderOrders(Array.isArray(data.orders) ? data.orders : []);
        })
        .catch(function (error) {
            if (ordersEmptyState) {
                ordersEmptyState.style.display = "none";
            }

            if (ordersPageMessage) {
                ordersPageMessage.textContent = error.message || "Unable to load your orders right now.";
            }
        });
    }

    function initContactPage() {
        const contactForm = document.getElementById("contactForm");
        const contactFormMessage = document.getElementById("contactFormMessage");

        if (!contactForm) {
            return;
        }

        function showContactMessage(message, type) {
            if (!contactFormMessage) {
                showAppMessage(message, type === "success" ? "success" : "error");
                return;
            }

            contactFormMessage.textContent = message;
            contactFormMessage.className = "contact-form-message " + type;
        }

        contactForm.addEventListener("submit", function (event) {
            const name = document.getElementById("contactName");
            const email = document.getElementById("contactEmail");
            const message = document.getElementById("contactMessage");

            event.preventDefault();

            if (name.value.trim().length < 3) {
                showContactMessage("Please enter a valid name.", "error");
                return;
            }

            if (!email.value.includes("@") || !email.value.includes(".")) {
                showContactMessage("Please enter a valid email address.", "error");
                return;
            }

            if (message.value.trim().length < 10) {
                showContactMessage("Message should be at least 10 characters.", "error");
                return;
            }

            showContactMessage("Sending your message...", "");

            fetchCsrfToken()
            .then(function (csrfToken) {
                return fetch(getPhpBasePath() + "contact.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: buildFormBody({
                        csrf_token: csrfToken,
                        name: name.value,
                        email: email.value,
                        message: message.value
                    })
                })
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.status !== "success") {
                    showContactMessage(data.message || "Unable to send your message.", "error");
                    return;
                }

                showAppMessage(data.message || "Message sent successfully.", "success");
                showContactMessage("", "");
                contactForm.reset();
            })
            .catch(function () {
                showContactMessage("Unable to send your message right now. Please try again.", "error");
            });
        });
    }

    function initLoginPage() {
        const loginForm = document.getElementById("loginForm");
        const toggleLoginPassword = document.getElementById("toggleLoginPassword");
        const loginPassword = document.getElementById("loginPassword");

        if (toggleLoginPassword && loginPassword) {
            toggleLoginPassword.addEventListener("change", function () {
                loginPassword.type = loginPassword.type === "password" ? "text" : "password";
            });
        }

        if (!loginForm) {
            return;
        }

        loginForm.addEventListener("submit", function (event) {
            const username = document.getElementById("loginUsername");
            const password = document.getElementById("loginPassword");

            event.preventDefault();

            if (username.value.trim().length < 3) {
                showAppMessage("Username must be at least 3 characters.", "error");
                return;
            }

            if (password.value.length < 6) {
                showAppMessage("Password must be at least 6 characters.", "error");
                return;
            }

            fetchCsrfToken()
            .then(function (csrfToken) {
                return fetch(getPhpBasePath() + "login.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: buildFormBody({
                        csrf_token: csrfToken,
                        username: username.value,
                        password: password.value
                    })
                })
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.status === "success") {
                    currentUser = data.user ? data.user.username : username.value;
                    applyUserAuthState();
                    updateCartCounter();
                    showAppMessage("Login successful.", "success", function () {
                        window.location.href = getHomePath();
                    });
                    return;
                }

                if (data.status === "wrongpassword") {
                    showAppMessage("Incorrect password.", "error");
                    return;
                }

                if (data.status === "nouser") {
                    showAppMessage("User not found.", "error");
                    return;
                }

                if (data.status === "throttled") {
                    showAppMessage(data.message || "Too many login attempts. Please wait before trying again.", "error");
                    return;
                }

                showAppMessage(data.message || "Login failed.", "error");
            })
            .catch(function () {
                showAppMessage("Login request failed. Please check your server and database connection.", "error");
            });
        });
    }

    function initRegisterPage() {
        const registerForm = document.getElementById("registerForm");
        const toggleRegisterPassword = document.getElementById("toggleRegisterPassword");
        const registerPassword = document.getElementById("registerPassword");
        const confirmPassword = document.getElementById("confirmPassword");

        if (toggleRegisterPassword && registerPassword && confirmPassword) {
            toggleRegisterPassword.addEventListener("change", function () {
                const type = this.checked ? "text" : "password";
                registerPassword.type = type;
                confirmPassword.type = type;
            });
        }

        if (!registerForm) {
            return;
        }

        registerForm.addEventListener("submit", function (event) {
            const username = document.getElementById("registerUsername");
            const email = document.getElementById("registerEmail");
            const password = document.getElementById("registerPassword");

            event.preventDefault();

            if (username.value.trim().length < 3) {
                showAppMessage("Username must be at least 3 characters.", "error");
                return;
            }

            if (!email.value.includes("@") || !email.value.includes(".")) {
                showAppMessage("Please enter a valid email address.", "error");
                return;
            }

            if (password.value.length < 6) {
                showAppMessage("Password must be at least 6 characters.", "error");
                return;
            }

            if (password.value !== confirmPassword.value) {
                showAppMessage("Passwords do not match.", "error");
                return;
            }

            fetchCsrfToken()
            .then(function (csrfToken) {
                return fetch(getPhpBasePath() + "register.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: buildFormBody({
                        csrf_token: csrfToken,
                        username: username.value,
                        email: email.value,
                        password: password.value
                    })
                })
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.status === "success") {
                    showAppMessage("Registration successful.", "success");
                    registerForm.reset();
                    return;
                }

                if (data.status === "exists") {
                    showAppMessage("Username or email already exists.", "error");
                    return;
                }

                showAppMessage(data.message || "Registration failed.", "error");
            })
            .catch(function () {
                showAppMessage("Registration request failed. Please check your server and database connection.", "error");
            });
        });
    }

    function initHomepageSlider() {
        const slides = document.querySelectorAll(".slide");
        const dots = document.querySelectorAll(".dot");

        if (!slides.length || !dots.length) {
            return;
        }

        let current = 0;

        function showSlide(index) {
            slides.forEach(function (slide) {
                slide.classList.remove("active");
            });

            dots.forEach(function (dot) {
                dot.classList.remove("active");
            });

            slides[index].classList.add("active");
            dots[index].classList.add("active");
        }

        showSlide(current);

        window.setInterval(function () {
            current = (current + 1) % slides.length;
            showSlide(current);
        }, 3000);
    }

    setupLogoutModal();
    const settingsReady = loadStorefrontSettings();
    initContactPage();
    initRegisterPage();
    initHomepageSlider();
    Promise.all([authReady, settingsReady]).then(function () {
        updateCartCounter();
        applyUserAuthState();
        initCartPage();
        initCheckoutPage();
        initOrdersPage();
        initLoginPage();
    });
});
