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

    var form = document.getElementById('category-form');
    var statusBox = document.getElementById('status');
    var tableBody = document.getElementById('category-rows');
    var formTitle = document.getElementById('form-title');
    var saveButton = document.getElementById('save-button');
    var cancelButton = document.getElementById('cancel-button');
    var fields = {
        id: document.getElementById('category-id'),
        name: document.getElementById('name'),
        description: document.getElementById('description'),
        sort: document.getElementById('sort_order'),
        active: document.getElementById('is_active')
    };
    var categories = [];

    function resetForm() {
        form.reset();
        fields.id.value = '';
        fields.sort.value = '0';
        fields.active.checked = true;
        formTitle.textContent = 'Add a category';
        saveButton.textContent = 'Add category';
        cancelButton.hidden = true;
    }

    function startEdit(category) {
        DC.hideStatus(statusBox);
        fields.id.value = String(category.id);
        fields.name.value = category.name;
        fields.description.value = category.description;
        fields.sort.value = String(category.sort_order);
        fields.active.checked = category.is_active;
        formTitle.textContent = 'Edit category';
        saveButton.textContent = 'Save changes';
        cancelButton.hidden = false;
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        fields.name.focus();
    }

    function badge(on, onText, offText) {
        return DC.el('span', { className: 'badge ' + (on ? 'badge-on' : 'badge-off'), text: on ? onText : offText });
    }

    function render() {
        tableBody.textContent = '';

        if (categories.length === 0) {
            var emptyCell = DC.el('td', { colspan: '5', className: 'empty-cell', text: 'No categories yet. Add the first one above.' });
            tableBody.appendChild(DC.el('tr', {}, [emptyCell]));
            return;
        }

        categories.forEach(function (category) {
            var nameCell = DC.el('td', { 'data-label': 'Category' }, [
                DC.el('strong', { text: category.name }),
                category.description ? DC.el('span', { className: 'cell-sub', text: category.description }) : null
            ]);

            var editButton = DC.el('button', { type: 'button', className: 'button button-secondary button-small', text: 'Edit' });
            editButton.addEventListener('click', function () { startEdit(category); });

            var toggleButton = DC.el('button', {
                type: 'button',
                className: 'button button-secondary button-small',
                text: category.is_active ? 'Deactivate' : 'Activate'
            });
            toggleButton.addEventListener('click', function () { setActive(category, toggleButton); });

            tableBody.appendChild(DC.el('tr', {}, [
                nameCell,
                DC.el('td', { 'data-label': 'Order', text: String(category.sort_order) }),
                DC.el('td', { 'data-label': 'Items', text: String(category.item_count) }),
                DC.el('td', { 'data-label': 'Status' }, [badge(category.is_active, 'Active', 'Inactive')]),
                DC.el('td', { 'data-label': 'Actions', className: 'cell-actions' }, [editButton, toggleButton])
            ]));
        });
    }

    function load() {
        DC.request('GET', api, {}, function (result) {
            if (DC.sessionEnded(result, loginUrl)) {
                return;
            }
            if (!result.success) {
                DC.showStatus(statusBox, 'error', result.message);
                return;
            }
            categories = result.data.categories || [];
            render();
        });
    }

    function setActive(category, button) {
        button.disabled = true;
        DC.request('POST', api, {
            csrf_token: csrf,
            action: 'set_active',
            id: category.id,
            value: category.is_active ? '0' : '1'
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
            name: fields.name.value,
            description: fields.description.value,
            sort_order: fields.sort.value,
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

    resetForm();
    load();
}());
