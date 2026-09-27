(function () {
    const config = window.beneficiariesConfig || {};
    const modal = document.getElementById('beneficiary-modal');
    const filterForm = document.getElementById('filter-form');
    const searchInput = document.getElementById('search-input');

    function toast(message, icon = 'success') {
        if (typeof Swal === 'undefined') return;
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon,
            title: message,
            showConfirmButton: false,
            timer: 3200,
            timerProgressBar: true,
        });
    }

    function confirmAction(options) {
        if (typeof Swal === 'undefined') {
            return Promise.resolve({
                isConfirmed: window.confirm(`${options.title}\n\n${options.text}`),
            });
        }

        return Swal.fire({
            icon: options.icon || 'warning',
            title: options.title,
            text: options.text,
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Confirm',
            cancelButtonText: 'Cancel',
            confirmButtonColor: options.confirmColor || '#2563eb',
            cancelButtonColor: '#64748b',
            customClass: {
                popup: 'modern-swal-popup',
                title: 'modern-swal-title',
                content: 'modern-swal-content',
                confirmButton: 'modern-swal-confirm',
                cancelButton: 'modern-swal-cancel',
            },
        });
    }

    function submitHiddenForm(action, method, fields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = config.csrf;
        form.appendChild(csrf);

        if (method && method !== 'POST') {
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = method;
            form.appendChild(methodInput);
        }

        Object.entries(fields || {}).forEach(([name, value]) => {
            const values = Array.isArray(value) ? value : [value];
            values.forEach((fieldValue) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = Array.isArray(value) ? `${name}[]` : name;
                input.value = fieldValue;
                form.appendChild(input);
            });
        });

        document.body.appendChild(form);
        form.submit();
    }

    window.toggleBulkMenu = function () {
        document.getElementById('bulk-actions-menu')?.classList.toggle('show');
    };

    window.toggleSelectAll = function (checked) {
        document.querySelectorAll('.beneficiary-checkbox').forEach((cb) => {
            cb.checked = checked;
        });
        updateSelectedCount();
    };

    window.selectAll = function () {
        toggleSelectAll(true);
        const master = document.getElementById('select-all-checkbox');
        if (master) master.checked = true;
        toggleBulkMenu();
    };

    window.clearSelection = function () {
        toggleSelectAll(false);
        const master = document.getElementById('select-all-checkbox');
        if (master) {
            master.checked = false;
            master.indeterminate = false;
        }
        updateSelectedCount();
        toggleBulkMenu();
    };

    window.updateSelectedCount = function () {
        const checked = document.querySelectorAll('.beneficiary-checkbox:checked');
        const total = document.querySelectorAll('.beneficiary-checkbox').length;
        const count = checked.length;

        const countEl = document.getElementById('selected-count');
        const menuCountEl = document.getElementById('selected-count-menu');
        const bar = document.getElementById('selection-bar');
        const master = document.getElementById('select-all-checkbox');

        if (countEl) countEl.textContent = count;
        if (menuCountEl) menuCountEl.textContent = count;
        if (bar) bar.hidden = count === 0;

        if (master) {
            master.indeterminate = count > 0 && count < total;
            master.checked = count > 0 && count === total;
        }

        document.querySelectorAll('.beneficiary-row').forEach((row) => {
            const cb = row.querySelector('.beneficiary-checkbox');
            row.classList.toggle('selected', cb && cb.checked);
        });
    };

    window.getSelectedIds = function () {
        return Array.from(document.querySelectorAll('.beneficiary-checkbox:checked')).map((cb) => cb.value);
    };

    window.bulkDelete = function () {
        const ids = getSelectedIds();
        if (!ids.length) {
            toast('Select at least one beneficiary first.', 'info');
            return;
        }

        confirmAction({
            title: 'Delete beneficiaries?',
            text: `${ids.length} record(s) will be permanently removed. Any linked distribution history will also be removed.`,
            confirmText: 'Delete',
            confirmColor: '#a96d18',
        }).then((result) => {
            if (result.isConfirmed) {
                submitHiddenForm(config.routes.bulkDelete, 'POST', { ids });
            }
        });
    };

    window.bulkStatusChange = function (status) {
        const ids = getSelectedIds();
        if (!ids.length) {
            toast('Select at least one beneficiary first.', 'info');
            return;
        }

        confirmAction({
            title: `Set selected beneficiaries ${status.toLowerCase()}?`,
            text: `${ids.length} record(s) will be updated.`,
            confirmText: `Set ${status.toLowerCase()}`,
        }).then((result) => {
            if (result.isConfirmed) {
                submitHiddenForm(config.routes.bulkStatus, 'POST', { ids, status });
            }
        });
    };

    window.deleteBeneficiary = function (id, name) {
        confirmAction({
            title: 'Delete beneficiary?',
            text: name
                ? `"${name}" and any linked distribution history will be permanently removed.`
                : 'This action cannot be undone. Any linked distribution history will also be removed.',
            confirmText: 'Delete',
            confirmColor: '#a96d18',
        }).then((result) => {
            if (result.isConfirmed) {
                submitHiddenForm(config.routes.delete.replace('__ID__', id), 'DELETE', {});
            }
        });
    };

    window.generateQr = function (id, name) {
        confirmAction({
            icon: 'question',
            title: 'Generate QR code?',
            text: `Create a QR code for ${name}.`,
            confirmText: 'Generate',
        }).then((result) => {
            if (result.isConfirmed) {
                submitHiddenForm(config.routes.generateQr.replace('__ID__', id), 'POST', {});
            }
        });
    };

    window.viewQRCode = function (btn) {
        document.getElementById('qr-modal-title').textContent = `${btn.dataset.name} - QR Code`;
        document.getElementById('qr-image').src = btn.dataset.qrUrl;
        document.getElementById('qr-modal').showModal();
    };

    window.toggleRowMenu = function (btn) {
        const menu = btn.nextElementSibling;
        const open = document.querySelector('.row-actions-menu.show');

        if (open && open !== menu) open.classList.remove('show');
        menu?.classList.toggle('show');
    };

    function resetBeneficiaryForm() {
        clearFormErrors();
        document.getElementById('modal-title').textContent = 'Add beneficiary';
        document.getElementById('modal-description').textContent = 'Create a barangay beneficiary record.';
        document.getElementById('form-method').value = 'POST';
        document.getElementById('beneficiary-form').action = config.routes.store;
        document.getElementById('beneficiary-id').value = '';
        document.getElementById('full_name').value = '';
        document.getElementById('contact_number').value = '';
        document.getElementById('address').value = '';
        document.getElementById('household_size').value = '';
        document.getElementById('priority_type').value = 'Regular';
        document.getElementById('status').value = 'Active';
        document.getElementById('submit-btn').textContent = 'Save beneficiary';
    }

    function clearFormErrors() {
        document.querySelectorAll('#beneficiary-form .field-error').forEach((el) => el.remove());
        document.querySelectorAll('#beneficiary-form .input-error').forEach((el) => el.classList.remove('input-error'));
    }

    window.openAddModal = function () {
        resetBeneficiaryForm();
        modal.showModal();
    };

    window.openEditModal = function (btn) {
        clearFormErrors();
        document.getElementById('modal-title').textContent = 'Edit beneficiary';
        document.getElementById('modal-description').textContent = 'Update beneficiary information.';
        document.getElementById('form-method').value = 'PUT';
        document.getElementById('beneficiary-form').action = config.routes.update.replace('__ID__', btn.dataset.id);
        document.getElementById('beneficiary-id').value = btn.dataset.id;
        document.getElementById('full_name').value = btn.dataset.fullName || '';
        document.getElementById('contact_number').value = btn.dataset.contact || '';
        document.getElementById('address').value = btn.dataset.address || '';
        document.getElementById('household_size').value = btn.dataset.household || '';
        document.getElementById('priority_type').value = btn.dataset.priority || 'Regular';
        document.getElementById('status').value = btn.dataset.status || 'Active';
        document.getElementById('submit-btn').textContent = 'Update beneficiary';
        modal.showModal();
    };

    window.openViewModal = function (btn) {
        const fields = [
            ['Beneficiary ID', btn.dataset.beneficiaryNo],
            ['Full name', btn.dataset.fullName],
            ['Contact', btn.dataset.contact || '—'],
            ['Address', btn.dataset.address || '—'],
            ['Household size', btn.dataset.household ? `${btn.dataset.household} members` : '—'],
            ['Priority', btn.dataset.priority],
            ['Status', btn.dataset.status],
            ['Registered', btn.dataset.created],
        ];

        const body = document.getElementById('view-modal-body');
        body.replaceChildren();
        fields.forEach(([label, value]) => {
            const row = document.createElement('div');
            row.className = 'detail-row';

            const labelElement = document.createElement('span');
            labelElement.textContent = label;

            const valueElement = document.createElement('strong');
            valueElement.textContent = value;

            row.append(labelElement, valueElement);
            body.appendChild(row);
        });

        document.getElementById('view-modal-title').textContent = btn.dataset.fullName;
        document.getElementById('view-modal-subtitle').textContent = btn.dataset.beneficiaryNo;
        document.getElementById('view-edit-btn').dataset.id = btn.dataset.id;
        document.getElementById('view-edit-btn').dataset.fullName = btn.dataset.fullName;
        document.getElementById('view-edit-btn').dataset.contact = btn.dataset.contact || '';
        document.getElementById('view-edit-btn').dataset.address = btn.dataset.address || '';
        document.getElementById('view-edit-btn').dataset.household = btn.dataset.household || '';
        document.getElementById('view-edit-btn').dataset.priority = btn.dataset.priority;
        document.getElementById('view-edit-btn').dataset.status = btn.dataset.status;

        document.getElementById('view-modal').showModal();
    };

    function formatPhoneInput(e) {
        if (e.target.value.trim().startsWith('+')) return;

        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) return;

        if (value.length > 7) {
            value = `${value.substring(0, 4)}-${value.substring(4, 7)}-${value.substring(7, 11)}`;
        } else if (value.length > 4) {
            value = `${value.substring(0, 4)}-${value.substring(4)}`;
        }

        e.target.value = value;
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (config.successMessage) toast(config.successMessage);
        if (config.errorMessage) toast(config.errorMessage, 'error');

        document.getElementById('open-add-modal')?.addEventListener('click', openAddModal);

        const initialParams = new URLSearchParams(window.location.search);
        if (initialParams.get('action') === 'add') {
            openAddModal();
            initialParams.delete('action');
            const query = initialParams.toString();
            window.history.replaceState({}, '', window.location.pathname + (query ? `?${query}` : '') + window.location.hash);
        }

        document.getElementById('view-edit-btn')?.addEventListener('click', (e) => {
            document.getElementById('view-modal').close();
            openEditModal(e.currentTarget);
        });

        document.getElementById('search-clear')?.addEventListener('click', () => {
            if (!searchInput) return;
            searchInput.value = '';
            filterForm?.submit();
        });

        document.querySelectorAll('.filter-select, #per-page-select').forEach((el) => {
            el.addEventListener('change', () => filterForm?.submit());
        });

        document.getElementById('contact_number')?.addEventListener('input', formatPhoneInput);

        document.getElementById('beneficiary-form')?.addEventListener('submit', (e) => {
            const btn = document.getElementById('submit-btn');
            if (btn.disabled) {
                e.preventDefault();
                return;
            }
            btn.disabled = true;
            btn.dataset.originalText = btn.textContent;
            btn.textContent = 'Saving...';
        });

        document.addEventListener('click', (event) => {
            const bulkBtn = document.querySelector('.bulk-actions-btn');
            const bulkMenu = document.getElementById('bulk-actions-menu');
            if (bulkMenu && bulkBtn && !bulkMenu.contains(event.target) && !bulkBtn.contains(event.target)) {
                bulkMenu.classList.remove('show');
            }

            if (!event.target.closest('.row-actions-dropdown')) {
                document.querySelectorAll('.row-actions-menu.show').forEach((menu) => menu.classList.remove('show'));
            }
        });

        document.querySelectorAll('.beneficiary-row').forEach((row) => {
            row.addEventListener('click', (e) => {
                if (e.target.closest('input, button, a, .row-actions-dropdown, .beneficiary-name-btn')) return;
                const cb = row.querySelector('.beneficiary-checkbox');
                if (cb) {
                    cb.checked = !cb.checked;
                    updateSelectedCount();
                }
            });
        });

        document.querySelectorAll('.view-beneficiary-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => e.stopPropagation());
        });

        if (config.formHasErrors && modal) {
            const beneficiaryId = config.failedBeneficiaryId;
            const isUpdate = beneficiaryId && config.failedMethod === 'PUT';

            if (isUpdate) {
                document.getElementById('modal-title').textContent = 'Edit beneficiary';
                document.getElementById('modal-description').textContent = 'Update beneficiary information.';
                document.getElementById('form-method').value = 'PUT';
                document.getElementById('beneficiary-form').action = config.routes.update.replace('__ID__', beneficiaryId);
                document.getElementById('beneficiary-id').value = beneficiaryId;
                document.getElementById('submit-btn').textContent = 'Update beneficiary';
            }

            modal.showModal();
        }
    });
})();
