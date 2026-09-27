(function () {
    const config = window.inventoryConfig || {};
    const modal = document.getElementById('inventory-modal');
    const form = document.getElementById('inventory-form');

    function clearFormErrors() {
        form?.querySelectorAll('.field-error').forEach((element) => element.remove());
        form?.querySelectorAll('.input-error').forEach((element) => element.classList.remove('input-error'));
    }

    function resetForm() {
        clearFormErrors();
        form.reset();
        form.action = config.routes.store;
        document.getElementById('modal-title').textContent = 'Add inventory item';
        document.getElementById('modal-description').textContent = 'Enter a supply item and its stock level.';
        document.getElementById('form-method').value = 'POST';
        document.getElementById('item-id').value = '';
        document.getElementById('item_name').value = '';
        document.getElementById('category').value = '';
        document.getElementById('quantity').value = '0';
        document.getElementById('unit').value = 'pcs';
        document.getElementById('minimum_stock').value = '0';
        document.getElementById('submit-btn').textContent = 'Save item';
        document.getElementById('submit-btn').disabled = false;
    }

    function openEditModal(button) {
        clearFormErrors();
        document.getElementById('modal-title').textContent = 'Edit inventory item';
        document.getElementById('modal-description').textContent = 'Update inventory item information.';
        document.getElementById('form-method').value = 'PUT';
        form.action = config.routes.update.replace('__ID__', encodeURIComponent(button.dataset.id));
        document.getElementById('item-id').value = button.dataset.id;
        document.getElementById('item_name').value = button.dataset.name || '';
        document.getElementById('category').value = button.dataset.category || '';
        document.getElementById('quantity').value = button.dataset.quantity || '0';
        document.getElementById('unit').value = button.dataset.unit || '';
        document.getElementById('minimum_stock').value = button.dataset.minimumStock || '0';
        document.getElementById('submit-btn').textContent = 'Update item';
        document.getElementById('submit-btn').disabled = false;
        modal.showModal();
    }

    function deleteInventory(id, name) {
        const title = 'Delete inventory item?';
        const text = name
            ? `Delete "${name}"? This cannot be undone.`
            : 'This cannot be undone.';
        const confirmation = typeof Swal !== 'undefined'
            ? Swal.fire({
                icon: 'warning',
                title,
                text,
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#a96d18',
                cancelButtonColor: '#64748b',
                customClass: {
                    popup: 'modern-swal-popup',
                    title: 'modern-swal-title',
                    content: 'modern-swal-content',
                    confirmButton: 'modern-swal-confirm',
                    cancelButton: 'modern-swal-cancel',
                },
            })
            : Promise.resolve({ isConfirmed: window.confirm(`${title}\n\n${text}`) });

        confirmation.then((result) => {
            if (!result.isConfirmed) return;

            const deleteForm = document.createElement('form');
            deleteForm.method = 'POST';
            deleteForm.action = config.routes.delete.replace('__ID__', encodeURIComponent(id));

            [
                ['_token', config.csrf],
                ['_method', 'DELETE'],
            ].forEach(([fieldName, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = fieldName;
                input.value = value;
                deleteForm.appendChild(input);
            });

            document.body.appendChild(deleteForm);
            deleteForm.submit();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('open-add-inventory')?.addEventListener('click', () => {
            resetForm();
            modal.showModal();
        });

        document.addEventListener('click', (event) => {
            const editButton = event.target.closest('.edit-inventory-btn');
            if (editButton) {
                openEditModal(editButton);
                return;
            }

            const deleteButton = event.target.closest('.delete-inventory-btn');
            if (deleteButton) {
                deleteInventory(deleteButton.dataset.id, deleteButton.dataset.name);
            }
        });

        form?.addEventListener('submit', (event) => {
            const submitButton = document.getElementById('submit-btn');
            if (submitButton.disabled) {
                event.preventDefault();
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
        });

        if (config.formHasErrors && modal) {
            const itemId = config.failedItemId;
            const isUpdate = itemId && config.failedMethod === 'PUT';

            if (isUpdate) {
                document.getElementById('modal-title').textContent = 'Edit inventory item';
                document.getElementById('modal-description').textContent = 'Update inventory item information.';
                document.getElementById('form-method').value = 'PUT';
                form.action = config.routes.update.replace('__ID__', encodeURIComponent(itemId));
                document.getElementById('item-id').value = itemId;
                document.getElementById('submit-btn').textContent = 'Update item';
            }

            modal.showModal();
        }
    });
})();
