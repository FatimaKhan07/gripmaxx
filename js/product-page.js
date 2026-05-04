document.addEventListener("DOMContentLoaded", function () {
    const allProductsContainer = document.getElementById("allProductsContainer");
    const productLayout = document.querySelector(".product-layout");
    const quantitySelect = document.getElementById("quantitySelect");
    const stockStatus = document.getElementById("stockStatus");
    const addToCartButton = document.getElementById("addToCartBtn");
    const buyNowButton = document.getElementById("buyNowBtn");
    const cartLink = document.getElementById("cartLink");
    const ordersLink = document.getElementById("ordersLink");
    const loginLink = document.getElementById("loginLink");
    const logoutLink = document.getElementById("logoutLink");
    const userGreeting = document.getElementById("userGreeting");
    const logoutModal = document.getElementById("logoutConfirmModal");
    const cancelLogoutButton = document.getElementById("cancelUserLogout");
    const confirmLogoutButton = document.getElementById("confirmUserLogout");
    const cartNotification = document.getElementById("cartNotification");
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get("id");
    let storefrontSettings = {
        store_email: "support@gripmaxx.com"
    };
    let loadedProducts = [];
    let currentUser = null;

    if (!allProductsContainer || !productLayout || !quantitySelect) {
        return;
    }

    function parseStoredItems(key) {
        try {
            return JSON.parse(localStorage.getItem(key) || "[]");
        } catch (error) {
            return [];
        }
    }

    function getCurrentUser() {
        return currentUser;
    }

    function fetchCurrentUser() {
        return fetch("../php/session_user.php")
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
        return key ? parseStoredItems(key) : [];
    }

    function setCartItems(items) {
        const key = getCartStorageKey();

        if (!key) {
            return;
        }

        localStorage.setItem(key, JSON.stringify(items));
    }

    function formatCurrency(amount) {
        return "Rs." + Number(amount || 0).toFixed(2);
    }

    let csrfTokenPromise = null;

    function fetchCsrfToken(forceRefresh) {
        if (forceRefresh || !csrfTokenPromise) {
            csrfTokenPromise = fetch("../php/csrf_token.php")
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

    function applyStoreEmail() {
        document.querySelectorAll(".store-email").forEach(function (node) {
            node.textContent = storefrontSettings.store_email;
        });
    }

    function updateCartCounter() {
        if (!cartLink) {
            return;
        }

        const cartItems = getCartItems();
        const totalQuantity = cartItems.reduce(function (total, item) {
            return total + Number(item.quantity || 0);
        }, 0);

        cartLink.textContent = "Cart (" + totalQuantity + ")";
    }

    function applyAuthState() {
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
        if (logoutLink && logoutModal) {
            logoutLink.addEventListener("click", function (event) {
                event.preventDefault();
                logoutModal.classList.add("show");
            });
        }

        if (cancelLogoutButton && logoutModal) {
            cancelLogoutButton.addEventListener("click", function () {
                logoutModal.classList.remove("show");
            });
        }

        if (confirmLogoutButton && logoutModal) {
            confirmLogoutButton.addEventListener("click", function () {
                const buyNowKey = getBuyNowStorageKey();
                if (buyNowKey) {
                    localStorage.removeItem(buyNowKey);
                }

                fetchCsrfToken()
                .then(function (csrfToken) {
                    return fetch("../php/logout.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: new URLSearchParams({
                            csrf_token: csrfToken
                        }).toString()
                    });
                })
                .then(function () {
                    currentUser = null;
                    logoutModal.classList.remove("show");
                    applyAuthState();
                    updateCartCounter();
                    window.location.href = "../index.html";
                })
                .catch(function () {
                    showAppMessage("Unable to logout right now.", "error");
                });
            });
        }
    }

    function getReservedQuantity(id) {
        const cartItems = getCartItems();
        
        return cartItems.reduce(function (total, item) {
            return Number(item.id) === Number(id) ? total + Number(item.quantity || 0) : total;
        }, 0);
    }

    function setStockStatusText(isOutOfStock) {
        if (!stockStatus) {
            return;
        }

        stockStatus.className = "stock-status";
        stockStatus.textContent = isOutOfStock ? "Out of stock" : "";

        if (isOutOfStock) {
            stockStatus.classList.add("out-of-stock");
        }
    }

    function syncButtons() {
        const selectedOption = quantitySelect.options[quantitySelect.selectedIndex];
        const selectedStock = selectedOption ? Number(selectedOption.getAttribute("data-stock") || 0) : 0;
        const isAvailable = Boolean(selectedOption) && !selectedOption.disabled && selectedStock > 0;

        if (addToCartButton) {
            addToCartButton.disabled = !isAvailable;
        }

        if (buyNowButton) {
            buyNowButton.disabled = !isAvailable;
        }

        setStockStatusText(!isAvailable);
    }

    function showAddedToCartNotification() {
        if (!cartNotification) {
            return;
        }

        cartNotification.classList.add("show");

        window.setTimeout(function () {
            cartNotification.classList.remove("show");
        }, 2000);
    }

    function getProductVariants(products, product) {
        return products.filter(function (item) {
            return item.name === product.name;
        });
    }

    function renderListing(products) {
        productLayout.style.display = "none";
        allProductsContainer.style.display = "grid";
        allProductsContainer.style.gridTemplateColumns = "repeat(auto-fit, minmax(220px, 1fr))";
        allProductsContainer.style.gap = "25px";
        allProductsContainer.innerHTML = "";

        if (!Array.isArray(products) || !products.length) {
            const emptyState = document.createElement("p");
            emptyState.style.color = "#aaa";
            emptyState.style.gridColumn = "1 / -1";
            emptyState.style.textAlign = "center";
            emptyState.textContent = "No products are available right now.";
            allProductsContainer.appendChild(emptyState);
            return;
        }

        const groupedProducts = {};

        products.forEach(function (product) {
            if (!groupedProducts[product.name]) {
                groupedProducts[product.name] = [];
            }

            groupedProducts[product.name].push(product);
        });

        Object.keys(groupedProducts).forEach(function (productName) {
            const group = groupedProducts[productName];
            const firstProduct = group[0];
            const isPopular = group.some(function (item) {
                return Number(item.is_popular || 0) === 1;
            });
            const minPrice = Math.min.apply(null, group.map(function (item) {
                return Number(item.price);
            }));
            const sizes = group.map(function (item) {
                return item.size;
            }).join(" - ");
            const totalStock = group.reduce(function (total, item) {
                const availableStock = Math.max(0, Number(item.stock || 0) - getReservedQuantity(item.id));
                return total + availableStock;
            }, 0);

            const card = document.createElement("div");
            card.className = "product-card" + (totalStock <= 0 ? " out-of-stock" : "");

            if (isPopular) {
                const badge = document.createElement("span");
                badge.className = "badge";
                badge.textContent = "Popular";
                card.appendChild(badge);
            }

            const img = document.createElement("img");
            img.src = "../images/" + firstProduct.image;
            img.alt = firstProduct.name;
            card.appendChild(img);

            const h3 = document.createElement("h3");
            h3.textContent = firstProduct.name;
            card.appendChild(h3);

            const pSizes = document.createElement("p");
            pSizes.textContent = sizes;
            card.appendChild(pSizes);

            const pStock = document.createElement("p");
            pStock.textContent = totalStock <= 0 ? "Out of stock" : "";
            card.appendChild(pStock);

            const pPrice = document.createElement("p");
            pPrice.textContent = "Starting " + formatCurrency(minPrice);
            card.appendChild(pPrice);

            const link = document.createElement("a");
            link.href = "product.html?id=" + firstProduct.id;
            const btn = document.createElement("button");
            btn.disabled = totalStock <= 0;
            btn.textContent = totalStock <= 0 ? "Out of Stock" : "View Product";
            link.appendChild(btn);
            card.appendChild(link);

            allProductsContainer.appendChild(card);
        });

        if (!allProductsContainer.children.length) {
            const emptyState = document.createElement("p");
            emptyState.style.color = "#aaa";
            emptyState.style.gridColumn = "1 / -1";
            emptyState.style.textAlign = "center";
            emptyState.textContent = "No products are available right now.";
            allProductsContainer.appendChild(emptyState);
        }
    }

    function renderProduct(products) {
        const product = products.find(function (item) {
            return Number(item.id) === Number(productId);
        });

        if (!product) {
            renderListing(products);
            return;
        }

        productLayout.style.display = "flex";
        allProductsContainer.style.display = "none";

        document.getElementById("productName").innerText = product.name;
        document.getElementById("productImage").src = "../images/" + product.image;
        document.getElementById("productImage").alt = product.name;
        document.getElementById("productDescription").textContent = product.description || "Product description coming soon.";

        quantitySelect.innerHTML = "";

        const variants = getProductVariants(products, product);

        variants.forEach(function (item) {
            const availableStock = Math.max(0, Number(item.stock || 0) - getReservedQuantity(item.id));
            const option = document.createElement("option");

            option.value = item.size;
            option.textContent = item.size + " - " + formatCurrency(item.price) + (availableStock <= 0 ? " (Out of stock)" : "");
            option.disabled = availableStock <= 0;
            option.setAttribute("data-id", item.id);
            option.setAttribute("data-price", item.price);
            option.setAttribute("data-stock", availableStock);
            option.setAttribute("data-image", item.image);

            quantitySelect.appendChild(option);
        });

        syncButtons();
    }

    function rerenderCurrentView() {
        if (!loadedProducts.length) {
            return;
        }

        if (productId) {
            renderProduct(loadedProducts);
        } else {
            renderListing(loadedProducts);
        }
    }

    quantitySelect.addEventListener("change", syncButtons);

    if (addToCartButton) {
        addToCartButton.addEventListener("click", function () {
            const loggedInUser = getCurrentUser();
            const selectedOption = quantitySelect.options[quantitySelect.selectedIndex];

            if (!loggedInUser) {
                showAppMessage("Please login before adding items to cart.", "error", function () {
                    window.location.href = "login.html";
                });
                return;
            }

            if (!selectedOption || selectedOption.disabled) {
                showAppMessage("This variant is out of stock.", "error");
                return;
            }

            const availableStock = Number(selectedOption.getAttribute("data-stock") || 0);
            const variantId = selectedOption.getAttribute("data-id");
            const variantSize = selectedOption.value;
            const variantPrice = selectedOption.getAttribute("data-price");
            const cartItems = getCartItems();
            const existingIndex = cartItems.findIndex(function (item) {
                return String(item.id) === String(variantId) && item.size === variantSize;
            });

            if (existingIndex !== -1) {
                if (Number(cartItems[existingIndex].quantity) >= availableStock) {
                    rerenderCurrentView();
                    showAppMessage("You have reached the available stock for this variant.", "error");
                    return;
                }

                cartItems[existingIndex].quantity += 1;
            } else {
                if (availableStock <= 0) {
                    rerenderCurrentView();
                    showAppMessage("This variant is out of stock.", "error");
                    return;
                }

                cartItems.push({
                    id: variantId,
                    name: document.getElementById("productName").innerText,
                    size: variantSize,
                    price: variantPrice,
                    quantity: 1,
                    stock: availableStock,
                    image: document.getElementById("productImage").getAttribute("src").split("/").pop()
                });
            }

            setCartItems(cartItems);
            updateCartCounter();
            rerenderCurrentView();
            showAddedToCartNotification();
        });
    }

    if (buyNowButton) {
        buyNowButton.addEventListener("click", function () {
            const loggedInUser = getCurrentUser();
            const selectedOption = quantitySelect.options[quantitySelect.selectedIndex];

            if (!loggedInUser) {
                showAppMessage("Please login before purchasing.", "error", function () {
                    window.location.href = "login.html";
                });
                return;
            }

            if (!selectedOption || selectedOption.disabled) {
                showAppMessage("This variant is out of stock.", "error");
                return;
            }

            const availableStock = Number(selectedOption.getAttribute("data-stock") || 0);

            if (availableStock <= 0) {
                showAppMessage("This variant is out of stock.", "error");
                return;
            }

            const buyNowKey = getBuyNowStorageKey();

            if (!buyNowKey) {
                return;
            }

            localStorage.setItem(buyNowKey, JSON.stringify([{
                id: selectedOption.getAttribute("data-id"),
                name: document.getElementById("productName").innerText,
                size: selectedOption.value,
                price: selectedOption.getAttribute("data-price"),
                quantity: 1,
                stock: availableStock,
                image: document.getElementById("productImage").getAttribute("src").split("/").pop()
            }]));

            window.location.href = "checkout.html";
        });
    }

    setupLogoutModal();
    fetchCurrentUser().then(function () {
        updateCartCounter();
        applyAuthState();
    });

    fetch("../php/public_settings.php")
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
    });

    fetch("../php/get_products.php")
    .then(function (response) {
        return response.json();
    })
    .then(function (products) {
        loadedProducts = Array.isArray(products) ? products : [];

        if (productId) {
            renderProduct(loadedProducts);
        } else {
            renderListing(loadedProducts);
        }
    })
    .catch(function () {
        productLayout.style.display = "none";
        allProductsContainer.style.display = "block";
        allProductsContainer.innerHTML = "<p style='color:#aaa;'>Unable to load products right now.</p>";
    });
});
