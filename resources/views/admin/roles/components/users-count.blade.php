<div class="col-xxl-2 col-lg-4 box-col-4">
    <div class="card user-management">
        <div class="card-body bg-primary">
            <div class="blog-tags">
                <div class="tags-icon">
                    <svg class="stroke-icon">
                        <use href="{{ asset('assets/svg/icon-sprite.svg#stroke-user') }}"></use>
                    </svg>
                </div>
                <div class="tag-details">
                    <h2 class="total-num counter">{{ sprintf("%02d",totalUsers()) }}</h2>
                    <p>Total Users</p>
                </div>
            </div>
        </div>
    </div>
</div>
