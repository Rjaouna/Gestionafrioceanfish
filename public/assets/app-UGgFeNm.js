const jsonHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

const secretVisibilityTimers = new WeakMap();
const debounceTimers = new WeakMap();

function installBootstrapFallback() {
    if (window.bootstrap?.Modal) return;

    const modalInstances = new WeakMap();
    const offcanvasInstances = new WeakMap();

    function dispatch(element, name) {
        element.dispatchEvent(new CustomEvent(name, {bubbles: true}));
    }

    function backdropFor(element) {
        let backdrop = element.__bootstrapFallbackBackdrop;
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.dataset.bootstrapFallback = 'true';
            backdrop.addEventListener('click', () => ModalFallback.getOrCreateInstance(element).hide());
            element.__bootstrapFallbackBackdrop = backdrop;
        }

        return backdrop;
    }

    class ModalFallback {
        constructor(element) {
            this.element = element;
        }

        show() {
            dispatch(this.element, 'show.bs.modal');
            this.element.style.display = 'block';
            this.element.removeAttribute('aria-hidden');
            this.element.setAttribute('aria-modal', 'true');
            this.element.setAttribute('role', 'dialog');
            this.element.classList.add('show');
            document.body.classList.add('modal-open');
            document.body.appendChild(backdropFor(this.element));
            dispatch(this.element, 'shown.bs.modal');
        }

        hide() {
            dispatch(this.element, 'hide.bs.modal');
            this.element.classList.remove('show');
            this.element.style.display = 'none';
            this.element.setAttribute('aria-hidden', 'true');
            this.element.removeAttribute('aria-modal');
            document.body.classList.remove('modal-open');
            this.element.__bootstrapFallbackBackdrop?.remove();
            dispatch(this.element, 'hidden.bs.modal');
        }

        static getOrCreateInstance(element) {
            if (!modalInstances.has(element)) {
                modalInstances.set(element, new ModalFallback(element));
            }

            return modalInstances.get(element);
        }

        static getInstance(element) {
            return modalInstances.get(element) || null;
        }
    }

    class OffcanvasFallback {
        constructor(element) {
            this.element = element;
        }

        show() {
            this.element.classList.add('show');
        }

        hide() {
            this.element.classList.remove('show');
            dispatch(this.element, 'hidden.bs.offcanvas');
        }

        static getOrCreateInstance(element) {
            if (!offcanvasInstances.has(element)) {
                offcanvasInstances.set(element, new OffcanvasFallback(element));
            }

            return offcanvasInstances.get(element);
        }
    }

    class AlertFallback {
        constructor(element) {
            this.element = element;
        }

        close() {
            this.element.remove();
        }

        static getOrCreateInstance(element) {
            return new AlertFallback(element);
        }
    }

    window.bootstrap = {...(window.bootstrap || {}), Modal: ModalFallback, Offcanvas: OffcanvasFallback, Alert: AlertFallback};

    document.addEventListener('click', (event) => {
        const modalTrigger = event.target.closest('[data-bs-toggle="modal"][data-bs-target]');
        if (modalTrigger) {
            const modal = document.querySelector(modalTrigger.dataset.bsTarget);
            if (modal) {
                event.preventDefault();
                ModalFallback.getOrCreateInstance(modal).show();
            }

            return;
        }

        const offcanvasTrigger = event.target.closest('[data-bs-toggle="offcanvas"][data-bs-target]');
        if (offcanvasTrigger) {
            const offcanvas = document.querySelector(offcanvasTrigger.dataset.bsTarget);
            if (offcanvas) {
                event.preventDefault();
                OffcanvasFallback.getOrCreateInstance(offcanvas).show();
            }

            return;
        }

        const dismiss = event.target.closest('[data-bs-dismiss]');
        if (!dismiss) return;

        const target = dismiss.dataset.bsDismiss;
        if (target === 'modal') {
            const modal = dismiss.closest('.modal');
            if (modal) ModalFallback.getOrCreateInstance(modal).hide();
        } else if (target === 'offcanvas') {
            const offcanvas = dismiss.closest('.offcanvas');
            if (offcanvas) OffcanvasFallback.getOrCreateInstance(offcanvas).hide();
        } else if (target === 'alert') {
            dismiss.closest('.alert')?.remove();
        }
    });
}

installBootstrapFallback();

async function startStimulus() {
    try {
        await import('./stimulus_bootstrap.js');
    } catch (error) {
        console.error('Stimulus bootstrap failed to load.', error);
    }
}

startStimulus();

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

