import './stimulus_bootstrap.js';
import './styles/app.css';

const jsonHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const secretVisibilityTimers = new WeakMap();
const debounceTimers = new WeakMap();

function showAlert(message, type = 'success') {
    const container = document.getElementById('ajax-alerts');
    if (!container) return;

    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show shadow`;
    alert.setAttribute('role', 'alert');
    alert.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>`;
    container.appendChild(alert);
    window.setTimeout(() => window.bootstrap?.Alert.getOrCreateInstance(alert).close(), 5000);
}

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value);
    return element.innerHTML;
}

function normalizeSearch(value) {
    return String(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase()
        .trim();
}

function documentNameFromFileName(fileName) {
    const value = String(fileName).trim();
    const lastDot = value.lastIndexOf('.');

    return lastDot > 0 ? value.slice(0, lastDot) : value;
}

function syncDocumentNameFromFile(fileInput) {
    const nameInput = fileInput.closest('form')?.querySelector('[data-document-name]');
    const file = fileInput.files?.[0];
    if (!nameInput || !file) return;

    const currentName = nameInput.value.trim();
    const previousAutomaticName = nameInput.dataset.automaticDocumentName || '';
    if (currentName !== '' && currentName !== previousAutomaticName) return;

    const automaticName = documentNameFromFileName(file.name);
    if (!automaticName) return;

    nameInput.value = automaticName;
    nameInput.dataset.automaticDocumentName = automaticName;
    nameInput.dispatchEvent(new Event('input', {bubbles: true}));
}

function filterCardGrid(search) {
    const grid = document.getElementById(search.dataset.searchTarget);
    if (!grid) return;

    const query = normalizeSearch(search.value);
    const items = [...grid.querySelectorAll('[data-search-item]')];
    let visibleCount = 0;

    items.forEach((item) => {
        const visible = normalizeSearch(item.dataset.searchValue).includes(query);
        item.classList.toggle('d-none', !visible);
        if (visible) visibleCount += 1;
    });

    grid.querySelector('[data-search-empty]')?.classList.toggle('d-none', visibleCount !== 0);

    const toolbar = search.closest('.card');
    const count = toolbar?.querySelector('[data-search-count]');
    const label = toolbar?.querySelector('[data-search-label]');
    const clearButton = toolbar?.querySelector('[data-clear-search]');
    if (count) count.textContent = String(visibleCount);
    if (label) label.textContent = visibleCount > 1 ? 'résultats' : 'résultat';
    clearButton?.classList.toggle('d-none', query === '');
}

async function readJson(response) {
    const payload = await response.json().catch(() => ({
        success: false,
        message: 'La réponse du serveur est invalide.',
        data: {},
    }));
    if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'L’action a échoué.');
    }
    return payload;
}

async function sendJson(url, method, body) {
    return readJson(await fetch(url, {
        method,
        headers: jsonHeaders,
        body: JSON.stringify(body),
    }));
}

function getSecretInput(control) {
    return control.closest('[data-secret-field]')?.querySelector('[data-secret-input]');
}

function hideSecret(input, button) {
    input.type = 'password';
    button.classList.remove('active');
    button.setAttribute('aria-pressed', 'false');
    button.querySelector('i')?.classList.replace('bi-eye-slash', 'bi-eye');
}

function showSecretTemporarily(input, button) {
    window.clearTimeout(secretVisibilityTimers.get(input));
    input.type = 'text';
    button.classList.add('active');
    button.setAttribute('aria-pressed', 'true');
    button.querySelector('i')?.classList.replace('bi-eye', 'bi-eye-slash');

    secretVisibilityTimers.set(input, window.setTimeout(() => {
        hideSecret(input, button);
        secretVisibilityTimers.delete(input);
    }, 2000));
}

async function copyText(value) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    const copied = document.execCommand('copy');
    textarea.remove();
    if (!copied) throw new Error('La copie dans le presse-papiers a échoué.');
}

function randomIndex(max) {
    const random = new Uint32Array(1);
    const limit = 0x100000000 - (0x100000000 % max);

    do {
        window.crypto.getRandomValues(random);
    } while (random[0] >= limit);

    return random[0] % max;
}

function generatePassword(length = 20) {
    const groups = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!@#$%&*+-_=',
    ];
    const allCharacters = groups.join('');
    const password = groups.map((group) => group[randomIndex(group.length)]);

    while (password.length < length) {
        password.push(allCharacters[randomIndex(allCharacters.length)]);
    }

    for (let index = password.length - 1; index > 0; index -= 1) {
        const swapIndex = randomIndex(index + 1);
        [password[index], password[swapIndex]] = [password[swapIndex], password[index]];
    }

    return password.join('');
}

