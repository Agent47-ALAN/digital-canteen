(function () {
    'use strict';

    var app = document.getElementById('app');
    if (!app || !window.DC) {
        return;
    }

    var DC = window.DC;
    var api = app.getAttribute('data-api');
    var csrf = app.getAttribute('data-csrf');
    var loginUrl = app.getAttribute('data-login');

    var form = document.getElementById('food-form');
    var statusBox = document.getElementById('status');
    var tableBody = document.getElementById('food-rows');
    var formTitle = document.getElementById('form-title');
    var saveButton = document.getElementById('save-button');
    var cancelButton = document.getElementById('cancel-button');
    var filterSearch = document.getElementById('filter-q');
    var filterCategory = document.getElementById('filter-category');
    var fields = {
        id: document.getElementById('food-id'),
        category: document.getElementById('category_id'),
        name: document.getElementById('name'),
        description: document.getElementById('description'),
        price: document.getElementById('price'),
        available: document.getElementById('is_available'),
        active: document.getElementById('is_active')
    };

    var items = [];
    var categories = [];
    var editingCategoryId = 0;
    var currentRequest = null;
    var searchTimer = null;

    function fillCategorySelects() {
        var chosenForm = fields.category.value;
        var chosenFilter = filterCategory.value;

        fields.category.textContent = '';
        fields.category.appendChild(DC.el('option', { value: '', text: 'Choose a category' }));
        filterCategory.textContent = '';
        filterCategory.appendChild(DC.el('option', { value: '', text: 'All categories' }));

        categories.forEach(function (category) {
            var label = category.is_active ? category.name : category.name + ' (inactive)';
            var option = DC.el('option', { value: String(category.id), text: label });
            if (!category.is_active && category.id !== editingCategoryId) {
                option.disabled = true;
            }
            fields.category.appendChild(option);
            filterCategory.appendChild(DC.el('option', { value: String(category.id), text: category.name }));
        });

        fields.category.value = chosenForm;
        filterCategory.value = chosenFilter;
    }

    function resetForm() {
        form.reset();
        fields.id.value = '';
        editingCategoryId = 0;
        fields.available.checked = true;
        fields.active.checked = true;
        formTitle.textContent = 'Add a food item';
        saveButton.textContent = 'Add item';
        cancelButton.hidden = true;
        fillCategorySelects();
    }

    function startEdit(item) {
        DC.hideStatus(statusBox);
        editingCategoryId = item.category_id;
        fillCategorySelects();
        fields.id.value = String(item.id);
        fields.category.value = String(item.category_id);
        fields.name.value = item.name;
        fields.description.value = item.description;
        fields.price.value = item.price;
        fields.available.checked = item.is_available;
        fields.active.checked = item.is_active;
        formTitle.textContent = 'Edit food item';
        saveButton.textContent = 'Save changes';
        cancelButton.hidden = false;
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        fields.name.focus();
    }

    function badge(on, onText, offText, warn) {
        var cls = on ? 'badge-on' : (warn ? 'badge-warn' : 'badge-off');
        return DC.el('span', { className: 'badge ' + cls, text: on ? onText : offText });
    }

    function actionButton(text, handler) {
        var button = DC.el('button', { type: 'button', className: 'button button-secondary button-small', text: text });
        button.addEventListener('click', function () { handler(button); });
        return button;
    }

    function render() {
        tableBody.textContent = '';

        if (items.length === 0) {
            tableBody.appendChild(DC.el('tr', {}, [
                DC.el('td', { colspan: '6', className: 'empty-cell', text: 'No food items found.' })
            ]));
            return;
        }

        items.forEach(function (item) {
            var nameCell = DC.el('td', { 'data-label': 'Item' }, [
                DC.el('strong', { text: item.name }),
                item.description ? DC.el('span', { className: 'cell-sub', text: item.description }) : null
            ]);
            var categoryCell = DC.el('td', { 'data-label': 'Category' }, [
                DC.el('span', { text: item.category_name }),
                item.category_active ? null : DC.el('span', { className: 'cell-sub', text: 'Category is inactive' })
            ]);

            tableBody.appendChild(DC.el('tr', {}, [
                nameCell,
                categoryCell,
                DC.el('td', { 'data-label': 'Price', text: DC.money(item.price) }),
                DC.el('td', { 'data-label': 'Availability' }, [badge(item.is_available, 'Available', 'Unavailable', true)]),
                DC.el('td', { 'data-label': 'Status' }, [badge(item.is_active, 'Active', 'Inactive', false)]),
                DC.el('td', { 'data-label': 'Actions', className: 'cell-actions' }, [
                    actionButton('Edit', function () { startEdit(item); }),
                    actionButton(item.is_available ? 'Mark unavailable' : 'Mark available', function (button) {
                        toggle(button, 'set_available', item.id, item.is_available);
                    }),
                    actionButton(item.is_active ? 'Deactivate' : 'Activate', function (button) {
                        toggle(button, 'set_active', item.id, item.is_active);
                    })
                ])
            ]));
        });
    }

    function load() {
        if (currentRequest) {
            currentRequest.abort();
        }
        currentRequest = DC.request('GET', api, {
            q: filterSearch.value,
            category_id: filterCategory.value
        }, function (result) {
            currentRequest = null;
            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }
            if (!result.success) {
                DC.showStatus(statusBox, 'error', result.message);
                return;
            }
            items = result.data.items || [];
            categories = result.data.categories || [];
            fillCategorySelects();
            render();
        });
    }

    function toggle(button, action, id, currentValue) {
        button.disabled = true;
        DC.request('POST', api, {
            csrf_token: csrf,
            action: action,
            id: id,
            value: currentValue ? '0' : '1'
        }, function (result) {
            button.disabled = false;
            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }
            DC.showStatus(statusBox, result.success ? 'success' : 'error', result.message);
            if (result.success) {
                load();
            }
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        DC.hideStatus(statusBox);
        saveButton.disabled = true;

        DC.request('POST', api, {
            csrf_token: csrf,
            action: 'save',
            id: fields.id.value,
            category_id: fields.category.value,
            name: fields.name.value,
            description: fields.description.value,
            price: fields.price.value,
            is_available: fields.available.checked ? '1' : '0',
            is_active: fields.active.checked ? '1' : '0'
        }, function (result) {
            saveButton.disabled = false;
            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }
            DC.showStatus(statusBox, result.success ? 'success' : 'error', result.message);
            if (result.success) {
                resetForm();
                load();
            }
        });
    });

    cancelButton.addEventListener('click', function () {
        DC.hideStatus(statusBox);
        resetForm();
    });

    filterSearch.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(load, 300);
    });
    filterCategory.addEventListener('change', load);
    document.getElementById('filter-form').addEventListener('submit', function (event) {
        event.preventDefault();
        load();
    });

    resetForm();
    load();
}());
