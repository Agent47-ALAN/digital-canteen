(function () {
    'use strict';

    var app = document.getElementById('app');
    if (!app || !window.DC) {
        return;
    }

    var DC = window.DC;
    var api = app.getAttribute('data-api');
    var loginUrl = app.getAttribute('data-login');
    var cartApi = app.getAttribute('data-cart-api');
    var csrf = app.getAttribute('data-csrf');

    var searchInput = document.getElementById('menu-search');
    var chipBox = document.getElementById('menu-chips');
    var grid = document.getElementById('menu-grid');
    var countText = document.getElementById('menu-count');
    var statusBox = document.getElementById('status');

    var selectedCategory = '';
    var currentRequest = null;
    var searchTimer = null;

    function renderChips(categories) {
        chipBox.textContent = '';

        var stillExists = selectedCategory === '' || categories.some(function (category) {
            return String(category.id) === selectedCategory;
        });
        if (!stillExists) {
            selectedCategory = '';
        }

        function addChip(id, label) {
            var active = selectedCategory === id;
            var chip = DC.el('button', {
                type: 'button',
                className: 'chip' + (active ? ' is-active' : ''),
                'aria-pressed': active ? 'true' : 'false',
                text: label
            });
            chip.addEventListener('click', function () {
                selectedCategory = id;
                load();
            });
            chipBox.appendChild(chip);
        }

        addChip('', 'All');
        categories.forEach(function (category) {
            addChip(String(category.id), category.name);
        });
    }

    function addToCart(item, button) {
        button.disabled = true;
        DC.request('POST', cartApi, {
            csrf_token: csrf,
            action: 'add',
            food_item_id: item.id,
            quantity: 1
        }, function (result) {
            button.disabled = false;
            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }
            DC.showStatus(statusBox, result.success ? 'success' : 'error', result.message);
            if (result.success && result.data && result.data.cart) {
                DC.setCartCount(result.data.cart.count);
            }
        });
    }

    function renderItems(items) {
        grid.textContent = '';

        if (items.length === 0) {
            grid.appendChild(DC.el('p', { className: 'empty-state', text: 'No food items match your search. Try another word or choose All.' }));
            return;
        }

        items.forEach(function (item) {
            var card = DC.el('article', { className: 'menu-card' + (item.is_available ? '' : ' is-unavailable') }, [
                DC.el('p', { className: 'menu-category', text: item.category_name }),
                DC.el('h3', { text: item.name }),
                item.description ? DC.el('p', { className: 'menu-description', text: item.description }) : null,
                DC.el('div', { className: 'menu-foot' }, [
                    DC.el('span', { className: 'price', text: DC.money(item.price) }),
                    DC.el('span', {
                        className: 'badge ' + (item.is_available ? 'badge-on' : 'badge-warn'),
                        text: item.is_available ? 'Available' : 'Not available now'
                    })
                ])
            ]);
            if (item.is_available) {
                var addButton = DC.el('button', { type: 'button', className: 'button button-small add-button', text: 'Add to cart' });
                addButton.setAttribute('aria-label', 'Add ' + item.name + ' to cart');
                addButton.addEventListener('click', function () { addToCart(item, addButton); });
                card.appendChild(addButton);
            }
            grid.appendChild(card);
        });
    }

    function load() {
        if (currentRequest) {
            currentRequest.abort();
        }
        grid.setAttribute('aria-busy', 'true');
        DC.hideStatus(statusBox);

        currentRequest = DC.request('GET', api, {
            q: searchInput.value,
            category_id: selectedCategory
        }, function (result) {
            currentRequest = null;
            grid.setAttribute('aria-busy', 'false');
            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }
            if (!result.success) {
                DC.showStatus(statusBox, 'error', result.message);
                return;
            }
            renderChips(result.data.categories || []);
            renderItems(result.data.items || []);
            var count = result.data.count || 0;
            countText.textContent = count === 1 ? '1 item' : count + ' items';
        });
    }

    searchInput.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(load, 300);
    });
    document.getElementById('menu-form').addEventListener('submit', function (event) {
        event.preventDefault();
        window.clearTimeout(searchTimer);
        load();
    });

    load();
}());
