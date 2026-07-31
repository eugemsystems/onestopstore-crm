<div class="col-xxl-2 col-lg-4 box-col-4">
    <div class="card user-management">
        <div class="card-body bg-primary">
            <div class="blog-tags">
                <div class="tags-icon">
                    <svg class="stroke-icon">
                        <use href="{{ asset('assets/svg/icon-sprite.svg#stroke-task') }}"></use>
                    </svg>
                </div>
                <div class="tag-details">
                    <h2 class="total-num counter">{{ sprintf("%02d",totalPermissions()) }}</h2>
                    <p>Total Permissions</p>
                </div>
            </div>
        </div>
    </div>
</div>