function confirmAction(message) {
    let modal = document.getElementById('appConfirmModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'appConfirmModal';
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5">Confirmation</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0" data-confirm-modal-message></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-danger" data-confirm-modal-accept>Confirmer</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    modal.querySelector('[data-confirm-modal-message]').textContent = message || 'Confirmer cette action ?';

    return new Promise((resolve) => {
        const instance = window.bootstrap.Modal.getOrCreateInstance(modal);
        const acceptButton = modal.querySelector('[data-confirm-modal-accept]');
        let resolved = false;

        const cleanup = () => {
            acceptButton.removeEventListener('click', accept);
            modal.removeEventListener('hidden.bs.modal', cancel);
        };
        const accept = () => {
            resolved = true;
            cleanup();
            instance.hide();
            resolve(true);
        };
        const cancel = () => {
            cleanup();
            if (!resolved) resolve(false);
        };

        acceptButton.addEventListener('click', accept, {once: true});
        modal.addEventListener('hidden.bs.modal', cancel, {once: true});
        instance.show();
    });
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

function normalizedWords(value) {
    return normalizeSearch(value).split(/[^a-z0-9]+/).filter(Boolean);
}

function sidebarSearchMatches(text, query) {
    if (!query) return true;
    const normalized = normalizeSearch(text);
    if (normalized.includes(query)) return true;

    const words = normalizedWords(text);
    const queryWords = normalizedWords(query);
    if (queryWords.length > 1) {
        return queryWords.every((queryWord) => normalized.includes(queryWord) || words.some((word) => word.startsWith(queryWord)));
    }

    return words.some((word) => word.includes(query));
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

function filterSidebarNavigation(navigation, value) {
    if (!navigation) return;

    const query = normalizeSearch(value);
    const hasQuery = query !== '';
    let visibleCount = 0;

    navigation.querySelectorAll('.sidebar-kicker').forEach((kicker) => {
        kicker.classList.toggle('d-none', hasQuery);
    });

    navigation.querySelectorAll('.sidebar-home-link').forEach((link) => {
        const visible = !hasQuery || sidebarSearchMatches(link.textContent, query);
        link.classList.toggle('d-none', !visible);
        if (visible) visibleCount += 1;
    });

    navigation.querySelectorAll('.sidebar-open-section').forEach((section) => {
        const heading = section.querySelector('.sidebar-open-heading');
        const headingMatches = hasQuery && sidebarSearchMatches(heading?.textContent || '', query);
        let sectionVisibleCount = 0;
        let parentMatches = headingMatches;

        section.querySelectorAll('.sidebar-sub-link').forEach((link) => {
            const isChildLink = link.classList.contains('ps-4');
            const linkMatches = hasQuery && sidebarSearchMatches(link.textContent, query);
            if (!isChildLink) {
                parentMatches = headingMatches || linkMatches;
            }

            const visible = !hasQuery || headingMatches || linkMatches || (isChildLink && parentMatches);
            link.classList.toggle('d-none', !visible);
            if (visible) sectionVisibleCount += 1;
        });

        const visible = !hasQuery || headingMatches || sectionVisibleCount > 0;
        section.classList.toggle('d-none', !visible);
        if (visible) visibleCount += Math.max(sectionVisibleCount, 1);
    });

    navigation.querySelectorAll('.sidebar-configuration').forEach((configuration) => {
        const visible = !hasQuery || [...configuration.querySelectorAll('.sidebar-open-section')].some((section) => !section.classList.contains('d-none'));
        configuration.classList.toggle('d-none', !visible);
    });

    navigation.querySelectorAll('.sidebar-logout-button').forEach((link) => {
        const visible = !hasQuery || sidebarSearchMatches(link.textContent, query);
        link.classList.toggle('d-none', !visible);
        if (visible) visibleCount += 1;
    });

    const bottomZone = navigation.querySelector('.sidebar-bottom-zone');
    if (bottomZone) {
        const visible = !hasQuery || [...bottomZone.children].some((child) => !child.classList.contains('d-none'));
        bottomZone.classList.toggle('d-none', !visible);
    }

    navigation.querySelector('[data-sidebar-search-empty]')?.classList.toggle('d-none', !hasQuery || visibleCount > 0);
    navigation.querySelector('[data-sidebar-search-clear]')?.classList.toggle('d-none', !hasQuery);
}

function initializeSidebarSearches(root = document) {
    root.querySelectorAll('[data-sidebar-search-input]').forEach((input) => {
        filterSidebarNavigation(input.closest('.sidebar-navigation'), input.value);
    });
}

function handleSidebarSearchEvent(target) {
    const sidebarSearchInput = target.closest?.('[data-sidebar-search-input]');
    if (!sidebarSearchInput) return false;

    filterSidebarNavigation(sidebarSearchInput.closest('.sidebar-navigation'), sidebarSearchInput.value);

    return true;
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

    showAlert('Les informations du contact ont été importées.');
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

function cleanInterimPhone(value) {
    return String(value || '').replace(/[\s().-]+/g, '').trim();
}

function syncInterimAge(form) {
    const input = form.querySelector('[data-interim-birth-date]');
    const output = form.querySelector('[data-interim-age-output]');
    if (!input || !output) return;

    if (!input.value) {
        output.textContent = '';
        return;
    }

    const birthDate = new Date(`${input.value}T00:00:00`);
    if (Number.isNaN(birthDate.getTime())) {
        output.textContent = '';
        return;
    }

    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDelta = today.getMonth() - birthDate.getMonth();
    if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1;
    }

    output.textContent = age >= 0 ? `${age} an${age > 1 ? 's' : ''}` : 'Date dans le futur';
}

function syncInterimFamily(form) {
    const selected = form.querySelector('input[name$="[familySituation]"]:checked')?.value
        || form.querySelector('select[name$="[familySituation]"]')?.value
        || '';
    const row = form.querySelector('[data-interim-children-row]');
    const input = form.querySelector('[data-interim-children-count]');
    if (!row || !input) return;

    const hideChildren = selected === 'celibataire';
    row.classList.toggle('d-none', hideChildren);
    if (hideChildren || input.value === '') {
        input.value = '0';
    }
    if (Number(input.value) < 0) input.value = '0';
    if (Number(input.value) > 20) input.value = '20';
}

function syncInterimObservationsCounter(form) {
    const textarea = form.querySelector('[data-interim-observations]');
    const counter = form.querySelector('[data-interim-observations-counter]');
    if (!textarea || !counter) return;

    const max = Number(textarea.getAttribute('maxlength') || 1000);
    counter.textContent = `${textarea.value.length} / ${max}`;
}

function previewInterimPhoto(input) {
    const form = input.closest('[data-interim-worker-form]');
    const preview = form?.querySelector('[data-interim-photo-preview]');
    const placeholder = form?.querySelector('[data-interim-photo-placeholder]');
    const file = input.files?.[0];
    if (!preview || !placeholder || !file) return;

    preview.src = URL.createObjectURL(file);
    preview.classList.remove('d-none');
    preview.classList.add('d-block');
    placeholder.classList.add('d-none');
}

function initializeInterimWorkerForms(root = document) {
    root.querySelectorAll('[data-interim-worker-form]').forEach((form) => {
        syncInterimAge(form);
        syncInterimFamily(form);
        syncInterimObservationsCounter(form);

        if (form.dataset.interimWorkerInitialized === 'true') return;
        form.dataset.interimWorkerInitialized = 'true';

        form.querySelector('[data-interim-photo-input]')?.addEventListener('change', (event) => {
            previewInterimPhoto(event.currentTarget);
        });
        form.querySelector('[data-interim-birth-date]')?.addEventListener('input', () => syncInterimAge(form));
        form.querySelectorAll('input[name$="[familySituation]"], select[name$="[familySituation]"]').forEach((input) => {
            input.addEventListener('change', () => syncInterimFamily(form));
        });
        form.querySelector('[data-interim-children-count]')?.addEventListener('input', () => syncInterimFamily(form));
        form.querySelector('[data-interim-observations]')?.addEventListener('input', () => syncInterimObservationsCounter(form));
        form.querySelector('[data-interim-cin]')?.addEventListener('input', (event) => {
            event.currentTarget.value = String(event.currentTarget.value || '').toLocaleUpperCase().replace(/[^A-Z0-9]/g, '');
        });
        form.querySelector('[data-interim-phone]')?.addEventListener('input', (event) => {
            event.currentTarget.value = cleanInterimPhone(event.currentTarget.value);
        });
        form.addEventListener('submit', () => syncInterimFamily(form));
    });
}

function coutNumber(value) {
    const normalized = String(value ?? '0').replace(',', '.').trim();
    const number = Number.parseFloat(normalized);

    return Number.isFinite(number) ? Math.max(0, number) : 0;
}

function coutFormat(value) {
    return Number(value || 0).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function coutField(form, name) {
    return form.querySelector(`[name$="[${name}]"]`);
}

function coutValue(form, name) {
    return coutNumber(coutField(form, name)?.value);
}

function syncCoutChargeLine(row) {
    const select = row.querySelector('[data-cout-charge-select]');
    const selectedOption = select?.selectedOptions?.[0];
    const nameInput = row.querySelector('[data-cout-charge-name]');
    const categoryInput = row.querySelector('[data-cout-charge-category]');
    const unitInput = row.querySelector('[data-cout-charge-unit]');
    const unitCostInput = row.querySelector('[data-cout-charge-unit-cost]');
    const quantityInput = row.querySelector('[data-cout-charge-quantity]');
    const quantityLabel = row.querySelector('[data-cout-charge-quantity-label]');
    const meta = row.querySelector('[data-cout-charge-meta]');
    const unitShort = row.querySelector('[data-cout-charge-unit-short]');
    const totalOutput = row.querySelector('[data-cout-charge-total]');
    const formula = row.querySelector('[data-cout-charge-formula]');

    if (selectedOption?.value) {
        if (nameInput) nameInput.value = selectedOption.dataset.name || '';
        if (categoryInput) categoryInput.value = selectedOption.dataset.category || 'autre';
        if (unitInput) unitInput.value = selectedOption.dataset.unit || 'montant_direct';
        if (unitCostInput && (unitCostInput.value === '' || row.dataset.coutChargeSelected !== selectedOption.value)) {
            unitCostInput.value = selectedOption.dataset.unitCost || '0';
        }
        if (quantityInput && coutNumber(quantityInput.value) <= 0) quantityInput.value = '1';
        if (meta) meta.textContent = `${selectedOption.dataset.categoryLabel || 'Autre'} - ${selectedOption.dataset.unitLabel || 'Montant direct'}`;
        if (unitShort) unitShort.textContent = selectedOption.dataset.unitShort || 'direct';
        if (quantityLabel) quantityLabel.textContent = selectedOption.dataset.quantityLabel || 'Quantite';
        row.dataset.coutChargeSelected = selectedOption.value;
    } else {
        if (nameInput) nameInput.value = '';
        if (categoryInput) categoryInput.value = 'autre';
        if (unitInput) unitInput.value = 'montant_direct';
        if (meta) meta.textContent = 'Autre - Montant direct';
        if (unitShort) unitShort.textContent = 'direct';
        if (quantityLabel) quantityLabel.textContent = 'Quantite';
        row.dataset.coutChargeSelected = '';
    }

    const unit = unitInput?.value || 'montant_direct';
    const unitCost = coutNumber(unitCostInput?.value);
    const quantity = coutNumber(quantityInput?.value);
    const total = unit === 'mois' ? (unitCost / 30) * quantity : unitCost * quantity;
    if (totalOutput) totalOutput.textContent = coutFormat(total);
    if (formula) {
        formula.textContent = unit === 'mois'
            ? `${coutFormat(unitCost / 30)} dh / jour (${coutFormat(unitCost)} dh / mois)`
            : 'Calcul direct';
    }

    return total;
}

function configuredCoutChargesTotal(form) {
    return [...form.querySelectorAll('[data-cout-charge-line]')]
        .reduce((total, row) => total + syncCoutChargeLine(row), 0);
}

function refreshCoutChargeEmptyState(form) {
    const empty = form.querySelector('[data-cout-charge-empty]');
    if (!empty) return;

    empty.classList.toggle('d-none', form.querySelectorAll('[data-cout-charge-line]').length > 0);
}

function addCoutChargeLine(form) {
    const template = form.querySelector('[data-cout-charge-template]');
    const container = form.querySelector('[data-cout-charge-lines]');
    if (!template || !container) return;

    const index = `${Date.now()}${container.querySelectorAll('[data-cout-charge-line]').length}`;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, index).trim();
    const row = wrapper.firstElementChild;
    if (!row) return;

    container.appendChild(row);
    refreshCoutChargeEmptyState(form);
    syncCoutRevientForm(form);
    row.querySelector('[data-cout-charge-select]')?.focus();
}

function syncCoutMode(form) {
    const mode = coutField(form, 'modeCalculMainOeuvre')?.value || 'montant_direct';
    form.querySelectorAll('[data-cout-mode-section]').forEach((section) => {
        section.classList.toggle('d-none', section.dataset.coutModeSection !== mode);
    });
    form.querySelectorAll('[data-cout-mode-result]').forEach((section) => {
        section.classList.toggle('d-none', section.dataset.coutModeResult !== mode);
    });
}

function calculateCoutRevient(form) {
    const poidsBrut = coutValue(form, 'poidsBrutRecu');
    const poidsProduction = coutValue(form, 'poidsMisEnProduction');
    const poidsFini = coutValue(form, 'poidsProduitFini');
    const coutAchatPoisson = poidsBrut * coutValue(form, 'prixAchatKg');
    const coutFraisAchatTotal = coutValue(form, 'fraisTransportAchat') + coutValue(form, 'autresFraisAchat');
    const coutMatierePremiere = coutAchatPoisson + coutFraisAchatTotal;

    const mode = coutField(form, 'modeCalculMainOeuvre')?.value || 'montant_direct';
    const coutMainOeuvreParPersonne = coutValue(form, 'nombreHeures') * coutValue(form, 'coutHoraireMoyen');
    let coutMainOeuvre = coutValue(form, 'coutMainOeuvreDirect');
    if (mode === 'heure') {
        coutMainOeuvre = coutValue(form, 'nombreOperatrices') * coutMainOeuvreParPersonne;
    } else if (mode === 'kg') {
        coutMainOeuvre = coutValue(form, 'prixTacheKg') * coutValue(form, 'kgTraitesMainOeuvre');
    }

    const coutCartonsTotal = coutValue(form, 'nombreCartons') * coutValue(form, 'prixCarton');
    const coutSachetsTotal = coutValue(form, 'nombreSachets') * coutValue(form, 'prixSachet');
    const coutEmballageTotal = coutCartonsTotal
        + coutSachetsTotal
        + coutValue(form, 'coutEtiquettes')
        + coutValue(form, 'coutFilmPlastique')
        + coutValue(form, 'autresCoutEmballage');

    const coutChargesConfigureesTotal = configuredCoutChargesTotal(form);
    const coutAjustementsChargesTotal = coutValue(form, 'coutElectricite')
        + coutValue(form, 'coutEau')
        + coutValue(form, 'coutGlace')
        + coutValue(form, 'coutNettoyage')
        + coutValue(form, 'coutMaintenance')
        + coutValue(form, 'coutTransportLivraison')
        + coutValue(form, 'autresCharges');
    const coutChargesTotal = coutChargesConfigureesTotal + coutAjustementsChargesTotal;

    const coutTotalProduction = coutMatierePremiere + coutMainOeuvre + coutEmballageTotal + coutChargesTotal;
    const coutRevientKg = poidsFini > 0 ? coutTotalProduction / poidsFini : 0;
    const rendementPourcentage = poidsProduction > 0 ? (poidsFini / poidsProduction) * 100 : 0;
    const prixVenteKgRaw = coutField(form, 'prixVenteKg')?.value ?? '';
    const hasPrixVente = String(prixVenteKgRaw).trim() !== '' && coutNumber(prixVenteKgRaw) > 0;
    const margeKg = hasPrixVente ? coutNumber(prixVenteKgRaw) - coutRevientKg : 0;
    const margeTotale = hasPrixVente ? margeKg * poidsFini : 0;
    const tauxMargePourcentage = coutTotalProduction > 0 ? (margeTotale / coutTotalProduction) * 100 : 0;
    const alerts = [];
    const totalSortie = poidsFini + coutValue(form, 'poidsDechets') + coutValue(form, 'poidsPerte');
    const ecartPoidsProduction = totalSortie - poidsProduction;
    if (poidsProduction > 0 && Math.abs(totalSortie - poidsProduction) > 0.001) {
        alerts.push('Attention : le total fini + dechets + pertes ne correspond pas au poids mis en production.');
    }
    if (poidsFini <= 0) alerts.push('Impossible de calculer le cout/kg sans poids fini.');
    if (rendementPourcentage > 100) alerts.push('Rendement impossible.');
    if (rendementPourcentage > 0 && rendementPourcentage < 35) alerts.push('Rendement faible, verifier pertes et dechets.');
    if (hasPrixVente && margeKg < 0) alerts.push('Production non rentable.');
    const productionDiagnostic = getProductionDiagnostic({
        poidsProduction,
        poidsFini,
        poidsDechets: coutValue(form, 'poidsDechets'),
        poidsPerte: coutValue(form, 'poidsPerte'),
        totalSortie,
        ecartPoidsProduction,
        rendementPourcentage,
    });

    return {
        coutAchatPoisson,
        coutFraisAchatTotal,
        coutMatierePremiere,
        coutMainOeuvreParPersonne,
        coutMainOeuvre,
        kgTraitesMainOeuvre: coutValue(form, 'kgTraitesMainOeuvre'),
        coutCartonsTotal,
        coutSachetsTotal,
        coutEmballageTotal,
        coutChargesConfigureesTotal,
        coutAjustementsChargesTotal,
        coutTotalProduction,
        coutRevientKg,
        totalSortieProduction: totalSortie,
        ecartPoidsProduction,
        rendementPourcentage,
        margeKg,
        margeTotale,
        tauxMargePourcentage,
        coutChargesTotal,
        rentabiliteLabel: !hasPrixVente ? 'Sans prix vente' : (margeKg < 0 ? 'Non rentable' : (Math.abs(margeKg) < 0.01 ? 'Marge nulle' : 'Rentable')),
        rentabiliteBadgeClass: !hasPrixVente ? 'text-bg-secondary' : (margeKg < 0 ? 'text-bg-danger' : (Math.abs(margeKg) < 0.01 ? 'text-bg-warning' : 'text-bg-success')),
        productionDiagnostic,
        alerts,
    };
}

function getProductionDiagnostic({
    poidsProduction,
    poidsFini,
    poidsDechets,
    poidsPerte,
    totalSortie,
    ecartPoidsProduction,
    rendementPourcentage,
}) {
    if (poidsProduction <= 0 || totalSortie <= 0) {
        return {
            className: 'alert-secondary',
            message: 'Renseignez le poids mis en production, le poids fini, les dechets et les pertes pour analyser la production.',
        };
    }

    const dechetsRate = (poidsDechets / poidsProduction) * 100;
    const perteRate = (poidsPerte / poidsProduction) * 100;
    const rejetsRate = ((poidsDechets + poidsPerte) / poidsProduction) * 100;

    if (Math.abs(ecartPoidsProduction) > 0.1) {
        return {
            className: 'alert-warning',
            message: `A verifier : fini + dechets + pertes = ${coutFormat(totalSortie)} kg, soit un ecart de ${coutFormat(ecartPoidsProduction)} kg avec le poids mis en production.`,
        };
    }

    if (rendementPourcentage > 100) {
        return {
            className: 'alert-danger',
            message: 'Erreur de saisie : le rendement depasse 100%. Verifiez les poids saisis.',
        };
    }

    if (rendementPourcentage < 35 || perteRate > 12) {
        return {
            className: 'alert-danger',
            message: `Production anormale : beaucoup de dechets/pertes (${coutFormat(rejetsRate)}%). Fini ${coutFormat(rendementPourcentage)}%, dechets ${coutFormat(dechetsRate)}%, pertes ${coutFormat(perteRate)}%.`,
        };
    }

    if ((rendementPourcentage >= 35 && rendementPourcentage <= 45) && perteRate <= 8) {
        return {
            className: 'alert-success',
            message: `Production normale : rendement ${coutFormat(rendementPourcentage)}%, dechets/pertes ${coutFormat(rejetsRate)}%. La zone cible anchois filet est respectee.`,
        };
    }

    return {
        className: 'alert-warning',
        message: `Production a surveiller : rendement ${coutFormat(rendementPourcentage)}%, dechets ${coutFormat(dechetsRate)}%, pertes ${coutFormat(perteRate)}%. Verifiez si le lot correspond bien a un filet anchois standard.`,
    };
}

function syncCoutRevientForm(form) {
    syncCoutMode(form);
    form.querySelectorAll('input[type="number"]').forEach((input) => {
        if (Number(input.value) < 0) input.value = '0';
    });

    const result = calculateCoutRevient(form);
    form.querySelectorAll('[data-cout-output]').forEach((output) => {
        const key = output.dataset.coutOutput;
        output.textContent = coutFormat(result[key] ?? 0);
    });

    form.querySelectorAll('[data-cout-rentability]').forEach((rentability) => {
        rentability.className = `badge ${result.rentabiliteBadgeClass}`;
        rentability.textContent = result.rentabiliteLabel;
    });

    const productionDiagnostic = form.querySelector('[data-cout-production-diagnostic]');
    if (productionDiagnostic) {
        productionDiagnostic.className = `alert ${result.productionDiagnostic.className} small mb-0 mt-3`;
        productionDiagnostic.textContent = result.productionDiagnostic.message;
    }

    const alerts = form.querySelector('[data-cout-alerts]');
    if (alerts) {
        alerts.innerHTML = result.alerts.map((alert) => `<div class="alert alert-warning small mb-0">${escapeHtml(alert)}</div>`).join('');
    }
}

function initializeCoutRevientForms(root = document) {
    root.querySelectorAll('[data-cout-revient-form]').forEach((form) => {
        syncCoutRevientForm(form);
        if (form.dataset.coutRevientInitialized === 'true') return;
        form.dataset.coutRevientInitialized = 'true';
        refreshCoutChargeEmptyState(form);
        form.addEventListener('input', (event) => {
            if (event.target.matches('input, textarea')) syncCoutRevientForm(form);
        });
        form.addEventListener('change', (event) => {
            if (event.target.matches('select, input')) syncCoutRevientForm(form);
        });
        form.addEventListener('click', (event) => {
            const addButton = event.target.closest('[data-cout-charge-add]');
            if (addButton) {
                addCoutChargeLine(form);
                return;
            }

            const removeButton = event.target.closest('[data-cout-charge-remove]');
            if (removeButton) {
                removeButton.closest('[data-cout-charge-line]')?.remove();
                refreshCoutChargeEmptyState(form);
                syncCoutRevientForm(form);
            }
        });
        form.addEventListener('submit', () => syncCoutRevientForm(form));
    });
}

async function refreshCoutRevientDashboard(form) {
    const region = document.querySelector('[data-cout-dashboard-region]');
    if (!region || !form?.dataset.dashboardUrl) return;

    const url = new URL(form.dataset.dashboardUrl, window.location.origin);
    for (const [key, value] of new FormData(form).entries()) {
        if (String(value).trim() !== '') url.searchParams.set(key, value);
    }

    region.setAttribute('aria-busy', 'true');
    region.classList.add('opacity-50');
    try {
        const response = await fetch(url, {
            headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        });
        const payload = await readJson(response);
        region.innerHTML = payload.data.html;
    } finally {
        region.removeAttribute('aria-busy');
        region.classList.remove('opacity-50');
    }
}

function initializeCoutRevientDashboard(root = document) {
    root.querySelectorAll('[data-cout-dashboard-form]').forEach((form) => {
        if (form.dataset.coutDashboardInitialized === 'true') return;
        form.dataset.coutDashboardInitialized = 'true';
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            refreshCoutRevientDashboard(form).catch((error) => showAlert(error.message, 'danger'));
        });
        form.addEventListener('change', () => {
            refreshCoutRevientDashboard(form).catch((error) => showAlert(error.message, 'danger'));
        });
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

let appointmentCalendar = null;
let fullCalendarLoader = null;
const fullCalendarCdnUrl = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js';

function appointmentUrl(template, id) {
    return String(template || '').replace('999999', String(id));
}

function appendAppointmentFilters(url) {
    document.querySelectorAll('[data-appointment-filter]').forEach((field) => {
        const key = field.dataset.appointmentFilter;
        if (key && field.value) url.searchParams.set(key, field.value);
    });
}

function appointmentCalendarFilterParams(root) {
    const params = {};
    document.querySelectorAll('[data-calendar-filter]').forEach((field) => {
        const key = field.dataset.calendarFilter;
        if (key && field.value) params[key] = field.value;
    });
    if (root?.dataset.mine === '1') params.mine = '1';

    return params;
}

async function refreshAjaxRegion(name, options = {}) {
    const region = document.querySelector(`[data-ajax-region="${CSS.escape(name)}"]`);
    if (!region) return;

    const url = new URL(region.dataset.refreshUrl, window.location.origin);
    const form = region.dataset.refreshForm ? document.querySelector(region.dataset.refreshForm) : null;
    if (form) {
        for (const [key, value] of new FormData(form).entries()) {
            if (String(value).trim() !== '') url.searchParams.set(key, value);
        }
    }
    if (options.page) url.searchParams.set('page', String(options.page));

    region.setAttribute('aria-busy', 'true');
    region.classList.add('opacity-50');
    try {
        const response = await fetch(url, {
            headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        });
        const payload = await readJson(response);
        region.innerHTML = payload.data.html;

        const count = document.querySelector(`[data-ajax-count-region="${CSS.escape(name)}"]`);
        if (count && Number.isFinite(Number(payload.data.count))) {
            const total = Number(payload.data.count);
            count.textContent = `${total} élément${total > 1 ? 's' : ''} visible${total > 1 ? 's' : ''}.`;
        }
    } finally {
        region.removeAttribute('aria-busy');
        region.classList.remove('opacity-50');
    }
}

function syncInventoryMoveLocations(root = document) {
    root.querySelectorAll('[data-inventory-move-site]').forEach((siteField) => {
        const form = siteField.closest('form');
        const locationField = form?.querySelector('[data-inventory-move-location]');
        if (!locationField) return;

        const selectedSite = siteField.value;
        [...locationField.options].forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                return;
            }
            option.hidden = option.dataset.siteId !== selectedSite;
            if (option.hidden && option.selected) option.selected = false;
        });
    });
}

async function searchInventoryContacts(input) {
    const form = input.closest('[data-inventory-whatsapp-form]');
    const results = form?.querySelector('[data-inventory-contact-results]');
    const contactId = form?.querySelector('[data-inventory-contact-id]');
    const selection = form?.querySelector('[data-inventory-contact-selection]');
    const query = input.value.trim();
    if (!form || !results || !contactId || !selection) return;

    contactId.value = '';
    selection.textContent = 'Aucun contact sélectionné.';
    if (query.length < 2) {
        results.innerHTML = '';
        return;
    }

    const url = new URL(form.dataset.contactSearchUrl, window.location.origin);
    url.searchParams.set('q', query);
    const response = await fetch(url, {
        headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
    });
    const payload = await readJson(response);
    const contacts = payload.data.contacts || [];
    results.innerHTML = contacts.length
        ? contacts.map((contact) => `
            <button
                type="button"
                class="list-group-item list-group-item-action"
                data-inventory-contact-option
                data-contact-id="${escapeHtml(contact.id)}"
                data-contact-name="${escapeHtml(contact.name)}"
                data-contact-phone="${escapeHtml(contact.phone || '')}"
            >
                <span class="fw-semibold">${escapeHtml(contact.name)}</span>
                <span class="small text-secondary d-block">${escapeHtml(contact.company)} · ${escapeHtml(contact.phone || 'Sans portable')}</span>
            </button>
        `).join('')
        : '<div class="list-group-item text-secondary small">Aucun contact avec portable trouvé.</div>';
}

function loadFullCalendar() {
    if (window.FullCalendar) return Promise.resolve(window.FullCalendar);
    if (fullCalendarLoader) return fullCalendarLoader;

    fullCalendarLoader = new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${fullCalendarCdnUrl}"]`);
        if (existing) {
            existing.addEventListener('load', () => resolve(window.FullCalendar), {once: true});
            existing.addEventListener('error', () => reject(new Error('Impossible de charger FullCalendar.')), {once: true});
            return;
        }

        const script = document.createElement('script');
        script.src = fullCalendarCdnUrl;
        script.async = true;
        script.onload = () => resolve(window.FullCalendar);
        script.onerror = () => reject(new Error('Impossible de charger FullCalendar.'));
        document.head.appendChild(script);
    });

    return fullCalendarLoader;
}

