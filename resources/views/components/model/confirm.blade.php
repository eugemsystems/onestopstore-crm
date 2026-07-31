<!-- confirmation modal modal -->
<div class="modal fade confirmation-modal" id="confirmation" tabindex="-1" role="dialog"
    aria-labelledby="confirmationLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-start">
                <div class="main-img">
                    <i class="ri-delete-bin-line "></i>
                </div>
                <div class="text-center">
                    <div class="modal-title"> Are you sure want to delete ?</div>
                    <p>This Item Will Be Deleted Permanently. You Can not Undo This Action.</p>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" action="{{ route('app.app.category.destroy', 1) }}">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary delete delete-btn">{{ __('Delete') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
