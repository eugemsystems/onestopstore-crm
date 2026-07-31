<!-- Item Status Update Modal Component -->
@props(['orderItem', 'orderId'])

<div class="modal fade" id="itemStatusModal{{ $orderItem->id }}" tabindex="-1" aria-labelledby="itemStatusModalLabel{{ $orderItem->id }}" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="itemStatusForm{{ $orderItem->id }}" data-order-id="{{ $orderId }}" data-item-id="{{ $orderItem->id }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="itemStatusModalLabel{{ $orderItem->id }}">Update Item Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Product:</strong> {{ $orderItem->name }}
                        @if($orderItem->variation_display)
                            <div class="text-muted small">{{ $orderItem->variation_display }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="itemStatus{{ $orderItem->id }}" class="form-label">Item Status</label>
                        <select class="form-select item-status-select" id="itemStatus{{ $orderItem->id }}" name="item_status" required>
                            <option value="pending" {{ $orderItem->item_status === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="processing" {{ $orderItem->item_status === 'processing' ? 'selected' : '' }}>Processing</option>
                            <option value="shipped" {{ $orderItem->item_status === 'shipped' ? 'selected' : '' }}>Shipped</option>
                            <option value="delivered" {{ $orderItem->item_status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                            <option value="cancelled" {{ $orderItem->item_status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            <option value="out_of_stock" {{ $orderItem->item_status === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                            <option value="refunded" {{ $orderItem->item_status === 'refunded' ? 'selected' : '' }}>Refunded</option>
                        </select>
                    </div>

                    <div class="mb-3 reason-field" style="display: {{ in_array($orderItem->item_status, ['cancelled', 'refunded']) ? 'block' : 'none' }};">
                        <label for="cancellationReason{{ $orderItem->id }}" class="form-label">
                            Reason <span class="text-danger">*</span>
                        </label>
                        <textarea
                            class="form-control cancellation-reason"
                            id="cancellationReason{{ $orderItem->id }}"
                            name="cancellation_reason"
                            rows="3"
                            placeholder="Please provide a reason...">{{ $orderItem->cancellation_reason }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label for="eta{{ $orderItem->id }}" class="form-label">
                            Estimated Time of Arrival (ETA)
                        </label>
                        <input
                            type="date"
                            class="form-control"
                            id="eta{{ $orderItem->id }}"
                            name="eta"
                            value="{{ $orderItem->eta ? \Carbon\Carbon::parse($orderItem->eta)->format('Y-m-d') : '' }}">
                        <small class="form-text text-muted">Optional: Set when the item is expected to arrive</small>
                    </div>

                    <div class="alert alert-danger d-none error-message" role="alert"></div>
                    <div class="alert alert-success d-none success-message" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary submit-btn">
                        <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    // TEST: Create file immediately when modal HTML loads
    fetch('/app/test-js-working', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
        },
        body: JSON.stringify({test: 'JavaScript is loading'})
    }).catch(() => {});

    const formId = 'itemStatusForm{{ $orderItem->id }}';
    const form = document.getElementById(formId);

    if (!form) {
        return;
    }

    // Check if already initialized
    if (form.dataset.initialized === 'true') {
        return;
    }
    form.dataset.initialized = 'true';

    const statusSelect = form.querySelector('.item-status-select');
    const reasonField = form.querySelector('.reason-field');
    const reasonTextarea = form.querySelector('.cancellation-reason');
    const submitBtn = form.querySelector('.submit-btn');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorAlert = form.querySelector('.error-message');
    const successAlert = form.querySelector('.success-message');

    // Show/hide reason field based on status
    statusSelect.addEventListener('change', function() {
        const needsReason = ['cancelled', 'refunded'].includes(this.value);
        reasonField.style.display = needsReason ? 'block' : 'none';
        if (needsReason) {
            reasonTextarea.setAttribute('required', 'required');
        } else {
            reasonTextarea.removeAttribute('required');
        }
    });

    // Handle form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        errorAlert.classList.add('d-none');
        successAlert.classList.add('d-none');

        const formData = new FormData(form);
        const orderId = form.dataset.orderId;
        const itemId = form.dataset.itemId;

        const payload = {
            item_status: formData.get('item_status'),
            cancellation_reason: formData.get('cancellation_reason'),
            eta: formData.get('eta')
        };
        const url = `/app/orders/${orderId}/items/${itemId}/status`;

        // Check CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

        submitBtn.disabled = true;
        spinner.classList.remove('d-none');

        try {
            const url = `/app/orders/${orderId}/items/${itemId}/status`;
            const response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                successAlert.textContent = result.message || 'Item status updated successfully';
                successAlert.classList.remove('d-none');

                // Reload page after 1 second
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                throw new Error(result.message || 'Failed to update item status');
            }
        } catch (error) {
            errorAlert.textContent = error.message;
            errorAlert.classList.remove('d-none');
        } finally {
            submitBtn.disabled = false;
            spinner.classList.add('d-none');
        }
    });

})();
</script>