function updateAppointmentSearchCounters(search, count) {
    const card = search.closest('.card');
    const counter = document.querySelector('[data-appointment-search-count]');
    const label = document.querySelector('[data-appointment-search-label]');
    const clearButton = card?.querySelector('[data-appointment-clear-search]');
    if (counter) counter.textContent = String(count);
    if (label) label.textContent = count > 1 ? 'résultats' : 'résultat';
    clearButton?.classList.toggle('d-none', normalizeSearch(search.value) === '');
}

async function loadAppointmentGrid(search, page = 1) {
    const target = document.getElementById(search.dataset.appointmentTarget);
    if (!target) return;

    target.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body py-5 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement</span></div></div></div>';
    const url = new URL(search.dataset.appointmentSearchUrl, window.location.origin);
    url.searchParams.set('q', search.value);
    url.searchParams.set('page', String(page));
    appendAppointmentFilters(url);

    const payload = await readJson(await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}}));
    target.innerHTML = payload.data.html;
    updateAppointmentSearchCounters(search, payload.data.count);
}

function appointmentIso(value) {
    if (!value) return '';
    if (!(value instanceof Date)) return String(value);

    const pad = (part) => String(part).padStart(2, '0');

    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}T${pad(value.getHours())}:${pad(value.getMinutes())}:${pad(value.getSeconds())}`;
}

function appointmentDateValue(value) {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (part) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function appointmentTimeValue(value) {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (part) => String(part).padStart(2, '0');

    return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function appointmentDisplayRange(start, end) {
    const formatter = new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
    const startDate = start instanceof Date ? start : new Date(start);
    const endDate = end instanceof Date ? end : new Date(end);
    if (Number.isNaN(startDate.getTime())) return 'Créneau sélectionné';
    if (Number.isNaN(endDate.getTime())) return formatter.format(startDate);

    return `${formatter.format(startDate)} - ${formatter.format(endDate)}`;
}

function appointmentDateTimeFromFields(dateValue, timeValue) {
    if (!dateValue || !timeValue) return null;
    const date = new Date(`${dateValue}T${timeValue}:00`);

    return Number.isNaN(date.getTime()) ? null : date;
}

function syncQuickAppointmentDateFields(form) {
    const dateInput = form?.querySelector('[data-appointment-date]');
    const timeInput = form?.querySelector('[data-appointment-time]');
    const durationInput = form?.querySelector('[data-appointment-duration]');
    const startInput = form?.querySelector('[name="startAt"]');
    const endInput = form?.querySelector('[name="endAt"]');
    const slot = form?.closest('.modal')?.querySelector('[data-appointment-selected-slot]');
    if (!dateInput || !timeInput || !durationInput || !startInput || !endInput) return false;

    const startDate = appointmentDateTimeFromFields(dateInput.value, timeInput.value);
    if (!startDate) {
        startInput.value = '';
        endInput.value = '';
        if (slot) slot.textContent = 'Choisissez une date et une heure.';
        return false;
    }

    const duration = Math.max(15, Number.parseInt(durationInput.value || '60', 10));
    const endDate = new Date(startDate.getTime() + duration * 60 * 1000);
    startInput.value = appointmentIso(startDate);
    endInput.value = appointmentIso(endDate);
    if (slot) slot.textContent = appointmentDisplayRange(startDate, endDate);

    return true;
}

function openQuickAppointmentModal(start, end = null) {
    const modal = document.getElementById('quickAppointmentModal');
    const form = modal?.querySelector('[data-appointment-quick-form]');
    if (!modal || !form) return;

    const startInput = form.querySelector('[name="startAt"]');
    const titleInput = form.querySelector('[name="title"]');
    const dateInput = form.querySelector('[data-appointment-date]');
    const timeInput = form.querySelector('[data-appointment-time]');
    const durationInput = form.querySelector('[data-appointment-duration]');
    const slot = modal.querySelector('[data-appointment-selected-slot]');
    const startDate = start instanceof Date ? start : new Date(start);
    const endDate = end ? (end instanceof Date ? end : new Date(end)) : new Date(startDate.getTime() + 60 * 60 * 1000);
    const duration = Math.max(15, Math.round((endDate.getTime() - startDate.getTime()) / 60000));

    form.reset();
    if (dateInput) dateInput.value = appointmentDateValue(startDate);
    if (timeInput) timeInput.value = appointmentTimeValue(startDate);
    if (durationInput && [...durationInput.options].some((option) => option.value === String(duration))) {
        durationInput.value = String(duration);
    }
    syncQuickAppointmentDateFields(form);
    if (slot && !startInput.value) slot.textContent = appointmentDisplayRange(startDate, endDate);
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
    window.setTimeout(() => titleInput?.focus(), 180);
}

async function openAppointmentDetails(root, id) {
    const modal = document.getElementById('remoteModal');
    const dialog = modal?.querySelector('.modal-dialog');
    const content = document.getElementById('remoteModalContent');
    if (!modal || !dialog || !content) return;

    dialog.className = 'modal-dialog modal-fullscreen modal-dialog-scrollable';
    content.innerHTML = '<div class="modal-body py-5 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement</span></div></div>';
    window.bootstrap.Modal.getOrCreateInstance(modal).show();

    try {
        const response = await fetch(appointmentUrl(root.dataset.viewUrlTemplate, id), {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
        });
        if (!response.ok) throw new Error('Impossible de charger le rendez-vous.');
        content.innerHTML = await response.text();
    } catch (error) {
        content.innerHTML = `<div class="modal-body"><div class="alert alert-danger mb-0">${escapeHtml(error.message)}</div></div>`;
    }
}

async function sendAppointmentCalendarChange(root, template, event) {
    const startAt = event.startStr || appointmentIso(event.start);
    const endAt = event.endStr || appointmentIso(event.end || new Date(event.start.getTime() + 60 * 60 * 1000));

    return sendJson(appointmentUrl(template, event.id), 'POST', {
        token: root.dataset.token,
        startAt,
        endAt,
    });
}

async function initializeAppointmentCalendar() {
    const root = document.querySelector('[data-appointment-calendar]');
    const calendarElement = document.getElementById('appointmentCalendar');
    if (!root || !calendarElement) return;
    if (appointmentCalendar?.el === calendarElement) return;
    if (appointmentCalendar) {
        try {
            appointmentCalendar.destroy();
        } catch {
            // The previous calendar may already be detached after a Turbo page swap.
        }
        appointmentCalendar = null;
    }

    if (!window.FullCalendar) {
        try {
            await loadFullCalendar();
        } catch (error) {
            root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message)}</div>`;
            return;
        }
    }

    if (!window.FullCalendar) {
        root.innerHTML = '<div class="alert alert-danger mb-0">Le calendrier n’a pas pu être chargé.</div>';
        return;
    }

    appointmentCalendar = new window.FullCalendar.Calendar(calendarElement, {
        locale: 'fr',
        height: window.matchMedia('(max-width: 767px)').matches ? 'auto' : 'calc(100vh - 230px)',
        expandRows: true,
        initialView: window.matchMedia('(max-width: 767px)').matches ? 'listWeek' : 'timeGridWeek',
        firstDay: 1,
        nowIndicator: true,
        navLinks: true,
        selectable: true,
        editable: true,
        eventResizableFromStart: true,
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        buttonText: {
            today: 'Aujourd’hui',
            month: 'Mois',
            week: 'Semaine',
            day: 'Jour',
            list: 'Liste',
        },
        events(fetchInfo, successCallback, failureCallback) {
            const url = new URL(root.dataset.eventsUrl, window.location.origin);
            url.searchParams.set('start', fetchInfo.startStr);
            url.searchParams.set('end', fetchInfo.endStr);
            Object.entries(appointmentCalendarFilterParams(root)).forEach(([key, value]) => url.searchParams.set(key, value));
            fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then((response) => {
                    if (!response.ok) throw new Error('Impossible de charger le calendrier.');
                    return response.json();
                })
                .then(successCallback)
                .catch(failureCallback);
        },
        dateClick(info) {
            openQuickAppointmentModal(info.date, null);
        },
        select(info) {
            openQuickAppointmentModal(info.start, info.end);
            appointmentCalendar.unselect();
        },
        eventClick(info) {
            info.jsEvent.preventDefault();
            openAppointmentDetails(root, info.event.id);
        },
        async eventDrop(info) {
            try {
                const payload = await sendAppointmentCalendarChange(root, root.dataset.moveUrlTemplate, info.event);
                showAlert(payload.message);
            } catch (error) {
                info.revert();
                showAlert(error.message, 'danger');
            }
        },
        async eventResize(info) {
            try {
                const payload = await sendAppointmentCalendarChange(root, root.dataset.resizeUrlTemplate, info.event);
                showAlert(payload.message);
            } catch (error) {
                info.revert();
                showAlert(error.message, 'danger');
            }
        },
    });

    appointmentCalendar.render();
}