function openNavigationModal(targetSelector, trigger = null) {
    const modal = document.querySelector(targetSelector);
    if (!modal) return false;

    const showModal = () => {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
        if (window.location.hash) {
            window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}`);
        }
    };
    const offcanvas = trigger?.closest('.offcanvas.show');

    if (offcanvas) {
        offcanvas.addEventListener('hidden.bs.offcanvas', showModal, {once: true});
        window.bootstrap.Offcanvas.getOrCreateInstance(offcanvas).hide();
    } else {
        showModal();
    }

    return true;
}

function documentFilterFields() {
    return Array.from(document.querySelectorAll('[data-document-filter]'));
}

function hasDocumentFilters() {
    return documentFilterFields().some((field) => normalizeSearch(field.value) !== '');
}

function appendDocumentFilters(url) {
    documentFilterFields().forEach((field) => {
        const key = field.dataset.documentFilter;
        if (key && field.value) url.searchParams.set(key, field.value);
    });
}

function resetDocumentFilters() {
    documentFilterFields().forEach((field) => {
        field.value = '';
    });
}

function updateDocumentSearchCounters(search, count) {
    const card = search.closest('.card');
    const counter = document.querySelector('[data-document-search-count]');
    const label = document.querySelector('[data-document-search-label]');
    const clearButton = card?.querySelector('[data-document-clear-search]');
    if (counter) counter.textContent = String(count);
    if (label) label.textContent = count > 1 ? 'résultats' : 'résultat';
    clearButton?.classList.toggle('d-none', normalizeSearch(search.value) === '' && !hasDocumentFilters());
}

function updateMaintenanceSearchCounters(search, count) {
    const card = search.closest('.card');
    const counter = card?.querySelector('[data-maintenance-search-count]') || document.querySelector('[data-maintenance-search-count]');
    const label = card?.querySelector('[data-maintenance-search-label]') || document.querySelector('[data-maintenance-search-label]');
    const clearButton = card?.querySelector('[data-maintenance-clear-search]');
    if (counter) counter.textContent = String(count);
    if (label) label.textContent = count > 1 ? 'résultats' : 'résultat';
    clearButton?.classList.toggle('d-none', normalizeSearch(search.value) === '');
}

function updateExpenseSearchCounters(search, count) {
    const card = search.closest('.card');
    const counter = card?.querySelector('[data-expense-search-count]') || document.querySelector('[data-expense-search-count]');
    const label = card?.querySelector('[data-expense-search-label]') || document.querySelector('[data-expense-search-label]');
    const clearButton = card?.querySelector('[data-expense-clear-search]');
    if (counter) counter.textContent = String(count);
    if (label) label.textContent = count > 1 ? 'résultats' : 'résultat';
    clearButton?.classList.toggle('d-none', normalizeSearch(search.value) === '');
}

async function loadDocumentGrid(search, page = 1) {
    const target = document.getElementById(search.dataset.documentTarget);
    if (!target) return;

    target.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body py-5 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement</span></div></div></div>';
    const url = new URL(search.dataset.documentSearchUrl, window.location.origin);
    url.searchParams.set('q', search.value);
    url.searchParams.set('page', String(page));
    appendDocumentFilters(url);
    const payload = await readJson(await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}}));
    target.innerHTML = payload.data.html;
    updateDocumentSearchCounters(search, payload.data.count);
}

async function loadMaintenanceGrid(search) {
    const target = document.getElementById(search.dataset.maintenanceTarget);
    if (!target) return;

    target.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body py-5 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement</span></div></div></div>';
    const url = new URL(search.dataset.maintenanceSearchUrl, window.location.origin);
    url.searchParams.set('q', search.value);

    const statusSelector = search.dataset.maintenanceStatusSelector;
    const statusField = statusSelector ? document.querySelector(statusSelector) : null;
    if (statusField?.value) url.searchParams.set('status', statusField.value);

    const payload = await readJson(await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}}));
    target.innerHTML = payload.data.html;
    updateMaintenanceSearchCounters(search, payload.data.count);
}

function appendExpenseFilters(url) {
    const filters = {
        category: document.getElementById('expenseCategoryFilter')?.value || '',
        status: document.getElementById('expenseStatusFilter')?.value || '',
        paymentMethod: document.getElementById('expensePaymentFilter')?.value || '',
        active: document.getElementById('expenseActiveFilter')?.value || 'active',
        dateFrom: document.getElementById('expenseDateFrom')?.value || '',
        dateTo: document.getElementById('expenseDateTo')?.value || '',
        minAmount: document.getElementById('expenseMinAmount')?.value || '',
        maxAmount: document.getElementById('expenseMaxAmount')?.value || '',
    };

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== '') url.searchParams.set(key, value);
    });
}

async function loadExpenseGrid(search, page = 1) {
    const target = document.getElementById(search.dataset.expenseTarget);
    if (!target) return;

    target.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body py-5 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement</span></div></div></div>';
    const url = new URL(search.dataset.expenseSearchUrl, window.location.origin);
    url.searchParams.set('q', search.value);
    url.searchParams.set('page', String(page));
    appendExpenseFilters(url);

    const payload = await readJson(await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}}));
    target.innerHTML = payload.data.html;
    updateExpenseSearchCounters(search, payload.data.count);
}

function filterMaintenanceContractChoices(form, selectedIntervenantId) {
    const contractSelect = form?.querySelector('[data-maintenance-contract-select]');
    if (!contractSelect) return;

    [...contractSelect.options].forEach((option) => {
        if (!option.value) {
            option.disabled = false;
            option.hidden = false;
            return;
        }

        const matches = selectedIntervenantId !== '' && option.dataset.intervenantId === selectedIntervenantId;
        option.disabled = !matches;
        option.hidden = !matches;
    });

    if (contractSelect.selectedOptions[0]?.disabled) {
        contractSelect.value = '';
    }
}

function syncMaintenanceIntervenant(select) {
    const form = select.closest('form');
    const option = select.selectedOptions[0];
    const name = option?.dataset.name || '';
    const email = option?.dataset.email || '';
    const phone = option?.dataset.phone || '';

    const nameInput = form?.querySelector('[data-maintenance-intervenant-name]');
    const emailInput = form?.querySelector('[data-maintenance-intervenant-email]');
    const phoneInput = form?.querySelector('[data-maintenance-intervenant-phone]');
    if (nameInput) nameInput.value = name;
    if (emailInput) emailInput.value = email;
    if (phoneInput) phoneInput.value = phone;

    filterMaintenanceContractChoices(form, select.value);
}

function splitContactName(value) {
    const parts = String(value || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return {firstName: '', lastName: ''};
    if (parts.length === 1) return {firstName: parts[0], lastName: ''};

    return {
        firstName: parts[0],
        lastName: parts.slice(1).join(' '),
    };
}

function importContactToIntervenant(button) {
    const form = button.closest('form');
    if (!form) return;

    const name = splitContactName(button.dataset.contactName || '');
    const mappings = [
        ['[data-maintenance-company-name]', button.dataset.company || ''],
        ['[data-maintenance-firstname]', name.firstName],
        ['[data-maintenance-lastname]', name.lastName],
        ['[data-maintenance-email]', button.dataset.email || ''],
        ['[data-maintenance-phone]', button.dataset.phone || ''],
    ];

    mappings.forEach(([selector, value]) => {
        const input = form.querySelector(selector);
        if (input && value !== '') {
            input.value = value;
            input.dispatchEvent(new Event('input', {bubbles: true}));
        }
    });

    showAlert('Les informations du contact ont ete importees.');
}

function syncMaintenanceContractType(control) {
    const form = control.closest('form');
    const select = form?.querySelector('[data-maintenance-contract-type-select]');
    const custom = form?.querySelector('[data-maintenance-contract-type-custom]');
    const input = form?.querySelector('[data-maintenance-contract-type-value]');
    if (!select || !custom || !input) return;

    const customMode = select.value === '__other__';
    custom.classList.toggle('d-none', !customMode);
    input.value = customMode ? custom.value.trim() : select.value;
    if (customMode) custom.focus();
}

function validateMaintenanceContractDates(form) {
    const startInput = form.querySelector('[data-maintenance-contract-start]');
    const endInput = form.querySelector('[data-maintenance-contract-end]');
    const errorBox = form.querySelector('[data-maintenance-date-error]');
    if (!startInput?.value || !endInput?.value) {
        errorBox?.classList.add('d-none');
        return true;
    }

    const startDate = new Date(`${startInput.value}T00:00:00`);
    const endDate = new Date(`${endInput.value}T00:00:00`);
    const minimumEndDate = new Date(startDate);
    minimumEndDate.setMonth(minimumEndDate.getMonth() + 1);

    let message = '';
    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
        message = 'Les dates du contrat sont invalides.';
    } else if (startDate > endDate) {
        message = 'La date de début doit être avant la date de fin.';
    } else if (endDate < minimumEndDate) {
        message = 'Un contrat doit durer au moins un mois.';
    }

    if (!message) {
        errorBox?.classList.add('d-none');
        return true;
    }

    if (errorBox) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
        errorBox.scrollIntoView({behavior: 'smooth', block: 'center'});
    }
    showAlert(message, 'danger');
    return false;
}

function initializeMaintenanceSmartForms(root = document) {
    root.querySelectorAll('[data-maintenance-intervenant-select]').forEach((select) => {
        syncMaintenanceIntervenant(select);
    });
    root.querySelectorAll('[data-maintenance-contract-type-select], [data-maintenance-contract-type-custom]').forEach((control) => {
        syncMaintenanceContractType(control);
    });
}

function syncExpenseTotals(form) {
    const amountInput = form?.querySelector('[data-expense-amount-ht]');
    const vatInput = form?.querySelector('[data-expense-vat-rate]');
    const preview = form?.querySelector('[data-expense-ttc-preview]');
    if (!amountInput || !vatInput || !preview) return;

    const amount = Number(String(amountInput.value || '0').replace(',', '.'));
    const vatRate = Number(String(vatInput.value || '0').replace(',', '.'));
    const safeAmount = Number.isFinite(amount) ? Math.max(0, amount) : 0;
    const safeRate = Number.isFinite(vatRate) ? Math.max(0, vatRate) : 0;
    const vat = safeAmount * (safeRate / 100);
    const total = safeAmount + vat;
    preview.textContent = `${total.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} dh`;
}

function syncExpenseCategory(select) {
    const form = select.closest('form');
    const customInput = form?.querySelector('[data-expense-category-custom]');
    if (!customInput) return;

    const customMode = select.value === '__other__';
    customInput.classList.toggle('d-none', !customMode);
    customInput.required = customMode;
    if (!customMode) {
        customInput.value = '';
    } else {
        customInput.focus();
    }
}

function syncExpenseSupplier(select) {
    if (!select.value) return;

    const form = select.closest('form');
    const option = select.selectedOptions[0];
    const nameInput = form?.querySelector('[data-expense-supplier-name]');
    const emailInput = form?.querySelector('[data-expense-supplier-email]');
    const phoneInput = form?.querySelector('[data-expense-supplier-phone]');

    if (nameInput) nameInput.value = option?.dataset.name || '';
    if (emailInput) emailInput.value = option?.dataset.email || '';
    if (phoneInput) phoneInput.value = option?.dataset.phone || '';
}

function initializeExpenseForms(root = document) {
    root.querySelectorAll('[data-expense-form]').forEach((form) => {
        syncExpenseTotals(form);
        form.querySelectorAll('[data-expense-category-select]').forEach((select) => syncExpenseCategory(select));
    });
}

function updateContactMobileButton(group) {
    const button = group.querySelector('[data-contact-add-mobile]');
    if (!button) return;

    const hasHiddenField = [...group.querySelectorAll('[data-contact-extra-mobile]')]
        .some((field) => field.classList.contains('d-none'));
    button.classList.toggle('d-none', !hasHiddenField);
}

function initializeContactPhones(root = document) {
    root.querySelectorAll('[data-contact-mobile-group]').forEach((group) => {
        updateContactMobileButton(group);
    });
}

function syncChoiceTags(field) {
    const selected = field.querySelector('[data-choice-tags-selected]');
    const placeholder = field.querySelector('[data-choice-tags-placeholder]');
    if (!selected || !placeholder) return;

    const checkedOptions = [...field.querySelectorAll('[data-choice-tags-input]:checked')];
    selected.innerHTML = checkedOptions.map((input) => {
        const label = input.closest('[data-choice-tags-option]')?.querySelector('span')?.textContent?.trim() || input.value;

        return `<span class="choice-tags-pill">${escapeHtml(label)}</span>`;
    }).join('');
    placeholder.classList.toggle('d-none', checkedOptions.length > 0);

    field.querySelectorAll('[data-choice-tags-option]').forEach((option) => {
        const input = option.querySelector('[data-choice-tags-input]');
        option.classList.toggle('active', input?.checked ?? false);
    });
}

function initializeChoiceTags(root = document) {
    root.querySelectorAll('[data-choice-tags]').forEach((field) => syncChoiceTags(field));
}

function revealNextContactMobile(button) {
    const group = button.closest('[data-contact-mobile-group]');
    const nextField = group?.querySelector('[data-contact-extra-mobile].d-none');
    if (!group || !nextField) return;

    nextField.classList.remove('d-none');
    nextField.querySelector('input')?.focus();
    updateContactMobileButton(group);
}

function scheduleDebounce(element, callback, delay = 350) {
    window.clearTimeout(debounceTimers.get(element));
    debounceTimers.set(element, window.setTimeout(callback, delay));
}

function renderDocumentUserResults(container, users) {
    if (!users.length) {
        container.innerHTML = '<div class="text-secondary small py-3">Aucun utilisateur actif trouvé.</div>';
        return;
    }

    container.innerHTML = users.map((user) => `
        <div class="list-group-item px-0" data-document-share-result data-user-id="${user.id}">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="min-w-0">
                    <div class="fw-semibold text-break">${escapeHtml(user.displayName)}</div>
                    <div class="small text-secondary text-break">${escapeHtml(user.email)}</div>
                    ${user.alreadyShared ? '<span class="badge text-bg-success mt-1">Déjà partagé</span>' : ''}
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" data-document-share-download checked id="shareDownload${user.id}">
                        <label class="form-check-label small" for="shareDownload${user.id}">Téléchargement</label>
                    </div>
                    <button class="btn btn-primary btn-sm" type="button" data-document-share-user ${user.alreadyShared ? 'disabled' : ''}>
                        <i class="bi bi-send me-1"></i>Partager
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

async function refreshDocumentShareModal(payload) {
    if (payload.data?.html) {
        document.getElementById('remoteModalContent').innerHTML = payload.data.html;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const modalByHash = {
        '#nouveau-mot-de-passe': '#createPasswordModal',
        '#nouvel-utilisateur': '#createUserModal',
        '#nouveau-contact': '#createContactModal',
        '#nouveau-document': '#createDocumentModal',
        '#nouvelle-intervention': '#createInterventionModal',
        '#nouvel-intervenant': '#createIntervenantModal',
        '#nouveau-contrat-maintenance': '#createMaintenanceContractModal',
        '#nouvelle-depense': '#createExpenseModal',
        '#nouvelle-categorie-depense': '#createExpenseCategoryModal',
    };
    const targetSelector = modalByHash[window.location.hash];
    if (targetSelector) openNavigationModal(targetSelector);
    initializeMaintenanceSmartForms();
    initializeExpenseForms();
    initializeContactPhones();
    initializeChoiceTags();
});

document.addEventListener('submit', async (event) => {
    const ajaxForm = event.target.closest('[data-ajax-form]');
    if (ajaxForm) {
        event.preventDefault();
        if (ajaxForm.matches('[data-maintenance-contract-form]') && !validateMaintenanceContractDates(ajaxForm)) {
            return;
        }

        const button = ajaxForm.querySelector('[type="submit"]');
        button?.setAttribute('disabled', 'disabled');
        try {
            const response = await fetch(ajaxForm.action, {
                method: ajaxForm.method || 'POST',
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: new FormData(ajaxForm),
            });
            const payload = await readJson(response);
            showAlert(payload.message);
            if (payload.data?.closeModal) {
                const modalElement = ajaxForm.closest('.modal');
                if (modalElement) window.bootstrap.Modal.getInstance(modalElement)?.hide();
                ajaxForm.reset();
            }
            if (payload.data?.reload) window.location.reload();
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            button?.removeAttribute('disabled');
        }
        return;
    }

    const quickForm = event.target.closest('#quickPasswordForm');
    if (quickForm) {
        event.preventDefault();
        try {
            const payload = await sendJson(quickForm.dataset.url, 'POST', {
                token: quickForm.dataset.token,
                password: document.getElementById('quickPasswordValue').value,
            });
            showAlert(payload.message);
            window.bootstrap.Modal.getInstance(document.getElementById('quickPasswordModal'))?.hide();
            quickForm.reset();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
        return;
    }

    const shareForm = event.target.closest('[data-share-form]');
    if (shareForm) {
        event.preventDefault();
        const shares = [...shareForm.querySelectorAll('[data-share-row]')].map((row) => ({
            userId: Number(row.querySelector('[data-share-view]').dataset.userId),
            canView: row.querySelector('[data-share-view]').checked,
            canEditPassword: row.querySelector('[data-share-edit]')?.checked ?? false,
        }));
        try {
            const payload = await sendJson(shareForm.action, 'POST', {
                token: shareForm.dataset.token,
                shares,
            });
            showAlert(payload.message);
            window.bootstrap.Modal.getInstance(document.getElementById('remoteModal'))?.hide();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    }
});

document.addEventListener('click', async (event) => {
    const choiceTagsOption = event.target.closest('[data-choice-tags-option]');
    if (choiceTagsOption) {
        event.preventDefault();
        const field = choiceTagsOption.closest('[data-choice-tags]');
        const input = choiceTagsOption.querySelector('[data-choice-tags-input]');
        if (!field || !input || input.disabled) return;

        if (field.dataset.choiceTagsSingle === 'true') {
            input.checked = true;
            field.querySelector('[data-choice-tags-menu]')?.classList.add('d-none');
            field.querySelector('[data-choice-tags-toggle]')?.setAttribute('aria-expanded', 'false');
        } else {
            input.checked = !input.checked;
        }

        input.dispatchEvent(new Event('change', {bubbles: true}));
        return;
    }

    const choiceTagsToggle = event.target.closest('[data-choice-tags-toggle]');
    if (choiceTagsToggle) {
        const field = choiceTagsToggle.closest('[data-choice-tags]');
        const menu = field?.querySelector('[data-choice-tags-menu]');
        if (!field || !menu) return;

        document.querySelectorAll('[data-choice-tags-menu]').forEach((otherMenu) => {
            if (otherMenu !== menu) {
                otherMenu.classList.add('d-none');
                otherMenu.closest('[data-choice-tags]')?.querySelector('[data-choice-tags-toggle]')?.setAttribute('aria-expanded', 'false');
            }
        });

        const willOpen = menu.classList.contains('d-none');
        menu.classList.toggle('d-none', !willOpen);
        choiceTagsToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        return;
    }

    if (!event.target.closest('[data-choice-tags]')) {
        document.querySelectorAll('[data-choice-tags-menu]').forEach((menu) => {
            menu.classList.add('d-none');
            menu.closest('[data-choice-tags]')?.querySelector('[data-choice-tags-toggle]')?.setAttribute('aria-expanded', 'false');
        });
    }

    const navigationModalLink = event.target.closest('[data-navigation-modal]');
    if (navigationModalLink && openNavigationModal(navigationModalLink.dataset.navigationModal, navigationModalLink)) {
        event.preventDefault();
        return;
    }

    const addContactMobileButton = event.target.closest('[data-contact-add-mobile]');
    if (addContactMobileButton) {
        revealNextContactMobile(addContactMobileButton);
        return;
    }

    const documentPageButton = event.target.closest('[data-document-page]');
    if (documentPageButton && !documentPageButton.disabled) {
        const search = document.querySelector('[data-document-search]');
        if (search) {
            await loadDocumentGrid(search, Number(documentPageButton.dataset.documentPage || 1));
        }
        return;
    }

    const expensePageButton = event.target.closest('[data-expense-page]');
    if (expensePageButton && !expensePageButton.disabled) {
        const search = document.querySelector('[data-expense-search]');
        if (search) {
            await loadExpenseGrid(search, Number(expensePageButton.dataset.expensePage || 1));
        }
        return;
    }

    const documentClearSearch = event.target.closest('[data-document-clear-search]');
    if (documentClearSearch) {
        const search = documentClearSearch.closest('.card')?.querySelector('[data-document-search]');
        if (search) {
            search.value = '';
            resetDocumentFilters();
            await loadDocumentGrid(search, 1);
            search.focus();
        }
        return;
    }

    const expenseClearSearch = event.target.closest('[data-expense-clear-search]');
    if (expenseClearSearch) {
        const search = expenseClearSearch.closest('.card')?.querySelector('[data-expense-search]');
        if (search) {
            search.value = '';
            await loadExpenseGrid(search, 1);
            search.focus();
        }
        return;
    }

    const maintenanceClearSearch = event.target.closest('[data-maintenance-clear-search]');
    if (maintenanceClearSearch) {
        const search = maintenanceClearSearch.closest('.card')?.querySelector('[data-maintenance-search]');
        if (search) {
            search.value = '';
            await loadMaintenanceGrid(search);
            search.focus();
        }
        return;
    }

    const clearSearchButton = event.target.closest('[data-clear-search]');
    if (clearSearchButton) {
        const search = clearSearchButton.closest('.card')?.querySelector('[data-list-search]');
        if (search) {
            search.value = '';
            filterCardGrid(search);
            search.focus();
        }
        return;
    }

    const documentShareButton = event.target.closest('[data-document-share-user]');
    if (documentShareButton) {
        const panel = documentShareButton.closest('[data-document-share-panel]');
        const row = documentShareButton.closest('[data-document-share-result]');
        if (!panel || !row) return;

        documentShareButton.disabled = true;
        try {
            const payload = await sendJson(panel.dataset.documentShareUrl, 'POST', {
                token: panel.dataset.documentShareToken,
                userId: Number(row.dataset.userId),
                canDownload: row.querySelector('[data-document-share-download]')?.checked ?? true,
            });
            showAlert(payload.message);
            await refreshDocumentShareModal(payload);
        } catch (error) {
            showAlert(error.message, 'danger');
            documentShareButton.disabled = false;
        }
        return;
    }

    const documentShareRemove = event.target.closest('[data-document-share-remove]');
    if (documentShareRemove) {
        documentShareRemove.disabled = true;
        try {
            const payload = await sendJson(documentShareRemove.dataset.url, 'DELETE', {
                token: documentShareRemove.dataset.token,
            });
            showAlert(payload.message);
            await refreshDocumentShareModal(payload);
        } catch (error) {
            showAlert(error.message, 'danger');
            documentShareRemove.disabled = false;
        }
        return;
    }

    const copyButton = event.target.closest('[data-copy-password]');
    if (copyButton) {
        copyButton.disabled = true;
        try {
            const payload = await sendJson(copyButton.dataset.url, 'POST', {token: copyButton.dataset.token});
            await copyText(payload.data.password);
            showAlert('Mot de passe copié dans le presse-papiers.');
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            copyButton.disabled = false;
        }
        return;
    }

    const secretToggle = event.target.closest('[data-secret-toggle]');
    if (secretToggle) {
        const input = getSecretInput(secretToggle);
        if (input?.value) showSecretTemporarily(input, secretToggle);
        return;
    }

    const secretCopy = event.target.closest('[data-secret-copy]');
    if (secretCopy) {
        const input = getSecretInput(secretCopy);
        if (!input?.value) {
            showAlert('Saisissez ou générez d’abord un mot de passe.', 'warning');
            return;
        }

        try {
            await copyText(input.value);
            showAlert('Mot de passe copié dans le presse-papiers.');
        } catch (error) {
            showAlert(error.message, 'danger');
        }
        return;
    }

    const secretGenerator = event.target.closest('[data-secret-generate]');
    if (secretGenerator) {
        const input = getSecretInput(secretGenerator);
        if (input) {
            input.value = generatePassword();
            input.dispatchEvent(new Event('input', {bubbles: true}));
            input.focus();
            const toggle = secretGenerator.closest('[data-secret-field]').querySelector('[data-secret-toggle]');
            showSecretTemporarily(input, toggle);
            showAlert('Un mot de passe sécurisé a été généré.');
        }
        return;
    }

    const maintenanceStatusButton = event.target.closest('[data-maintenance-status-url]');
    if (maintenanceStatusButton) {
        maintenanceStatusButton.disabled = true;
        try {
            const payload = await sendJson(maintenanceStatusButton.dataset.maintenanceStatusUrl, 'POST', {
                token: maintenanceStatusButton.dataset.token,
                status: maintenanceStatusButton.dataset.maintenanceStatus,
            });
            showAlert(payload.message);
            if (payload.data?.reload) window.location.reload();
        } catch (error) {
            showAlert(error.message, 'danger');
            maintenanceStatusButton.disabled = false;
        }
        return;
    }

    const maintenancePickIntervenant = event.target.closest('[data-maintenance-pick-intervenant]');
    if (maintenancePickIntervenant) {
        const select = document.querySelector(maintenancePickIntervenant.dataset.targetSelect);
        if (select) {
            select.value = maintenancePickIntervenant.dataset.intervenantId;
            select.dispatchEvent(new Event('change', {bubbles: true}));
        }
        return;
    }

    const maintenanceImportContact = event.target.closest('[data-maintenance-import-contact]');
    if (maintenanceImportContact) {
        importContactToIntervenant(maintenanceImportContact);
        return;
    }

    const expensePickSupplier = event.target.closest('[data-expense-pick-supplier]');
    if (expensePickSupplier) {
        const select = document.querySelector(expensePickSupplier.dataset.targetSelect);
        if (select) {
            select.value = expensePickSupplier.dataset.intervenantId;
            select.dispatchEvent(new Event('change', {bubbles: true}));
        }
        return;
    }

    const quickButton = event.target.closest('[data-quick-password]');
    if (quickButton) {
        const form = document.getElementById('quickPasswordForm');
        const input = document.getElementById('quickPasswordValue');
        form.dataset.url = quickButton.dataset.url;
        form.dataset.token = quickButton.dataset.token;
        document.getElementById('quickPasswordName').textContent = quickButton.dataset.name;
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('quickPasswordModal')).show();
        input.value = '';
        input.disabled = true;
        try {
            const payload = await sendJson(quickButton.dataset.revealUrl, 'POST', {
                token: quickButton.dataset.revealToken,
            });
            input.value = payload.data.password;
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            input.disabled = false;
            input.focus();
        }
        return;
    }

    const remoteButton = event.target.closest('[data-remote-modal]');
    if (remoteButton) {
        event.preventDefault();
        const modal = document.getElementById('remoteModal');
        const dialog = modal.querySelector('.modal-dialog');
        const content = document.getElementById('remoteModalContent');
        dialog.className = 'modal-dialog modal-fullscreen modal-dialog-scrollable';
        content.innerHTML = '<div class="modal-body py-5 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement</span></div></div>';
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
        try {
            const response = await fetch(remoteButton.dataset.remoteModal, {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
            if (!response.ok) throw new Error('Impossible de charger le formulaire.');
            content.innerHTML = await response.text();
            initializeMaintenanceSmartForms(content);
            initializeExpenseForms(content);
            initializeContactPhones(content);
            initializeChoiceTags(content);
            if (remoteButton.dataset.secretUrl) {
                const input = content.querySelector('[data-secret-input]');
                if (input) {
                    input.value = '';
                    input.disabled = true;
                    const payload = await sendJson(remoteButton.dataset.secretUrl, 'POST', {
                        token: remoteButton.dataset.secretToken,
                    });
                    input.value = payload.data.password;
                    input.disabled = false;
                    input.focus();
                }
            }
        } catch (error) {
            content.innerHTML = `<div class="modal-body"><div class="alert alert-danger mb-0">${escapeHtml(error.message)}</div></div>`;
        }
        return;
    }

    const confirmButton = event.target.closest('[data-confirm-url]');
    if (confirmButton) {
        if (!window.confirm(confirmButton.dataset.confirmMessage || 'Confirmer cette action ?')) return;
        confirmButton.disabled = true;
        try {
            const payload = await sendJson(
                confirmButton.dataset.confirmUrl,
                confirmButton.dataset.confirmMethod || 'POST',
                {token: confirmButton.dataset.token},
            );
            showAlert(payload.message);
            if (payload.data?.reload) window.location.reload();
        } catch (error) {
            showAlert(error.message, 'danger');
            confirmButton.disabled = false;
        }
    }
});

document.addEventListener('change', (event) => {
    const choiceTagsInput = event.target.closest('[data-choice-tags-input]');
    if (choiceTagsInput) {
        const field = choiceTagsInput.closest('[data-choice-tags]');
        if (field) syncChoiceTags(field);
        return;
    }

    const viewCheckbox = event.target.closest('[data-share-view]');
    if (!viewCheckbox) return;
    const editCheckbox = viewCheckbox.closest('[data-share-row]').querySelector('[data-share-edit]');
    if (!editCheckbox) return;
    editCheckbox.disabled = !viewCheckbox.checked;
    if (!viewCheckbox.checked) editCheckbox.checked = false;
});

document.addEventListener('input', (event) => {
    const expenseSearch = event.target.closest('[data-expense-search]');
    if (expenseSearch) {
        scheduleDebounce(expenseSearch, async () => {
            try {
                await loadExpenseGrid(expenseSearch, 1);
            } catch (error) {
                showAlert(error.message, 'danger');
            }
        });
        return;
    }

    const expenseAmountInput = event.target.closest('[data-expense-amount-ht], [data-expense-vat-rate]');
    if (expenseAmountInput) {
        syncExpenseTotals(expenseAmountInput.closest('[data-expense-form]'));
        return;
    }

    const documentSearch = event.target.closest('[data-document-search]');
    if (documentSearch) {
        scheduleDebounce(documentSearch, async () => {
            try {
                await loadDocumentGrid(documentSearch, 1);
            } catch (error) {
                showAlert(error.message, 'danger');
            }
        });
        return;
    }

    const maintenanceSearch = event.target.closest('[data-maintenance-search]');
    if (maintenanceSearch) {
        scheduleDebounce(maintenanceSearch, async () => {
            try {
                await loadMaintenanceGrid(maintenanceSearch);
            } catch (error) {
                showAlert(error.message, 'danger');
            }
        });
        return;
    }

    const documentUserSearch = event.target.closest('[data-document-user-search]');
    if (documentUserSearch) {
        const panel = documentUserSearch.closest('[data-document-share-panel]');
        const results = panel?.querySelector('[data-document-user-results]');
        const loader = panel?.querySelector('[data-document-share-loading]');
        if (!panel || !results || !loader) return;

        scheduleDebounce(documentUserSearch, async () => {
            const query = documentUserSearch.value.trim();
            if (query.length < 2) {
                results.innerHTML = '<div class="text-secondary small py-3">Tapez au moins 2 caractères.</div>';
                return;
            }

            loader.classList.remove('d-none');
            loader.classList.add('d-flex');
            try {
                const url = new URL(panel.dataset.documentShareSearchUrl, window.location.origin);
                url.searchParams.set('q', query);
                const payload = await readJson(await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}}));
                renderDocumentUserResults(results, payload.data.users || []);
            } catch (error) {
                results.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message)}</div>`;
            } finally {
                loader.classList.add('d-none');
                loader.classList.remove('d-flex');
            }
        });
        return;
    }

    const listSearch = event.target.closest('[data-list-search]');
    if (listSearch) {
        filterCardGrid(listSearch);
        return;
    }

    const search = event.target.closest('[data-user-search]');
    if (!search) return;
    const query = search.value.toLocaleLowerCase().trim();
    search.closest('form').querySelectorAll('[data-share-row]').forEach((row) => {
        row.classList.toggle('d-none', !row.dataset.searchValue.includes(query));
    });
});

document.addEventListener('change', (event) => {
    const documentFilter = event.target.closest('[data-document-filter]');
    if (documentFilter) {
        const search = document.querySelector('[data-document-search]');
        if (search) {
            loadDocumentGrid(search, 1).catch((error) => showAlert(error.message, 'danger'));
        }
        return;
    }

    const expenseFilter = event.target.closest('[data-expense-filter]');
    if (expenseFilter) {
        const search = document.querySelector('[data-expense-search]');
        if (search) {
            loadExpenseGrid(search, 1).catch((error) => showAlert(error.message, 'danger'));
        }
        return;
    }

    const expenseCategorySelect = event.target.closest('[data-expense-category-select]');
    if (expenseCategorySelect) {
        syncExpenseCategory(expenseCategorySelect);
        return;
    }

    const expenseSupplierSelect = event.target.closest('[data-expense-supplier-select]');
    if (expenseSupplierSelect) {
        syncExpenseSupplier(expenseSupplierSelect);
        return;
    }

    const documentFile = event.target.closest('[data-document-file]');
    if (documentFile) syncDocumentNameFromFile(documentFile);

    const maintenanceFilter = event.target.closest('[data-maintenance-filter]');
    if (maintenanceFilter) {
        const search = document.querySelector(`[data-maintenance-status-selector="#${maintenanceFilter.id}"]`);
        if (search) {
            loadMaintenanceGrid(search).catch((error) => showAlert(error.message, 'danger'));
        }
        return;
    }

    const maintenanceIntervenantSelect = event.target.closest('[data-maintenance-intervenant-select]');
    if (maintenanceIntervenantSelect) {
        syncMaintenanceIntervenant(maintenanceIntervenantSelect);
        return;
    }

    const contractTypeSelect = event.target.closest('[data-maintenance-contract-type-select]');
    if (contractTypeSelect) {
        syncMaintenanceContractType(contractTypeSelect);
        return;
    }

    const contractDateInput = event.target.closest('[data-maintenance-contract-start], [data-maintenance-contract-end]');
    if (contractDateInput) {
        validateMaintenanceContractDates(contractDateInput.closest('form'));
    }
});

document.addEventListener('input', (event) => {
    const customContractType = event.target.closest('[data-maintenance-contract-type-custom]');
    if (customContractType) {
        syncMaintenanceContractType(customContractType);
    }
});