function destroyAppointmentCalendar() {
    if (!appointmentCalendar) return;
    try {
        appointmentCalendar.destroy();
    } catch {
        // Ignore stale FullCalendar instances during Turbo cache cleanup.
    }
    appointmentCalendar = null;
}

function initializePageBehaviors() {
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
        '#nouveau-rendez-vous': '#quickAppointmentModal',
        '#nouvel-interimaire': '#createInterimWorkerModal',
    };
    const targetSelector = modalByHash[window.location.hash];
    if (targetSelector) openNavigationModal(targetSelector);
    initializeMaintenanceSmartForms();
    initializeExpenseForms();
    initializeInterimWorkerForms();
    initializeCoutRevientForms();
    initializeCoutRevientDashboard();
    initializeContactPhones();
    initializeChoiceTags();
    initializeSidebarSearches();
    initializeAppointmentCalendar();
}

document.addEventListener('DOMContentLoaded', initializePageBehaviors);
document.addEventListener('turbo:load', initializePageBehaviors);
document.addEventListener('turbo:before-cache', destroyAppointmentCalendar);

document.addEventListener('submit', async (event) => {
    const ajaxForm = event.target.closest('[data-ajax-form]');
    if (ajaxForm) {
        event.preventDefault();
        if (ajaxForm.matches('[data-maintenance-contract-form]') && !validateMaintenanceContractDates(ajaxForm)) {
            return;
        }

        const button = event.submitter?.matches?.('[type="submit"]') ? event.submitter : ajaxForm.querySelector('[type="submit"]');
        button?.setAttribute('disabled', 'disabled');
        try {
            const formData = new FormData(ajaxForm);
            if (event.submitter?.name) formData.set(event.submitter.name, event.submitter.value);
            const response = await fetch(ajaxForm.action, {
                method: ajaxForm.method || 'POST',
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: formData,
            });
            const payload = await readJson(response);
            showAlert(payload.message);
            if (payload.data?.closeModal) {
                const modalElement = ajaxForm.closest('.modal');
                if (modalElement) window.bootstrap.Modal.getInstance(modalElement)?.hide();
                ajaxForm.reset();
            }
            if (payload.data?.refreshRegion) await refreshAjaxRegion(payload.data.refreshRegion);
            if (payload.data?.redirectUrl) {
                window.location.href = payload.data.redirectUrl;
                return;
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
    const sidebarSearchClear = event.target.closest('[data-sidebar-search-clear]');
    if (sidebarSearchClear) {
        const navigation = sidebarSearchClear.closest('.sidebar-navigation');
        const input = navigation?.querySelector('[data-sidebar-search-input]');
        if (input) {
            input.value = '';
            filterSidebarNavigation(navigation, '');
            input.focus();
        }
        return;
    }

    const inventoryContactOption = event.target.closest('[data-inventory-contact-option]');
    if (inventoryContactOption) {
        const form = inventoryContactOption.closest('[data-inventory-whatsapp-form]');
        const contactId = form?.querySelector('[data-inventory-contact-id]');
        const selection = form?.querySelector('[data-inventory-contact-selection]');
        const results = form?.querySelector('[data-inventory-contact-results]');
        const input = form?.querySelector('[data-inventory-contact-search]');
        if (contactId) contactId.value = inventoryContactOption.dataset.contactId;
        if (selection) selection.textContent = `${inventoryContactOption.dataset.contactName} · ${inventoryContactOption.dataset.contactPhone}`;
        if (input) input.value = inventoryContactOption.dataset.contactName;
        if (results) results.innerHTML = '';
        return;
    }

    const inventoryWhatsappSend = event.target.closest('[data-inventory-whatsapp-send]');
    if (inventoryWhatsappSend) {
        const modal = inventoryWhatsappSend.closest('.modal');
        const form = modal?.querySelector('[data-inventory-whatsapp-form]');
        if (!form) return;

        const action = form.querySelector('input[name="action"]:checked')?.value;
        const destinationSiteId = form.querySelector('[name="destinationSiteId"]')?.value;
        const quantity = form.querySelector('[name="quantity"]')?.value;
        const notes = form.querySelector('[name="notes"]')?.value;
        const contactId = form.querySelector('[data-inventory-contact-id]')?.value;
        if (!contactId) {
            showAlert('Sélectionnez un contact.', 'danger');
            return;
        }
        if (action === 'transport' && !destinationSiteId) {
            showAlert('Sélectionnez un site de destination.', 'danger');
            return;
        }

        const whatsappWindow = window.open('', '_blank');
        inventoryWhatsappSend.disabled = true;
        try {
            const payload = await sendJson(form.dataset.url, 'POST', {
                token: form.dataset.token,
                action,
                destinationSiteId,
                quantity,
                notes,
                contactId,
            });
            showAlert(payload.message);
            if (whatsappWindow) whatsappWindow.location.href = payload.data.url;
            else window.location.href = payload.data.url;
        } catch (error) {
            whatsappWindow?.close();
            showAlert(error.message, 'danger');
        } finally {
            inventoryWhatsappSend.disabled = false;
        }
        return;
    }

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

    const replaceDocumentFileButton = event.target.closest('[data-document-replace-file]');
    if (replaceDocumentFileButton) {
        event.preventDefault();
        const panel = replaceDocumentFileButton.closest('[data-document-file-panel]');
        const currentFile = panel?.querySelector('[data-document-current-file]');
        const fileField = panel?.querySelector('[data-document-file-field]');
        const fileInput = fileField?.querySelector('input[type="file"]');

        currentFile?.classList.add('d-none');
        fileField?.classList.remove('d-none');
        if (fileInput) {
            fileInput.required = true;
            fileInput.focus();
        }
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
            if (payload.data?.refreshRegion) await refreshAjaxRegion(payload.data.refreshRegion);
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
            initializeInterimWorkerForms(content);
            initializeCoutRevientForms(content);
            initializeCoutRevientDashboard(content);
            initializeContactPhones(content);
            initializeChoiceTags(content);
            syncInventoryMoveLocations(content);
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
        if (!await confirmAction(confirmButton.dataset.confirmMessage || 'Confirmer cette action ?')) return;
        confirmButton.disabled = true;
        try {
            const payload = await sendJson(
                confirmButton.dataset.confirmUrl,
                confirmButton.dataset.confirmMethod || 'POST',
                {token: confirmButton.dataset.token},
            );
            showAlert(payload.message);
            if (payload.data?.closeModal) {
                const modalElement = confirmButton.closest('.modal');
                if (modalElement) window.bootstrap.Modal.getInstance(modalElement)?.hide();
            }
            if (payload.data?.refreshRegion) await refreshAjaxRegion(payload.data.refreshRegion);
            if (payload.data?.redirectUrl) {
                window.location.href = payload.data.redirectUrl;
                return;
            }
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
    if (handleSidebarSearchEvent(event.target)) return;

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

document.addEventListener('submit', async (event) => {
    const quickAppointmentForm = event.target.closest('[data-appointment-quick-form]');
    if (quickAppointmentForm) {
        event.preventDefault();
        const root = document.querySelector('[data-appointment-calendar]');
        if (!root) return;

        if (!syncQuickAppointmentDateFields(quickAppointmentForm)) {
            showAlert('Choisissez une date et une heure valides.', 'danger');
            return;
        }

        const button = quickAppointmentForm.querySelector('[type="submit"]');
        button?.setAttribute('disabled', 'disabled');
        const formData = new FormData(quickAppointmentForm);
        try {
            const payload = await sendJson(root.dataset.quickCreateUrl, 'POST', {
                token: formData.get('token'),
                title: formData.get('title'),
                appointmentDate: formData.get('appointmentDate'),
                startTime: formData.get('startTime'),
                startAt: formData.get('startAt'),
                endAt: formData.get('endAt'),
                duration: formData.get('duration'),
                location: formData.get('location'),
                customerName: formData.get('customerName'),
                appointmentType: formData.get('appointmentType'),
                priority: formData.get('priority'),
                participantIds: formData.getAll('participantIds'),
            });
            showAlert(payload.message);
            window.bootstrap.Modal.getInstance(quickAppointmentForm.closest('.modal'))?.hide();
            quickAppointmentForm.reset();
            appointmentCalendar?.refetchEvents();
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            button?.removeAttribute('disabled');
        }
        return;
    }

    const cancelAppointmentForm = event.target.closest('[data-appointment-cancel-form]');
    if (cancelAppointmentForm) {
        event.preventDefault();
        try {
            const payload = await sendJson(cancelAppointmentForm.dataset.url, 'POST', {
                token: cancelAppointmentForm.dataset.token,
                reason: new FormData(cancelAppointmentForm).get('reason'),
            });
            showAlert(payload.message);
            if (payload.data?.reload) window.location.reload();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    }
});

document.addEventListener('click', async (event) => {
    const quickCreateButton = event.target.closest('[data-appointment-open-quick-create]');
    if (quickCreateButton) {
        const start = new Date();
        start.setMinutes(0, 0, 0);
        start.setHours(start.getHours() + 1);
        openQuickAppointmentModal(start, null);
        return;
    }

    const appointmentPageButton = event.target.closest('[data-appointment-page]');
    if (appointmentPageButton && !appointmentPageButton.disabled) {
        const search = document.querySelector('[data-appointment-search]');
        if (search) {
            try {
                await loadAppointmentGrid(search, Number(appointmentPageButton.dataset.appointmentPage || 1));
            } catch (error) {
                showAlert(error.message, 'danger');
            }
        }
        return;
    }

    const appointmentClearSearch = event.target.closest('[data-appointment-clear-search]');
    if (appointmentClearSearch) {
        const search = document.querySelector('[data-appointment-search]');
        if (search) {
            search.value = '';
            try {
                await loadAppointmentGrid(search, 1);
                search.focus();
            } catch (error) {
                showAlert(error.message, 'danger');
            }
        }
        return;
    }

    const appointmentCancel = event.target.closest('[data-appointment-cancel-url]');
    if (appointmentCancel) {
        const reason = window.prompt('Motif d’annulation (facultatif)') || '';
        appointmentCancel.disabled = true;
        try {
            const payload = await sendJson(appointmentCancel.dataset.appointmentCancelUrl, 'POST', {
                token: appointmentCancel.dataset.token,
                reason,
            });
            showAlert(payload.message);
            if (payload.data?.reload) window.location.reload();
        } catch (error) {
            showAlert(error.message, 'danger');
            appointmentCancel.disabled = false;
        }
    }
});

document.addEventListener('input', (event) => {
    const inventoryContactSearch = event.target.closest('[data-inventory-contact-search]');
    if (inventoryContactSearch) {
        window.clearTimeout(debounceTimers.get(inventoryContactSearch));
        debounceTimers.set(inventoryContactSearch, window.setTimeout(() => {
            searchInventoryContacts(inventoryContactSearch).catch((error) => showAlert(error.message, 'danger'));
        }, 250));
        return;
    }

    const quickAppointmentDateField = event.target.closest('[data-appointment-date], [data-appointment-time]');
    if (quickAppointmentDateField) {
        syncQuickAppointmentDateFields(quickAppointmentDateField.closest('[data-appointment-quick-form]'));
        return;
    }

    const appointmentSearch = event.target.closest('[data-appointment-search]');
    if (appointmentSearch) {
        scheduleDebounce(appointmentSearch, async () => {
            try {
                await loadAppointmentGrid(appointmentSearch, 1);
            } catch (error) {
                showAlert(error.message, 'danger');
            }
        });
    }
});

document.addEventListener('change', (event) => {
    const inventoryMoveSite = event.target.closest('[data-inventory-move-site]');
    if (inventoryMoveSite) {
        syncInventoryMoveLocations(inventoryMoveSite.closest('form'));
        return;
    }

    const inventoryWhatsappAction = event.target.closest('[data-inventory-whatsapp-form] input[name="action"]');
    if (inventoryWhatsappAction) {
        const form = inventoryWhatsappAction.closest('[data-inventory-whatsapp-form]');
        form?.querySelector('[data-inventory-whatsapp-destination]')?.classList.toggle('d-none', inventoryWhatsappAction.value !== 'transport');
        return;
    }

    const ajaxFilter = event.target.closest('[data-ajax-filter-region]');
    if (ajaxFilter && event.target.matches('select')) {
        refreshAjaxRegion(ajaxFilter.dataset.ajaxFilterRegion).catch((error) => showAlert(error.message, 'danger'));
        return;
    }

    const quickAppointmentDateField = event.target.closest('[data-appointment-date], [data-appointment-time], [data-appointment-duration]');
    if (quickAppointmentDateField) {
        syncQuickAppointmentDateFields(quickAppointmentDateField.closest('[data-appointment-quick-form]'));
        return;
    }

    const appointmentFilter = event.target.closest('[data-appointment-filter]');
    if (appointmentFilter) {
        const search = document.querySelector('[data-appointment-search]');
        if (search) {
            loadAppointmentGrid(search, 1).catch((error) => showAlert(error.message, 'danger'));
        }
        return;
    }

    const calendarFilter = event.target.closest('[data-calendar-filter]');
    if (calendarFilter) {
        appointmentCalendar?.refetchEvents();
    }
});

document.addEventListener('keyup', (event) => {
    handleSidebarSearchEvent(event.target);
});

document.addEventListener('search', (event) => {
    handleSidebarSearchEvent(event.target);
});

document.addEventListener('input', (event) => {
    const ajaxFilter = event.target.closest('[data-ajax-filter-region]');
    if (!ajaxFilter || !event.target.matches('input[type="search"]')) return;

    window.clearTimeout(debounceTimers.get(ajaxFilter));
    debounceTimers.set(ajaxFilter, window.setTimeout(() => {
        refreshAjaxRegion(ajaxFilter.dataset.ajaxFilterRegion).catch((error) => showAlert(error.message, 'danger'));
    }, 300));
});

document.addEventListener('submit', (event) => {
    const ajaxFilter = event.target.closest('[data-ajax-filter-region]');
    if (!ajaxFilter) return;

    event.preventDefault();
    refreshAjaxRegion(ajaxFilter.dataset.ajaxFilterRegion).catch((error) => showAlert(error.message, 'danger'));
});

document.addEventListener('click', (event) => {
    const pageLink = event.target.closest('[data-ajax-region-page]');
    if (!pageLink || pageLink.classList.contains('disabled')) return;

    event.preventDefault();
    refreshAjaxRegion(pageLink.dataset.ajaxRegionPage, {page: pageLink.dataset.page})
        .catch((error) => showAlert(error.message, 'danger'));
});
