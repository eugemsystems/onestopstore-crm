<?php

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderItemMessage;
use App\Models\OrderItemMessageLike;
use App\Models\OrderItemMessageView;
use App\Models\OrderItemMessageMention;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Helpers\OrderStatusColors;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $paginationTheme = 'bootstrap';

    // Filters
    public ?string $etaFrom = null;
    public ?string $etaTo = null;
    public array|string $statusFilter = [];
    public string $orderFilter = '';
    public string $productFilter = '';
    public string $destQuery = '';
    public string $assignedToFilter = '';

    public array $selected = [];
    public ?string $etaBulk = null;
    public ?string $statusBulk = null;
    public ?int $assignedToBulk = null;
    public array $statuses = [];

    // Quick-edit (popover)
    public ?int $etaEditId = null;
    public ?string $etaEditTemp = null;
    public ?int $statusEditId = null;
    public ?string $statusEditTemp = null;
    public ?int $assignedToEdit = null;  // API user ID of selected Staff Raines member
    public array $staffRainesList = [];  // [{id, name, email}] fetched from API

    // Chat
    public bool $chatOpen = false;
    public ?int $chatItemId = null;
    public array $chatMessages = [];
    public array $chatParticipants = [];
    public string $chatBody = '';
    public array $chatMentionUserIds = [];
    public array $chatViews = [];
    public array $userSuggestions = [];
    public array $colors = [];
    public $defaultVariant = 'link';
    public array $list = [];

    // Permissions
    public bool $canEditOrderItems = false;
    public bool $canEditOrders = false;
    public bool $canTransferToInventory = false;

    // Sorting
    public string $sortBy = 'date'; // order_number | order_status | date
    public string $sortDir = 'desc'; // asc | desc

    // Reply state
    public ?int $chatReplyTo = null;

    public bool $stale = false;

    public function mount($stale=false): void
    {
        $this->stale = $stale;
        $this->statuses = collect(array_merge(
            App\Enums\OrderItemStatusEnums::names(),
            cachedOrderStatuses()->toArray()
        ))->map(fn ($ar) => (string) Str::of($ar)
            ->replaceMatches('/([a-z])([A-Z])/', '$1 $2')
            ->lower()
        )->push('collected')->push('refunded')->push('arrived at local branch')->unique()->sort()->values()->toArray();

        $this->colors = [
            'cancelled'             => 'danger',
            'delivered'             => 'success',
            'collected'             => 'success',
            'dropped at the deport' => 'secondary',
            'from supplier'         => 'info',
            'in transit to zim'     => 'primary',
            'out for delivery'      => 'warning',
            'pending'               => 'warning',
            'processing'            => 'info',
            'ready for collection'  => 'success',
            'shipped'               => 'primary',
            'stuck'                 => 'danger',
            'warehouse packing'     => 'secondary',
            'refunded'              => 'danger',
            'arrived at local branch' => 'info',
        ];

        $this->list = $this->statuses ?? [];

        // Resolve permissions once per mount
        $u = auth()->user();
        $this->canEditOrderItems = (bool) ($u?->can('update order items') || $u?->hasRole('super-admin'));
        $this->canEditOrders = (bool) ($u?->can('update order') || $u?->hasRole('super-admin'));
        $this->canTransferToInventory = (bool) ($u?->can('transfer to inventory') || $u?->hasRole('super-admin'));

        // Fetch Staff Raines list from Laravel API for assignment dropdown
        try {
            $res = \Illuminate\Support\Facades\Http::baseUrl(config('services.api.base_url'))
                ->withToken(config('services.api.token'))
                ->acceptJson()
                ->timeout(5)
                ->get('/api/inventory-shipments/staff-raines');
            if ($res->successful()) {
                $this->staffRainesList = $res->json('data', []);
            }
        } catch (\Throwable $e) {
            $this->staffRainesList = [];
        }
    }

    protected function baseQuery()
    {
        $uid = Auth::id();

        $sub = OrderItemMessage::query()
            ->selectRaw('order_product_id, COUNT(*) as cnt')
            ->join('order_item_message_mentions as m','m.message_id','=','order_item_messages.id')
            ->leftJoin('order_item_message_views as v', function($j) use($uid) {
                $j->on('v.message_id','=','order_item_messages.id')->where('v.user_id','=',$uid);
            })
            ->where('m.mentioned_user_id', $uid)
            ->whereNull('v.id')
            ->groupBy('order_product_id');

        $q = OrderProduct::query()
            ->with('order')
            ->leftJoin('orders','orders.id','=','order_products.order_id')
            ->leftJoinSub($sub, 'unread', 'unread.order_product_id','=','order_products.id')
            ->select('order_products.*')
            ->addSelect(\DB::raw('COALESCE(unread.cnt,0) as unread_cnt'));

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('id', 'like', $term)
                    ->orWhereHas('order', fn($oq) => $oq
                        ->where('order_number', 'like', $term)
                        ->orWhere('id', 'like', $term));
            });
        }

        if (!empty($this->etaFrom)) {
            $q->whereDate('order_products.eta', '>=', $this->etaFrom);
        }
        if (!empty($this->etaTo)) {
            $q->whereDate('order_products.eta', '<=', $this->etaTo);
        }

        $sf = $this->statusFilter;
        if (is_string($sf)) { $sf = [$sf]; }
        $sf = array_values(array_filter(array_map('strval', (array)$sf)));
        $sfNorm = array_map(fn($s) => strtolower(str_replace([' ', '_', '-'], '', $s)), $sf);
        if (!empty($sfNorm)) {
            $q->whereIn(\DB::raw("REPLACE(REPLACE(REPLACE(LOWER(order_products.status), ' ', ''), '_', ''), '-', '')"), $sfNorm);
        }

        $of = trim((string)$this->orderFilter);
        if ($of !== '') {
            $q->whereHas('order', function($oq) use ($of) {
                $oq->where('order_number', 'like', '%'.$of.'%')
                    ->orWhere('id', 'like', '%'.$of.'%');
            });
        }

        $pf = trim((string)$this->productFilter);
        if ($pf !== '') {
            $q->where(function($w) use ($pf) {
                $w->where('name', 'like', '%'.$pf.'%')
                    ->orWhere('sku', 'like', '%'.$pf.'%');
            });
        }

        $dq = trim((string)$this->destQuery);
        if ($dq !== '') {
            $q->whereHas('order', function($oq) use ($dq) {
                $oq->where('shipping_address->city', 'like', '%'.$dq.'%')
                    ->orWhere('shipping_address->country->name', 'like', '%'.$dq.'%');
            });
        }

        if ($this->assignedToFilter !== '') {
            if ($this->assignedToFilter === '__unassigned__') {
                $q->whereNull('assigned_to');
            } else {
                $q->where('assigned_to', (int) $this->assignedToFilter);
            }
        }

        if (!auth()->user()?->hasRole('super-admin')) {
            $uid = Auth::id();
            $q->where(function($wrap) use ($uid) {
                $wrap->whereExists(function($sub) use ($uid) {
                    $sub->selectRaw('1')
                        ->from('order_item_messages as oim')
                        ->whereColumn('oim.order_product_id', 'order_products.id')
                        ->where('oim.user_id', $uid);
                })->orWhereExists(function($sub) use ($uid) {
                    $sub->selectRaw('1')
                        ->from('order_item_messages as oim2')
                        ->join('order_item_message_mentions as omm', 'omm.message_id', '=', 'oim2.id')
                        ->whereColumn('oim2.order_product_id', 'order_products.id')
                        ->where('omm.mentioned_user_id', $uid);
                });
            });
        }

        // Sorting
        $by = $this->sortBy ?? 'date';
        $dir = strtolower($this->sortDir ?? 'desc') === 'asc' ? 'asc' : 'desc';
        if ($by === 'order_number') {
            $q->orderBy('orders.order_number', $dir);
        } elseif ($by === 'order_status') {
            $q->orderBy('orders.order_status', $dir);
        } else { // date
            $q->orderBy('order_products.created_at', $dir);
        }
        // Secondary: unread count desc for tie-breaker
        $q->orderByDesc(\DB::raw('COALESCE(unread.cnt,0)'));

        if($this->stale){
            return $q->where('order_products.updated_at', '<=', now()->subDays(3));
        }
        return $q;
    }

    public function setSort(string $by): void
    {
        $allowed = ['order_number','order_status','date'];
        if (!in_array($by, $allowed, true)) return;
        if ($this->sortBy === $by) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $by;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingEtaFrom(): void { $this->resetPage(); }
    public function updatingEtaTo(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingOrderFilter(): void { $this->resetPage(); }
    public function updatingProductFilter(): void { $this->resetPage(); }
    public function updatingDestQuery(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->etaFrom = null;
        $this->etaTo = null;
        $this->statusFilter = [];
        $this->orderFilter = '';
        $this->productFilter = '';
        $this->destQuery = '';
        $this->assignedToFilter = '';
        $this->resetPage();
    }

    public function with(): array
    {
        $items = $this->baseQuery()->paginate(20);
        $pageIds = $items->getCollection()->pluck('id')->values()->all();
        $onPageSelectedCount = collect($pageIds)
            ->intersect(collect($this->selected)->map(fn($v)=>(int)$v))
            ->count();
        $allOnPage = count($pageIds) > 0 && $onPageSelectedCount === count($pageIds);

        return [
            'items'     => $items,
            'FE'        => env('FRONTEND_URL'),
            'pageIds'   => $pageIds,
            'allOnPage' => $allOnPage,
        ];
    }

    public function toggleSelectPage(array $pageIds): void
    {
        $pageIds = array_map('intval', $pageIds);
        $cur = collect($this->selected)->map(fn($v)=>(int)$v);
        $allOnPageSelected = collect($pageIds)->every(fn($id) => $cur->contains($id));

        $this->selected = $allOnPageSelected
            ? $cur->reject(fn($id) => in_array($id, $pageIds))->values()->all()
            : $cur->merge($pageIds)->unique()->values()->all();
    }

    // Quick-save popover: combined ETA + Status + Assigned To
    public function saveItemMeta(int $id, ?string $eta, ?string $status, ?int $assignedTo = null): void
    {
        if (!(auth()->user()?->can('edit order items') || ($this->canEditOrderItems ?? false))) { $this->dispatch('error','You do not have permission to edit items.'); return; }
        try {
            // Load item with order relationship
            $item = OrderProduct::with('order')->find($id);
            if (!$item) { $this->dispatch('error','Item not found'); return; }

            $beforeEta = $item->eta ? ($item->eta instanceof \Carbon\Carbon ? $item->eta->format('Y-m-d') : (string)$item->eta) : null;
            $beforeStatus = $item->item_status ? (string)$item->item_status : null;

            $etaNorm = ($eta !== null && trim($eta) !== '') ? trim($eta) : null;
            $statusNorm = ($status !== null && trim($status) !== '') ? trim($status) : null;

            $changes = [];
            if ($eta !== null && $etaNorm !== $beforeEta) {
                $changes['eta'] = $etaNorm;
            }
            if ($status !== null && $statusNorm !== $beforeStatus) {
                $changes['item_status'] = $statusNorm;
            }
            if ($assignedTo !== null) {
                $changes['assigned_to'] = $assignedTo;
            }

            if (empty($changes)) { $this->dispatch('success','No changes to save.'); return; }

            $item->update($changes);

            // Sync to Laravel API if status or ETA was changed
            // Use array_key_exists to detect changes even when value is null
            if (array_key_exists('item_status', $changes) || array_key_exists('eta', $changes)) {
                try {
                    $ordersApi = app(\App\Services\OrdersApi::class);

                    // Get the current values after update to ensure we send the full state
                    $fresh = $item->fresh();

                    // Use external order_number; skip remote sync if missing
                    if (empty($item->order?->order_number)) {
                        \Log::warning('Skipping OrdersApi.updateItemStatus: order_number missing', ['order_id' => $item->order_id, 'item_id' => $item->id]);
                    } else {
                        // Prepare the data to send - always include eta key when eta was changed
                        // Get the current item status - normalize it for API
                        $currentStatus = $fresh->item_status ?? $statusNorm ?? 'pending';

                        // Normalize status to lowercase with spaces (API format)
                        $normalizedStatus = strtolower(trim((string)$currentStatus));

                        $apiData = [
                            'product_id' => $item->product_id,
                            'variation_id' => $item->variation_id,
                            'item_status' => $normalizedStatus,
                        ];

                        // Always include eta key if it was in the changes (even if null)
                        if (array_key_exists('eta', $changes)) {
                            $apiData['eta'] = $fresh->eta ? ($fresh->eta instanceof \Carbon\Carbon ? $fresh->eta->format('Y-m-d') : $fresh->eta) : null;
                        }

                        $ordersApi->updateItemStatus((string)$item->order->order_number, $apiData, auth()->user()?->email);

                    }
                } catch (\Throwable $e) {
                    \Log::error('Failed to sync item status/ETA to API', [
                        'item_id' => $item->id,
                        'order_id' => $item->order_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Don't fail the whole operation if API sync fails
                }
            }

            // If assigned_to changed and item was already transferred, push signed_by update to API shipment
            if (array_key_exists('assigned_to', $changes) && $item->inventory_transferred_at) {
                try {
                    $ordersApi = app(\App\Services\OrdersApi::class);
                    $baseUrl   = config('services.api.base_url');
                    $orderNum  = (string) ($item->order?->order_number ?? $item->order_id);
                    $resp = \Illuminate\Support\Facades\Http::baseUrl($baseUrl)
                        ->withToken(config('services.api.token'))
                        ->acceptJson()
                        ->timeout(10)
                        ->get('/api/inventory-shipments', ['order' => $orderNum, 'per_page' => 50]);
                    if ($resp->successful()) {
                        $payload   = $resp->json();
                        $shipments = $payload['data']['data'] ?? $payload['data'] ?? $payload;
                        if (is_array($shipments)) {
                            $match = collect($shipments)
                                ->filter(fn($s) => isset($s['order']) && (string)$s['order'] === $orderNum)
                                ->sortByDesc('id')
                                ->first();
                            if ($match && isset($match['id'])) {
                                $ordersApi->updateShipmentSignedBy((int)$match['id'], $item->assigned_to);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::error('Failed to sync signed_by to API shipment', [
                        'item_id' => $item->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            // Decide kind; if resulting item has both status and ETA, coerce to 'both'
            // Use array_key_exists instead of isset because isset returns false for null values
            $kind = (array_key_exists('eta', $changes) && array_key_exists('item_status', $changes))
                ? 'both'
                : (array_key_exists('eta', $changes) ? 'eta' : 'status');

            try {
                $fresh = $item->fresh();
                $hasEta = !empty($fresh->eta);
                $hasStatus = ($fresh->item_status !== null && trim((string)$fresh->item_status) !== '');
                if ($hasEta && $hasStatus) { $kind = 'both'; }
            } catch (\Throwable $e) {}

            $order = $item->order;
            // Suppress notifications for internal-only statuses
            $shouldNotify = true;
            try {
                if (array_key_exists('item_status', $changes)) {
                    $stLower = strtolower(trim((string)($changes['item_status'] ?? '')));
                    // Internal-only statuses: shipped, refunded, arrived at local branch
                    if (in_array($stLower, ['shipped', 'refunded', 'arrived at local branch'])) {
                        $shouldNotify = false;
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }


            // CRM sends its own specialized notifications (ETA-specific, Status-specific)
            // This is DIFFERENT from API's generic order status emails
            if ($order && $shouldNotify) {
                try {
                    $this->notifyCustomer($order, new \App\Notifications\OrderItemsUpdatedNotification($order, collect([$fresh ?? $item->fresh()]), $kind));
                } catch (\Throwable $e) {
                    logger()->warning('item meta notify failed', ['err'=>$e->getMessage()]);
                }
            }

            $this->dispatch('success','Item updated.');
        }
        catch (\Throwable $e) { $this->dispatch('error', $e->getMessage()); }
    }

    // Chat open/close
    public function openChat(int $itemId): void
    {
        $this->chatItemId = $itemId;
        $this->chatOpen = true;
        $this->chatBody = '';
        $this->chatMentionUserIds = [];
        $this->chatReplyTo = null;
        $this->loadChat();
    }

    public function closeChat(): void
    {
        $this->chatOpen = false;
        $this->chatItemId = null;
        $this->chatMessages = [];
        $this->chatParticipants = [];
        $this->chatViews = [];
        $this->chatBody = '';
        $this->chatMentionUserIds = [];
        $this->chatReplyTo = null;
    }

    protected function loadChat(): void
    {
        if (!$this->chatItemId) return;

        $msgs = OrderItemMessage::with([
            'user:id,first_name,last_name',
            'likes',
            'views.user:id,first_name,last_name'
        ])->where('order_product_id', $this->chatItemId)
            ->orderBy('created_at','asc')
            ->get();

        $this->chatMessages = $msgs->map(fn($m) => [
            'id'         => $m->id,
            'user'       => [
                'id'   => $m->user_id,
                'name' => $m->user->full_name ?? trim(($m->user->first_name ?? '').' '.($m->user->last_name ?? '')) ?: 'User',
            ],
            'body'       => $this->highlightMentions($m->body),
            'raw_body'   => (string)$m->body,      // keep original
            'parent_id'  => $m->parent_id ?? null, // for indenting/threading
            'created_at' => $m->created_at?->format('Y-m-d H:i'),
            'likes'      => $m->likes->pluck('user_id')->all(),
        ])->all();

        $this->chatViews = $msgs->mapWithKeys(function($m) {
            return [
                $m->id => $m->views->map(fn($v) => [
                    'id'   => $v->user_id,
                    'name' => $v->user->full_name ?? trim(($v->user->first_name ?? '').' '.($v->user->last_name ?? '')) ?: 'User',
                ])->all(),
            ];
        })->all();

        $uids = $msgs->pluck('user_id')->unique()->values()->all();
        $users = $uids ? User::whereIn('id',$uids)->get(['id','first_name','last_name']) : collect();
        $this->chatParticipants = $users->map(fn($u)=>['id'=>$u->id,'name'=>$u->full_name])->values()->all();
    }

    // Reply controls
    public function replyTo(int $messageId): void
    {
        $this->chatReplyTo = $messageId;

        try {
            $parent = OrderItemMessage::with('user:id,first_name,last_name')->find($messageId);
            if ($parent && (int)$parent->user_id !== (int)Auth::id()) {
                $this->chatMentionUserIds = [(int)$parent->user_id];
                $name = trim(($parent->user->first_name ?? '') . ' ' . ($parent->user->last_name ?? ''));
                if ($name && !str_contains($this->chatBody, '@'.$name)) {
                    $this->chatBody = '@' . $name . ' ' . $this->chatBody;
                }
            }
        } catch (\Throwable $e) {}
    }

    public function cancelReply(): void
    {
        $this->chatReplyTo = null;
        $this->chatMentionUserIds = [];
    }

    public function sendChat(): void
    {
        $body = trim($this->chatBody);
        if (!$this->chatItemId || $body === '') return;

        // Enforce @mention for NEW messages (non-replies)
        $hasMention = (bool) preg_match('/@(\w[\w\s\.\-]{1,50})/u', $body);
        if (!$hasMention && !$this->chatReplyTo) {
            $this->dispatch('error','Please mention a user with @ to send a new message. Use Reply to respond without mentions.');
            return;
        }

        try {
            $msg = new OrderItemMessage();
            $msg->order_product_id = $this->chatItemId;
            $msg->user_id = Auth::id();
            $msg->body = $body;
            $msg->parent_id = $this->chatReplyTo; // link reply
            $msg->save();

            // mark author viewed
            OrderItemMessageView::updateOrCreate(
                ['message_id'=>$msg->id,'user_id'=>Auth::id()],
                ['viewed_at'=>now()]
            );

            // Build recipients: explicit UI IDs
            $ids = collect($this->chatMentionUserIds ?? [])
                ->filter(fn($v) => is_numeric($v))
                ->map(fn($v) => (int)$v);

            // Auto-mention parent author on reply (if not already)
            if ($this->chatReplyTo && $ids->isEmpty()) {
                $parent = OrderItemMessage::find($this->chatReplyTo);
                if ($parent && (int)$parent->user_id !== (int)Auth::id()) {
                    $ids = $ids->merge([(int)$parent->user_id]);
                }
            }

            // Fallback: parse @Name from body (exact "First Last")
            preg_match_all('/@(\w[\w\s\.\-]{1,50})/u', $body, $m);
            if (!empty($m[1])) {
                $namesLower = collect($m[1])->map(fn($n)=>mb_strtolower(preg_replace('/\s+/u',' ',trim($n))));
                if ($namesLower->isNotEmpty()) {
                    $fallbackIds = User::where(function($q) use ($namesLower) {
                        foreach ($namesLower as $n) {
                            $q->orWhereRaw("LOWER(CONCAT(TRIM(first_name), ' ', TRIM(last_name))) = ?", [$n]);
                        }
                    })->pluck('id');
                    $ids = $ids->merge($fallbackIds);
                }
            }

            $ids = $ids->unique()->reject(fn($id) => (int)$id === (int)Auth::id())->values();

            if ($ids->isNotEmpty()) {
                $users = User::whereIn('id',$ids)->get(['id','first_name','last_name']);
                foreach ($users as $u) {
                    OrderItemMessageMention::firstOrCreate([
                        'message_id'=>$msg->id,
                        'mentioned_user_id'=>$u->id
                    ]);
                    try {
                        $u->notify(new \App\Notifications\NewOrderItemMessageNotification($msg));
                    } catch (\Throwable $e) {
                        logger()->warning('notify failed', ['err' => $e->getMessage()]);
                    }
                }
            }

            // reset composer
            $this->chatMentionUserIds = [];
            $this->chatReplyTo = null;
            $this->chatBody = '';
            $this->loadChat();
        } catch (\Throwable $e) {
            logger()->error('sendChat failed', ['err'=>$e->getMessage()]);
        }
    }

    public function searchUsers(string $term = ''): void
    {
        $q = trim($term);
        if ($q === '') { $this->userSuggestions = []; return; }
        $this->userSuggestions = User::where(function($qq) use ($q) {
            $qq->where('first_name','like','%'.$q.'%')
                ->orWhere('last_name','like','%'.$q.'%')
                ->orWhere('email','like','%'.$q.'%');
        })
            ->limit(8)
            ->get(['id','first_name','last_name'])
            ->map(fn($u)=>['id'=>$u->id,'name'=>$u->full_name])
            ->all();
    }

    public function toggleLike(int $messageId): void
    {
        $uid = Auth::id(); if (!$uid) return;
        $like = OrderItemMessageLike::where('message_id',$messageId)->where('user_id',$uid)->first();
        if ($like) $like->delete(); else OrderItemMessageLike::create(['message_id'=>$messageId,'user_id'=>$uid]);
        $this->loadChat();
    }

    public function markViewed(int $messageId): void
    {
        $uid = Auth::id(); if (!$uid) return;
        OrderItemMessageView::updateOrCreate(['message_id'=>$messageId,'user_id'=>$uid], ['viewed_at'=>now()]);
        $this->loadChat();
    }

    // helpers
    protected function highlightMentions($text): string
    {
        try {
            $safe = e((string)$text);
            // Highlight only the first two words after @ (first name and surname)
            return preg_replace_callback(
                "/@([\p{L}][\p{L}\.'\'-]*)(?:\s+([\p{L}][\p{L}\.'\'-]*))?/u",
                function ($m) {
                    $first = $m[1] ?? '';
                    $second = $m[2] ?? '';
                    $name = trim($first . ($second ? ' ' . $second : ''));
                    return '<span class="mention">@' . $name . '</span>';
                },
                $safe
            ) ?? $safe;
        } catch (\Throwable) { return e((string)$text); }
    }

    protected function getOrderEmail($order): ?string
    {
        try {
            $email = is_string($order->consumer_email ?? null) ? trim($order->consumer_email) : null;
            return $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        } catch (\Throwable) { return null; }
    }

    protected function buildEtaEmailHtml(Order $order, $items): string
    {
        $orderNo = $order->order_number ?? $order->id;
        $rows = collect($items)->map(function($it){
            $name = $it->name ?? ('SKU: '.$it->sku);
            $eta  = $it->eta ? $it->eta->format('Y-m-d') : '-';
            $sku  = $it->sku ?? '-';
            return "<tr><td style='padding:6px 8px;border:1px solid #ddd;'>".e($name)."</td><td style='padding:6px 8px;border:1px solid #ddd;'>".e($sku)."</td><td style='padding:6px 8px;border:1px solid #ddd;'>".e($eta)."</td></tr>";
        })->implode('');
        $etaGroups = collect($items)->map(fn($it)=>$it->eta? $it->eta->format('Y-m-d'):'-')->unique()->values();
        $splitNote = $etaGroups->count() > 1 ? "<p style='color:#0d6efd;'>Please note: To expedite delivery, your order has been split into multiple shipments. Items with different estimated arrival dates will be delivered separately. You will receive updates for each shipment as they progress.</p>" : '';
        $addr = $this->destinationCityCountry($order);
        return "<div>
            <h2>ETA updated for Order #".e($orderNo)."</h2>
            <p>We've updated the estimated arrival date(s) for your item(s) in Order #".e($orderNo).".</p>
            $splitNote
            <table cellspacing='0' cellpadding='0' style='border-collapse:collapse;border:1px solid #ddd;'>
                <thead><tr><th style='text-align:left;padding:6px 8px;border:1px solid #ddd;'>Product</th><th style='text-align:left;padding:6px 8px;border:1px solid #ddd;'>SKU</th><th style='text-align:left;padding:6px 8px;border:1px solid #ddd;'>ETA</th></tr></thead>
                <tbody>$rows</tbody>
            </table>
            <p><strong>Destination:</strong> ".e($addr)."</p>
        </div>";
    }

    protected function buildStatusEmailHtml(Order $order, $items): string
    {
        $orderNo = $order->order_number ?? $order->id;
        $rows = collect($items)->map(function($it){
            $name = $it->name ?? ('SKU: '.$it->sku);
            $status  = $it->status ? Str::title($it->status) : '-';
            $sku  = $it->sku ?? '-';
            return "<tr><td style='padding:6px 8px;border:1px solid #ddd;'>".e($name)."</td><td style='padding:6px 8px;border:1px solid #ddd;'>".e($sku)."</td><td style='padding:6px 8px;border:1px solid #ddd;'>".e($status)."</td></tr>";
        })->implode('');
        $addr = $this->destinationCityCountry($order);
        return "<div>
            <h2>Status update for Order #".e($orderNo)."</h2>
            <p>We've updated the status of the following item(s) on your order.</p>
            <table cellspacing='0' cellpadding='0' style='border-collapse:collapse;border:1px solid #ddd;'>
                <thead><tr><th style='text-align:left;padding:6px 8px;border:1px solid #ddd;'>Product</th><th style='text-align:left;padding:6px 8px;border:1px solid #ddd;'>SKU</th><th style='text-align:left;padding:6px 8px;border:1px solid #ddd;'>Status</th></tr></thead>
                <tbody>$rows</tbody>
            </table>
            <p><strong>Destination:</strong> ".e($addr)."</p>
        </div>";
    }

    protected function notifyCustomer(Order $order, \Illuminate\Notifications\Notification $notification): void
    {
        // Check send_emails setting - default to TRUE if not set
        $sendEmailsSetting = getCachedSetting('send_emails');
        $sendEmails = ($sendEmailsSetting === null || $sendEmailsSetting === true || $sendEmailsSetting === '1' || $sendEmailsSetting === 1);

        // Only skip if explicitly disabled (false or '0')
        if (!$sendEmails) {
            return;
        }

        $email = $this->getOrderEmail($order);


        if (!$email) {
            return;
        }

        $user = User::where('email', $email)->first();

        try {
            if ($user) {
                // Will include database + mail if enabled by the notification via()
                $user->notify($notification);
            } else {
                // Fallback to email only for non-registered consumers
                Notification::route('mail', $email)->notify($notification);
            }

        } catch (\Throwable $e) {
            \Log::error('notifyCustomer failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function destinationCityCountry($order): string
    {
        try {
            $addr = $order->shipping_address ?? null;
            if (is_array($addr)) {
                $city = (string)($addr['city'] ?? '');
                $country = $addr['country'] ?? '';
                if (is_array($country)) $country = (string)($country['name'] ?? '');
                $country = (string)$country;
                $parts = array_filter([$city, $country], fn($v)=>trim((string)$v) !== '');
                return $parts ? implode(', ', $parts) : '-';
            }
            return '-';
        } catch (\Throwable) { return '-'; }
    }

    public function orderStatusName($order): ?string
    {
        try {
            $s = $order->status ?? $order->order_status ?? $order->status_text ?? null;
            if (is_array($s))  return $s['name'] ?? null;
            if (is_object($s)) return $s->name ?? (string)$s;
            return $s ? (string)$s : null;
        } catch (\Throwable) { return null; }
    }

    // bulk
    public function bulkApply(): void
    {
        $ids = collect($this->selected)->filter(fn($v) => is_numeric($v))->map(fn($v) => (int)$v)->values()->all();
        $eta = $this->etaBulk ? trim($this->etaBulk) : null;
        $st  = $this->statusBulk ? trim($this->statusBulk) : null;
        $asgn = $this->assignedToBulk ?: null;
        if (!$ids || (!$eta && !$st && !$asgn)) return;

        // Validate that all selected items belong to the same order
        $items = OrderProduct::whereIn('id', $ids)->with('order')->get();
        $uniqueOrderIds = $items->pluck('order_id')->unique();

        if ($uniqueOrderIds->count() > 1) {
            $this->dispatch('error', 'Bulk update can only be applied to items from the same order. Please select items from a single order only.');
            return;
        }

        $itemsByOrder = $items->groupBy('order_id');
        try {
            foreach ($itemsByOrder as $orderId => $group) {
                $order = optional($group->first())->order; if (!$order) continue;
                $groupIds = $group->pluck('id')->values()->all();
                $update = [];
                if ($eta)  $update['eta'] = $eta;
                if ($st)   $update['item_status'] = $st;
                if ($asgn) $update['assigned_to'] = $asgn;
                if (empty($update)) continue;

                OrderProduct::whereIn('id', $groupIds)->update($update);
                $updated = OrderProduct::whereIn('id', $groupIds)->get();

                // Sync each updated item to Laravel API
                if (!empty($order->order_number)) {
                    try {
                        $ordersApi = app(\App\Services\OrdersApi::class);

                        foreach ($updated as $item) {
                            try {
                                // Normalize status to lowercase
                                $itemStatus = strtolower(trim((string)($item->item_status ?? 'pending')));

                                $ordersApi->updateItemStatus((string)$order->order_number, [
                                    'product_id' => $item->product_id,
                                    'variation_id' => $item->variation_id,
                                    'item_status' => $itemStatus,
                                    'eta' => $item->eta ? ($item->eta instanceof \Carbon\Carbon ? $item->eta->format('Y-m-d') : $item->eta) : null,
                                ], auth()->user()?->email);
                            } catch (\Throwable $e) {
                                \Log::error('Failed to sync bulk item to API', [
                                    'item_id' => $item->id,
                                    'order_id' => $order->id,
                                    'order_number' => $order->order_number,
                                    'error' => $e->getMessage(),
                                ]);
                                // Continue with other items even if one fails
                            }
                        }
                    } catch (\Throwable $e) {
                        \Log::error('Failed to sync bulk items to API', [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'error' => $e->getMessage(),
                        ]);
                        // Don't fail the whole operation if API sync fails
                    }
                } else {
                    \Log::warning('Skipping bulk API sync: order_number missing', ['order_id' => $order->id]);
                }

                // Determine kind based on resulting items (not just inputs)
                $hasAnyEta = $updated->contains(function($it){ return !empty($it->eta); });
                $hasAnyStatus = $updated->contains(function($it){ return ($it->item_status !== null && trim((string)$it->item_status) !== ''); });
                $kind = ($hasAnyEta && $hasAnyStatus) ? 'both' : ($hasAnyEta ? 'eta' : 'status');

                // Skip sending emails for internal-only statuses
                $skipNotify = false;
                try {
                    if (!empty($st)) {
                        $stLower = strtolower(trim((string)$st));
                        // Internal-only statuses: shipped, refunded, arrived at local branch
                        if (in_array($stLower, ['shipped', 'refunded', 'arrived at local branch'])) {
                            $skipNotify = true;
                        }
                    }
                } catch (\Throwable $e) { /* ignore */ }

                if (!$skipNotify) {
                    try {
                        $this->notifyCustomer($order, new \App\Notifications\OrderItemsUpdatedNotification($order, $updated, $kind));
                    } catch (\Throwable $e) { logger()->warning('bulk apply notify failed', ['err'=>$e->getMessage()]); }
                }
            }

            // Reset page to trigger Livewire re-render with fresh data
            $this->resetPage();

            $this->dispatch('success', 'Updated '.count($ids).' item(s).');
        }
        catch (\Throwable $e) { $this->dispatch('error', $e->getMessage()); return; }
        finally { $this->etaBulk = null; $this->statusBulk = null; $this->selected = []; }
    }

    /**
     * Build a Takealot multi-product search URL from selected items' SKUs
     * and dispatch a browser event to open it in a new tab.
     * URL format: https://www.takealot.com/all?filter=Id:SKU1|SKU2|SKU3
     */
    public function openOnTakealot(): void
    {
        $ids = collect($this->selected)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int)$v)
            ->values()
            ->all();

        if (empty($ids)) {
            $this->dispatch('error', 'No items selected.');
            return;
        }

        $skus = OrderProduct::whereIn('id', $ids)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->unique()
            ->filter(fn($sku) => ctype_digit((string) $sku))  // numeric SKUs only
            ->values()
            ->all();

        if (empty($skus)) {
            $this->dispatch('error', 'None of the selected items have a SKU.');
            return;
        }

        $url = 'https://www.takealot.com/all?filter=Id:' . implode('|', $skus);

        $this->dispatch('open-takealot', url: $url);
    }

    /**
     * Transfer selected order items to inventory shipments via queued job.
     * Requires 'transfer to inventory' permission.
     */
    public function transferToInventory(): void
    {
        if (!$this->canTransferToInventory) {
            $this->dispatch('error', 'You do not have permission to transfer items to inventory.');
            return;
        }

        $ids = collect($this->selected)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int)$v)
            ->values()
            ->all();

        if (empty($ids)) {
            $this->dispatch('error', 'No items selected.');
            return;
        }

        $items = OrderProduct::whereIn('id', $ids)->with('order')->get();
        $dispatched = 0;
        $skipped    = 0;
        $failures   = [];

        foreach ($items as $item) {
            // Skip items already transferred
            if ($item->inventory_transferred_at) {
                $skipped++;
                continue;
            }

            try {
                // Build destination: Zambian cities → "Zambia", all others → city name
                $addr = $item->order?->shipping_address;
                $destination = null;
                if (is_array($addr)) {
                    $city = trim((string)($addr['city'] ?? ''));
                    if ($city !== '') {
                        $zambianCities = ['lusaka','ndola','kitwe','livingstone','chipata','kabwe','solwezi','chingola','mufulira','luanshya','kasama','mongu','mazabuka','choma','kafue'];
                        $destination = in_array(strtolower($city), $zambianCities) ? 'Zambia' : $city;
                    }
                }

                $payload = [
                    'order'       => (string) ($item->order?->order_number ?? $item->order_id),
                    'title'       => $item->name ?? ('SKU: ' . $item->sku),
                    'quantity'    => $item->quantity ?? 1,
                    'sku'         => $item->sku ?? null,
                    'destination' => $destination,
                    'eta'         => $item->eta
                        ? ($item->eta instanceof \Carbon\Carbon ? $item->eta->format('Y-m-d') : $item->eta)
                        : null,
                    'signed_by'   => $item->assigned_to ?? null,
                ];

                // Dispatch queued job
                \App\Jobs\TransferItemToInventoryJob::dispatch($payload, $item->id);

                // Mark as transferred immediately
                $item->inventory_transferred_at = now();
                $item->save();

                $dispatched++;
            } catch (\Throwable $e) {
                \Log::error('Failed to dispatch inventory transfer job', [
                    'item_id' => $item->id,
                    'error'   => $e->getMessage(),
                ]);
                $failures[] = $item->name ?? $item->sku;
            }
        }

        $this->selected = [];

        if ($failures) {
            $this->dispatch('error', 'Dispatched ' . $dispatched . ', failed: ' . implode(', ', $failures));
        } elseif ($skipped > 0 && $dispatched === 0) {
            $this->dispatch('error', 'All selected items were already transferred to inventory.');
        } else {
            $msg = $dispatched . ' item(s) queued for inventory transfer.';
            if ($skipped) $msg .= ' ' . $skipped . ' already transferred (skipped).';
            $this->dispatch('success', $msg);
        }
    }


    public function bulkStatusOptions(): array
    {
        try {
            $opts = collect($this->statuses ?? [])->map(fn($s) => strtolower(trim((string) $s)))->unique()->values();
            $ids = collect($this->selected ?? [])->filter(fn($v) => is_numeric($v))->map(fn($v) => (int)$v);
            if ($ids->isEmpty()) return $opts->all();

            $first = OrderProduct::with('order')->find($ids->first());
            if (!$first || !$first->order) return $opts->all();

            $isDelivery = (float)($first->order->delivery_price ?? 0) > 0;
            if ($isDelivery) {
                // Delivery orders: hide pickup-only statuses
                $opts = $opts->reject(fn($s) => in_array($s, ['ready for collection','collected'], true))->values();
            } else {
                // Pickup orders: hide delivery-only statuses
                $opts = $opts->reject(fn($s) => in_array($s, ['out for delivery','delivered'], true))->values();
            }
            return $opts->all();
        } catch (\Throwable $e) {
            return $this->statuses ?? [];
        }
    }

    public function getStatusOptionsForItem(int $itemId): array
    {
        try {
            $opts = collect($this->statuses ?? [])->map(fn($s) => strtolower(trim((string) $s)))->unique()->values();
            $it = OrderProduct::with('order')->find($itemId);
            if (!$it || !$it->order) return $opts->all();

            $isDelivery = (float)($it->order->delivery_price ?? 0) > 0;
            if ($isDelivery) {
                // Delivery orders: hide pickup-only statuses
                $opts = $opts->reject(fn($s) => in_array($s, ['ready for collection','collected'], true))->values();
            } else {
                // Pickup orders: hide delivery-only statuses
                $opts = $opts->reject(fn($s) => in_array($s, ['out for delivery','delivered'], true))->values();
            }
            return $opts->all();
        } catch (\Throwable $e) {
            return $this->statuses ?? [];
        }
    }

}; ?>
<div>
<style>
    /* ================================================================
       ORDER ITEMS — PREMIUM CARD-ROW DESIGN
    ================================================================ */

    /* -- Filter bar -- */
    .oi-fb { background:#fff; border:1px solid #e8ecf1; border-radius:14px;
               padding:1rem 1.25rem 1rem; margin-bottom:1.25rem;
               box-shadow:0 2px 14px rgba(15,23,42,.06); }
    .oi-fb-title { font-size:.62rem; font-weight:800; letter-spacing:.12em;
                   text-transform:uppercase; color:#94a3b8;
                   display:flex; align-items:center; gap:.35rem; margin-bottom:.7rem; }
    .oi-fb .oi-lbl { font-size:.64rem; font-weight:700; letter-spacing:.07em;
                     text-transform:uppercase; color:#64748b; display:block; margin-bottom:3px; }
    .oi-fb .form-control, .oi-fb .form-select {
        border:1.5px solid #e2e8f0; border-radius:9px; font-size:.8rem;
        padding:.36rem .7rem; background:#f8fafc; color:#1e293b;
        transition:border-color .15s, box-shadow .15s; }
    .oi-fb .form-control:focus, .oi-fb .form-select:focus {
        background:#fff; border-color:#6366f1;
        box-shadow:0 0 0 3px rgba(99,102,241,.15); outline:none; }
    .oi-clr-btn { background:linear-gradient(135deg,#fee2e2,#fecaca);
                  color:#b91c1c; border:none; border-radius:9px;
                  padding:.38rem 1.1rem; font-size:.78rem; font-weight:700;
                  cursor:pointer; transition:all .15s; white-space:nowrap;
                  align-self:flex-end; }
    .oi-clr-btn:hover { background:linear-gradient(135deg,#fecaca,#fca5a5); }

    /* -- Table -- */
    .oi-table-wrap { overflow-x:auto; }
    .oi-table { width:100%; border-collapse:separate; border-spacing:0 9px; table-layout:auto; }

    /* -- Header row -- */
    .oi-table thead tr {
        background:linear-gradient(135deg,#1e293b 0%,#334155 100%);
    }
    .oi-table thead th {
        background:transparent; color:#cbd5e1;
        font-size:.6rem; font-weight:800; letter-spacing:.13em;
        text-transform:uppercase; padding:.75rem 1rem;
        border:none; white-space:nowrap; }
    .oi-table thead tr th:first-child { border-radius:12px 0 0 12px; }
    .oi-table thead tr th:last-child  { border-radius:0 12px 12px 0; }
    .oi-sort-btn { color:inherit; background:none; border:none;
                   cursor:pointer; font:inherit; font-weight:800;
                   letter-spacing:inherit; text-transform:inherit; font-size:inherit; padding:0; }
    .oi-sort-btn:hover { color:#e2e8f0; }

    /* -- Data rows: each <td> forms one face of the card -- */
    .oi-table tbody tr td {
        background:#f7f9fd;
        padding:.9rem 1rem;
        border-top:1px solid #eef2f7;
        border-bottom:1px solid #eef2f7;
        vertical-align:middle; }
    .oi-table tbody tr td.oi-img-td { padding:0; overflow:hidden; vertical-align:top; }
    .oi-table tbody tr td:first-child {
        border-left:1px solid #eef2f7;
        border-radius:12px 0 0 12px; }
    .oi-table tbody tr td:last-child {
        border-right:1px solid #eef2f7;
        border-radius:0 12px 12px 0; }
    /* Hover lift */
    .oi-table tbody tr:hover td {
        background:#edf2ff;
        border-color:#c7d3f7;
        box-shadow:0 4px 18px rgba(59,130,246,.09); }

    /* -- Left stripe via inline style on first-td (driven by status colour in PHP) -- */

    /* -- Product image (full-height) -- */
    .oi-img { width:72px; min-height:86px; object-fit:contain; display:block; flex-shrink:0;
               border-radius:10px 0 0 10px; background:#f8fafc; }

    /* -- Status pill -- */
    .oi-status { display:block; width:100%; padding:.44rem .65rem; border-radius:50px;
                  border:none; cursor:pointer; font-size:.7rem; font-weight:800;
                  letter-spacing:.05em; text-align:center;
                  transition:opacity .12s, transform .1s; }
    .oi-status:hover { opacity:.82; transform:scale(1.03); }

    /* -- ETA -- */
    .oi-eta-has { display:block; width:100%; margin-top:.5rem;
                   background:#ecfdf5; color:#065f46;
                   border:1.5px solid #6ee7b7; border-radius:10px;
                   font-size:.72rem; font-weight:600; padding:.3rem .5rem;
                   text-align:center; cursor:pointer;
                   transition:background .12s, border-color .12s; }
    .oi-eta-has:hover { background:#d1fae5; border-color:#34d399; }
    .oi-eta-nil { display:block; width:100%; margin-top:.5rem;
                   background:transparent; color:#94a3b8;
                   border:1.5px dashed #cbd5e1; border-radius:10px;
                   font-size:.72rem; padding:.3rem .5rem;
                   text-align:center; cursor:pointer;
                   transition:all .12s; }
    .oi-eta-nil:hover { background:#f8fafc; color:#475569; border-style:solid; }

    /* -- Inventory badge -- */
    .oi-inv { display:inline-flex; align-items:center; gap:4px; margin-top:6px;
               background:#dcfce7; color:#15803d;
               font-size:.62rem; font-weight:700;
               padding:3px 9px; border-radius:50px; }

    /* -- Destination colors -- */
    .dc-harare   { color:#7c3aed; font-weight:700; }
    .dc-bulawayo { color:#db2777; font-weight:700; }
    .dc-mutare   { color:#0284c7; font-weight:700; }
    .dc-zambia   { color:#15803d; font-weight:700; }
    .dc-default  { color:#475569; font-weight:600; }

    /* -- Chat link -- */
    .oi-chat { display:inline-flex; align-items:center; gap:5px;
                font-size:.76rem; font-weight:600; color:#6366f1;
                text-decoration:none; padding:.28rem .65rem;
                border-radius:8px; background:#eef2ff; margin-top:.55rem;
                transition:all .13s; border:none; cursor:pointer; }
    .oi-chat:hover { background:#e0e7ff; color:#4f46e5; }

</style>
    <div x-data="popoverCtl(@js($canEditOrderItems))" x-init="init()" x-on:keydown.escape.window="close()">
        <div class="content__boxed">
            <div class="content__wrap">
                <div class="card">
                    <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
                        <h5 class="card-title mb-0">Order Items</h5>
                        <div class="d-flex gap-2 align-items-center"></div>
                    </div>

                    <div class="card-body">
                        @if(count($selected) > 0)
                            <div style="background:linear-gradient(135deg,#eef2ff,#f0f9ff);border:1.5px solid #c7d2fe;border-radius:14px;padding:.75rem 1.1rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem;">
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                    <span style="background:#6366f1;color:#fff;border-radius:50px;font-size:.72rem;font-weight:800;padding:3px 12px;">{{ count($selected) }}</span>
                                    <span style="font-size:.83rem;font-weight:600;color:#3730a3;">items selected</span>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    @if($canEditOrderItems)
                                    <div class="d-flex align-items-center gap-2">
                                        <label style="font-size:.75rem;font-weight:700;color:#475569;white-space:nowrap;margin:0;"><i class="bi bi-calendar3 me-1"></i>Set ETA</label>
                                        <input type="date" class="form-control form-control-sm" style="max-width:180px;border-radius:8px;font-size:.78rem;" wire:model.defer="etaBulk">
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <label style="font-size:.75rem;font-weight:700;color:#475569;white-space:nowrap;margin:0;"><i class="bi bi-tag me-1"></i>Set Status</label>
                                        <select class="form-select form-select-sm" style="max-width:180px;border-radius:8px;font-size:.78rem;" wire:model.defer="statusBulk">
                                            <option value="">Select status…</option>
                                            @foreach($this->bulkStatusOptions() as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <label style="font-size:.75rem;font-weight:700;color:#475569;white-space:nowrap;margin:0;"><i class="bi bi-person-badge me-1"></i>Assign to</label>
                                        <select class="form-select form-select-sm" style="max-width:200px;border-radius:8px;font-size:.78rem;" wire:model.defer="assignedToBulk">
                                            <option value="">— Unassigned —</option>
                                            @foreach($staffRainesList as $staff)
                                                <option value="{{ $staff['id'] }}">{{ $staff['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <x-button style="background:linear-gradient(135deg,#6366f1,#818cf8);color:#fff;border:none;border-radius:50px;font-size:.72rem;font-weight:700;padding:.3rem .9rem;cursor:pointer;white-space:nowrap;" target="bulkApply"><i class="bi bi-check-lg me-1"></i>Save</x-button>
                                </div>
                                    @endif

                                    @if($canTransferToInventory)
                                    <button type="button"
                                            style="background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#1c1917;border:none;border-radius:50px;font-size:.72rem;font-weight:700;padding:.32rem 1rem;cursor:pointer;white-space:nowrap;"
                                            x-on:click="
                                                Swal.fire({
                                                    title: 'Transfer to Inventory?',
                                                    text: 'Transfer {{ count($selected) }} selected item(s) to inventory shipments?',
                                                    icon: 'question',
                                                    showCancelButton: true,
                                                    confirmButtonText: 'Yes, Transfer',
                                                    cancelButtonText: 'Cancel',
                                                    confirmButtonColor: '#f59e0b',
                                                    cancelButtonColor: '#6c757d',
                                                }).then(result => { if (result.isConfirmed) $wire.transferToInventory(); })
                                            ">
                                        <i class="bi bi-box-arrow-in-down me-1"></i>Transfer to Inventory
                                    </button>
                                    @endif

                                    <div x-on:open-takealot.window="window.open($event.detail.url, '_blank')">
                                        <button type="button"
                                                style="border:1.5px solid #334155;background:transparent;color:#334155;border-radius:50px;font-size:.72rem;font-weight:700;padding:.3rem .9rem;cursor:pointer;white-space:nowrap;"
                                                wire:click="openOnTakealot">
                                            <i class="bi bi-link-45deg me-1"></i>Link Builder
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ── FILTER BAR ── --}}
                        <div class="oi-fb">
                            <div class="oi-fb-title"><i class="bi bi-funnel-fill"></i> Filter Items</div>
                            <div class="row g-2 align-items-end">
                                <div class="col">
                                    <span class="oi-lbl">Order #</span>
                                    <input type="text" class="form-control" placeholder="e.g. 1234" wire:model.live="orderFilter">
                                </div>
                                <div class="col-md-3">
                                    <span class="oi-lbl">Product / SKU</span>
                                    <input type="text" class="form-control" placeholder="Name or SKU…" wire:model.live="productFilter">
                                </div>
                                <div class="col">
                                    <span class="oi-lbl">ETA From</span>
                                    <input type="date" class="form-control" wire:model.live="etaFrom">
                                </div>
                                <div class="col">
                                    <span class="oi-lbl">ETA To</span>
                                    <input type="date" class="form-control" wire:model.live="etaTo">
                                </div>
                                <div class="col">
                                    <span class="oi-lbl">Status</span>
                                    <select class="form-select" wire:model.live="statusFilter">
                                        <option value="">All</option>
                                        @foreach($statuses as $opt)
                                            <option value="{{ $opt }}">{{ ucwords($opt) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col">
                                    <span class="oi-lbl">Destination</span>
                                    <input type="text" class="form-control" placeholder="City or country…" wire:model.live.debounce.500ms="destQuery">
                                </div>
                                <div class="col">
                                    <span class="oi-lbl"><i class="bi bi-person-badge me-1"></i>Assigned To</span>
                                    <select class="form-select" wire:model.live="assignedToFilter">
                                        <option value="">All</option>
                                        <option value="__unassigned__">— Unassigned —</option>
                                        @foreach($staffRainesList as $staff)
                                            <option value="{{ $staff['id'] }}">{{ $staff['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-auto d-flex align-items-end">
                                    <button type="button" class="oi-clr-btn" wire:click="clearFilters">
                                        <i class="bi bi-x-circle-fill me-1"></i>Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- ── TABLE ── --}}
                        <div class="oi-table-wrap">
                            <table class="oi-table">
                                <thead>
                                <tr>
                                    <th style="width:32px;" class="text-center">
                                        @if($canEditOrderItems)
                                            <input type="checkbox"
                                                   class="form-check-input"
                                                   title="{{ $allOnPage ? 'Deselect all on page' : 'Select all on page' }}"
                                                   {{ $allOnPage ? 'checked' : '' }}
                                                   wire:click="toggleSelectPage({{ json_encode($pageIds) }})">
                                        @endif
                                    </th>
                                    <th style="width:160px;">
                                        <button class="oi-sort-btn" wire:click="setSort('order_number')">
                                             Order Items @if($sortBy==='order_number'){{ $sortDir==='asc'?'↑':'↓' }}@endif
                                        </button>
                                    </th>
                                    <th>Product</th>
                                    <th style="width:200px;" class="text-center">
                                        <button class="oi-sort-btn" wire:click="setSort('order_status')">
                                            Status &amp; ETA @if($sortBy==='order_status'){{ $sortDir==='asc'?'↑':'↓' }}@endif
                                        </button>
                                    </th>
                                    <th style="width:160px;">Destination</th>
                                </tr>
                                </thead>

                                <tbody>

                                {{-- Data Rows --}}
                                @forelse($items as $it)
                                    @php
                                        $sym  = $it->order?->currency_symbol ?? $it->order?->currency ?? '$';
                                        $rate = (float)($it->order?->exchange_rate ?? 1);
                                        $hasSale = !is_null($it->sale_price) && is_numeric($it->sale_price)
                                                && !is_null($it->price)      && is_numeric($it->price)
                                                && (float)$it->sale_price < (float)$it->price;
                                        if ($hasSale) {
                                            $baseConv = (float)$it->price      * $rate;
                                            $saleConv = (float)$it->sale_price * $rate;
                                        } else {
                                            $displayPrice = $it->single_price ?? $it->price ?? 0;
                                            $baseConv     = (float)$displayPrice * $rate;
                                            $saleConv     = null;
                                        }
                                        $statusText = $this->orderStatusName($it->order);

                                        // Variation
                                        $variationText = $it->variation_display;
                                        if (!$variationText) {
                                            $vVals = collect($it->variation_attributes ?? [])->map(function($v){
                                                return is_array($v) ? ($v['value'] ?? ($v['name'] ?? null)) : $v;
                                            })->filter()->values()->all();
                                            $variationText = $it->variation_name ?? (count($vVals) ? implode(', ',$vVals) : null);
                                        }

                                        // Destination & stripe class
                                        $dest      = $this->destinationCityCountry($it->order);
                                        $dLower    = strtolower($dest ?? '');
                                        $dClass    = 'dc-default';
                                        $dStripe   = 'oi-s-default';
                                        $dIcon     = 'bi-geo-alt-fill';
                                        if      (str_contains($dLower,'harare'))   { $dClass='dc-harare';   $dStripe='oi-s-harare'; }
                                        elseif  (str_contains($dLower,'bulawayo')) { $dClass='dc-bulawayo'; $dStripe='oi-s-bulawayo'; }
                                        elseif  (str_contains($dLower,'mutare'))   { $dClass='dc-mutare';   $dStripe='oi-s-mutare'; }
                                        elseif  (str_contains($dLower,'zambia') || str_contains($dLower,'lusaka')
                                              || str_contains($dLower,'ndola')   || str_contains($dLower,'kitwe'))
                                                { $dClass='dc-zambia'; $dStripe='oi-s-zambia'; $dIcon='bi-airplane-fill'; }
                                    @endphp

                                     <tr wire:key="item-{{ $it->id }}">
                                        {{-- ✅ Checkbox — left stripe = status colour --}}
                                        <td class="text-center" style="width:32px;box-shadow:inset 5px 0 0 {{ \App\Helpers\OrderStatusColors::hex($it->status) }};">
                                            @if($canEditOrderItems)
                                                <input type="checkbox" class="form-check-input"
                                                       value="{{ (int)$it->id }}"
                                                       wire:model.live="selected">
                                            @else
                                                <span style="color:#cbd5e1;">—</span>
                                            @endif
                                        </td>

                                        {{-- Order Items --}}
                                        <td style="min-width:150px;">
                                            <span style="display:inline-block;border:2px solid {{ \App\Helpers\OrderStatusColors::hex($it->status) }};color:#1e293b;border-radius:7px;font-size:.78rem;font-weight:800;padding:3px 10px;letter-spacing:.02em;">
                                                #{{ $it->order?->order_number ?? $it->order?->id }}
                                            </span>
                                            <div style="margin-top:7px;font-size:.73rem;color:#64748b;">
                                                <i class="bi bi-layers" style="font-size:.7rem;"></i>&nbsp;<span style="font-weight:600;">{{ (int)($it->quantity ?? 1) }}&times; qty</span>
                                                &nbsp;<span style="color:#cbd5e1;">·</span>&nbsp;
                                                <i class="bi bi-calendar3" style="font-size:.7rem;"></i>&nbsp;{{ $it->created_at?->format('d M y') }}
                                            </div>
                                            @if($it->inventory_transferred_at)
                                                <span class="oi-inv" style="margin-top:7px;" title="Transferred: {{ $it->inventory_transferred_at->format('d M Y') }}">
                                                    <i class="bi bi-check2-all"></i> In Inventory
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Product (full-height image) --}}
                                        <td class="oi-img-td">
                                            <div style="display:flex;align-items:stretch;height:100%;min-height:86px;">
                                                <img class="oi-img"
                                                     src="{{ $it->product_thumbnail_url ?: asset('default.png') }}"
                                                     alt="product">
                                                <div style="padding:.8rem .9rem;min-width:0;flex:1;display:flex;flex-direction:column;justify-content:center;">
                                                    @if($it->slug && !empty($FE))
                                                        <a href="{{ rtrim($FE,'/').'/en/product/'.ltrim($it->slug,'/') }}"
                                                           target="_blank"
                                                           style="font-size:.9rem;font-weight:700;color:#1e293b;text-decoration:none;display:block;line-height:1.35;">
                                                            {{ Str::limit($it->name ?? 'Unknown', 65) }}
                                                        </a>
                                                    @else
                                                        <div style="font-size:.9rem;font-weight:700;color:#1e293b;line-height:1.35;">
                                                            {{ Str::limit($it->name ?? 'Unknown', 65) }}
                                                        </div>
                                                    @endif
                                                    <div style="font-size:.71rem;color:#94a3b8;font-family:ui-monospace,monospace;margin-top:3px;">
                                                        <i class="bi bi-upc" style="font-size:.7rem;"></i>&nbsp;{{ $it->sku ?? 'N/A' }}
                                                    </div>
                                                    <div style="margin-top:5px;">
                                                        @if($hasSale)
                                                            <span style="font-size:.75rem;color:#94a3b8;text-decoration:line-through;">{{ $sym }}{{ number_format((float)$baseConv,2) }}</span>
                                                            <span style="font-size:.9rem;font-weight:800;color:#16a34a;margin-left:5px;">{{ $sym }}{{ number_format((float)$saleConv,2) }}</span>
                                                        @elseif(!is_null($baseConv))
                                                            <span style="font-size:.9rem;font-weight:800;color:#1e293b;">{{ $sym }}{{ number_format((float)$baseConv,2) }}</span>
                                                        @endif
                                                    </div>
                                                    <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:7px;">
                                                        @if(!empty($variationText))
                                                            <span style="border:1.5px solid #4f46e5;color:#4f46e5;background:transparent;font-size:.6rem;font-weight:700;padding:2px 8px;border-radius:50px;"><i class="bi bi-palette2"></i>&nbsp;{{ $variationText }}</span>
                                                        @endif
                                                        @if($it->has_fast_shipping || ($it->fast_shipping_cost ?? 0) > 0)
                                                            <span style="border:1.5px solid #b45309;color:#b45309;background:transparent;font-size:.6rem;font-weight:700;padding:2px 8px;border-radius:50px;">&#9889;&nbsp;Express 3&#8211;4d</span>
                                                        @endif
                                                        @if($it->estimated_delivery_text)
                                                            <span style="border:1.5px solid #0d9488;color:#0d9488;background:transparent;font-size:.6rem;font-weight:700;padding:2px 8px;border-radius:50px;"><i class="bi bi-truck"></i>&nbsp;{{ $it->estimated_delivery_text }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Status & ETA --}}
                                        <td class="text-center" style="width:200px;">
                                            <button class="oi-status"
                                                    style="background:{{ \App\Helpers\OrderStatusColors::hex($it->status) }};color:{{ \App\Helpers\OrderStatusColors::textColor(\App\Helpers\OrderStatusColors::hex($it->status)) }};"
                                                    x-on:click="openEdit($event,{{ $it->id }},@js($it->eta?->format('Y-m-d')),@js($it->status),@js($it->assigned_to))">
                                                {{ $it->status ? Str::title($it->status) : 'Set Status' }}
                                            </button>
                                            @if($it->eta)
                                                <button class="oi-eta-has"
                                                        x-on:click="openEdit($event,{{ $it->id }},@js($it->eta?->format('Y-m-d')),@js($it->status),@js($it->assigned_to))">
                                                    <i class="bi bi-calendar-check"></i>&nbsp;{{ $it->eta->format('d M Y') }}
                                                </button>
                                            @else
                                                <button class="oi-eta-nil"
                                                        x-on:click="openEdit($event,{{ $it->id }},@js($it->eta?->format('Y-m-d')),@js($it->status),@js($it->assigned_to))">
                                                    <i class="bi bi-calendar-plus"></i>&nbsp;Set ETA
                                                </button>
                                            @endif
                                            @if($statusText)
                                                <div style="font-size:.6rem;color:#94a3b8;margin-top:5px;">Order Items: {{ Str::title($statusText) }}</div>
                                            @endif
                                            @if($it->assigned_to)
                                                @php
                                                    $assignedName = collect($staffRainesList)->firstWhere('id', $it->assigned_to)['name'] ?? null;
                                                @endphp
                                                @if($assignedName)
                                                    <div style="margin-top:5px;">
                                                        <span style="display:inline-flex;align-items:center;gap:4px;background:#ede9fe;color:#5b21b6;font-size:.6rem;font-weight:700;padding:2px 8px;border-radius:50px;">
                                                            <i class="bi bi-person-check-fill"></i> {{ $assignedName }}
                                                        </span>
                                                    </div>
                                                @endif
                                            @endif
                                        </td>

                                        {{-- Destination & Chat --}}
                                        <td>
                                            @if($dest)
                                                <div class="{{ $dClass }}" style="font-size:.84rem;">
                                                    <i class="bi {{ $dIcon }} me-1"></i>{{ $dest }}
                                                </div>
                                            @else
                                                <span style="color:#cbd5e1;">—</span>
                                            @endif
                                            @if($it->slug && !empty($FE))
                                                <button class="oi-chat" wire:click="openChat({{ $it->id }})">
                                                    <i class="bi bi-chat-dots-fill"></i> Chat
                                                    @if(($it->unread_cnt ?? 0) > 0)
                                                        <span class="badge rounded-pill bg-danger" style="font-size:.58rem;">{{ (int)$it->unread_cnt }}</span>
                                                    @endif
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="text-align:center;padding:3.5rem 1rem;color:#94a3b8;">
                                            <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:.75rem;"></i>
                                            No order items found.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-center">
                            {{ $items->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Anchored Popover --}}
        <div x-cloak x-show="show" x-transition class="card shadow" x-ref="popover" :style="styleString()" @click.outside="close()">
            <div class="card-body">
                <div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">ETA</label>
                        <input type="date" class="form-control" x-model="eta" :disabled="!canEdit">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Status</label>
                        <select class="form-select" x-model="status" :disabled="!canEdit">
                            <option value="">Select status...</option>
                            <template x-for="opt in options" :key="opt">
                                <option :value="opt" x-text="opt"></option>
                            </template>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1"><i class="bi bi-person-badge me-1"></i>Assign to Staff</label>
                        <select class="form-select" x-model="assignedTo" :disabled="!canEdit">
                            <option value="">— Unassigned —</option>
                            @foreach($staffRainesList as $staff)
                                <option value="{{ $staff['id'] }}">{{ $staff['name'] }} <{{ $staff['email'] }}></option>
                            @endforeach
                        </select>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-sm btn-light me-2" @click="close()">Close</button>
                        <button type="button" class="btn btn-sm btn-primary" x-show="canEdit" @click="save()" :disabled="!canEdit || saving">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" x-show="saving"></span>
                            <span x-text="saving ? 'Saving...' : 'Save'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Chat Drawer (CSS toggled: avoids extra @endif mismatches) --}}
        @php
            $__chatOpen = (bool)($chatOpen ?? false);
        @endphp

        <div class="position-fixed top-0 start-0 w-100 h-100 chat-drawer-overlay"
             style="z-index:1050; {{ $__chatOpen ? '' : 'display:none;' }}"
             @if($__chatOpen) wire:click="closeChat" @endif></div>

        <div class="position-fixed top-0 end-0 h-100 shadow chat-drawer"
             style="z-index:1051; {{ $__chatOpen ? '' : 'display:none;' }}">
            <div class="d-flex align-items-center justify-content-between border-bottom px-3 py-3">
                <h6 class="mb-0">Item Updates</h6>
                <button class="btn btn-sm btn-light" wire:click="closeChat" aria-label="Close">✕</button>
            </div>

            {{-- Composer --}}
            <div class="p-3 border-bottom" x-data="{
                body: @entangle('chatBody'),
                suggestions: @entangle('userSuggestions'),
                mentionIds: @entangle('chatMentionUserIds'),
                open: false,
                debounce: null,
                onInput(){
                    const val = this.body || '';
                    const at = val.lastIndexOf('@');
                    if (at >= 0) {
                        const term = val.slice(at+1).trim();
                        if (term.length >= 1) {
                            this.open = true;
                            clearTimeout(this.debounce);
                            this.debounce = setTimeout(()=>{ this.$wire.searchUsers(term); }, 200);
                            return;
                        }
                    }
                    this.open = false; this.suggestions = [];
                },
                select(u){
                    const val = this.body || '';
                    const at = val.lastIndexOf('@');
                    if (at >= 0) { this.body = val.slice(0, at) + '@' + u.name + ' '; }
                    try {
                        const ids = Array.isArray(this.mentionIds) ? this.mentionIds.slice() : [];
                        if (!ids.includes(u.id)) { ids.push(u.id); }
                        this.mentionIds = ids;
                    } catch(_) {}
                    this.open = false; this.suggestions = []; this.$nextTick(()=> this.$refs.msg.focus());
                },
                canSend(){
                    const b = (this.body||'').trim();
                    if (!b) return false;
                    const replying = @js((bool)$chatReplyTo);
                    if (replying) return true;
                    return /(^|\s)@([\w.\- ]{1,50})/.test(b);
                },
                send(){ if (this.canSend()) { this.$wire.sendChat(); } }
            }">
                {{-- Reply banner --}}
                @if(!empty($chatReplyTo))
                    @php
                        $parent = collect($chatMessages)->firstWhere('id', $chatReplyTo);
                        $parentName = data_get($parent, 'user.name', 'User');
                        $parentRaw = (string) data_get($parent, 'raw_body', '');
                        $parentPreview = \Illuminate\Support\Str::limit(strip_tags($parentRaw), 120);
                    @endphp

                    <div class="alert alert-info py-2 px-3 d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong>Replying to:</strong> {{ $parentName }}
                            @if(!empty($parentPreview))
                                <span class="text-muted">— "{{ $parentPreview }}"</span>
                            @endif
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-dark" wire:click="cancelReply">Cancel</button>
                    </div>
                @endif

                <div class="border rounded p-2 d-flex align-items-end position-relative" style="border-color: var(--bs-border-color);">
                    <textarea x-ref="msg" class="form-control border-0 p-0" rows="2"
                              placeholder="Type @ to mention a user..."
                              x-model="body" @input="onInput" @keydown.enter.prevent="send()" style="resize: vertical; outline:none; box-shadow:none;"></textarea>

                    <button type="button" class="btn p-0 ms-2" @click="send()" :disabled="!canSend()" aria-label="Send" title="Send (requires @mention for new messages)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-send" viewBox="0 0 16 16">
                            <path d="M15.964.686a.5.5 0 0 1 .01.858L1.714 8.29l5.79 1.447a.5.5 0 0 1 .122.03l3.746 1.248 3.747 1.248a.5.5 0 0 1 .04.94l-3.746 1.248-3.747 1.248a.5.5 0 0 1-.61-.3l-5.79-14.5a.5.5 0 0 1 .748-.592l14.25 6.747a.5.5 0 0 1-.01.858L2.223 7.803 15.964.686z"/>
                        </svg>
                    </button>

                    <div class="position-absolute bg-white border rounded shadow-sm w-100" x-show="open && (suggestions||[]).length" style="top:100%; left:0; margin-top:4px; z-index:2000;" @mousedown.prevent>
                        <template x-for="u in suggestions" :key="u.id">
                            <div class="px-2 py-1 suggestion-item" @click="select(u)" style="cursor:pointer;">
                                <span x-text="'@' + u.name"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Messages --}}
            <div class="p-3 overflow-auto" style="max-height: calc(100vh - 150px);">
                @forelse($chatMessages as $m)
                    @php($isReply = !empty($m['parent_id']))
                    <div class="card mb-2" @if($isReply) style="margin-left: 28px; border-left: 3px solid #e9ecef;" @endif>
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge text-bg-secondary">{{ strtoupper(substr($m['user']['name'] ?? 'U',0,1)) }}</span>
                                <div class="fw-semibold">{{ $m['user']['name'] ?? 'User' }}</div>
                                <div class="text-muted small">{{ $m['created_at'] ?? '' }}</div>
                            </div>
                            <div>{!! $m['body'] ?? '' !!}</div>
                            <div class="d-flex gap-3 mt-2 align-items-center">
                                <span role="button" class="text-muted" wire:click="toggleLike({{ $m['id'] }})" title="Like">👍 ({{ count($m['likes'] ?? []) }})</span>
                                <span role="button" class="text-muted" wire:mouseover="markViewed({{ $m['id'] }})" title="Views">👁️ ({{ count($chatViews[$m['id']] ?? []) }})</span>
                                <span role="button" class="text-primary" wire:click="replyTo({{ $m['id'] }})" title="Reply">↩ Reply</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">No comments yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function popoverCtl(canEdit) {
            return {
                canEdit: !!canEdit,
                show:false, type:null, id:null, eta:'', status:'', assignedTo:'', options: [], top:0, left:0, anchor:null, saving:false,
                init(){
                    const update = () => {
                        if (!this.show || !this.anchor) return;
                        if (!document.body.contains(this.anchor)) { this.show=false; this.anchor=null; return; }
                        const r = this.anchor.getBoundingClientRect();
                        const pop = this.$refs.popover;
                        const popW = pop ? pop.offsetWidth : 280;
                        const popH = pop ? pop.offsetHeight : 150;

                        let top = r.bottom + 6;
                        let left = r.left;

                        left = Math.min(Math.max(10, left), window.innerWidth - popW - 10);

                        if (top + popH + 10 > window.innerHeight) {
                            top = r.top - popH - 6;
                        }
                        top = Math.max(10, Math.min(top, window.innerHeight - popH - 10));

                        this.top = top;
                        this.left = left;
                    };
                    document.addEventListener('scroll', update, { passive:true, capture:true });
                    window.addEventListener('resize', update, { passive:true });
                    this.$watch('show', v => { if (v) this.$nextTick(() => update()); });
                    document.addEventListener('livewire:load', () => {
                        try {
                            if (window.Livewire && typeof Livewire.hook === 'function') {
                                Livewire.hook('message.processed', () => update());
                            }
                        } catch (e) {}
                    });
                },
                openEdit(ev, id, curEta, curStatus, curAssignedTo){
                    this.type='edit';
                    this.id=id;
                    this.eta=curEta||'';
                    this.status=curStatus||'';
                    this.assignedTo=curAssignedTo||'';
                    this.anchor=ev.currentTarget;
                    this.options = [];
                    try { this.$wire.getStatusOptionsForItem(this.id).then((opts)=>{ this.options = Array.isArray(opts) ? opts : []; }); } catch(e) {}
                    this.show=true;
                },
                close(){ this.show=false; this.anchor=null; },
                async save(){
                    if (!this.canEdit) return;
                    if (this.saving) return;
                    this.saving = true;
                    try {
                        await this.$wire.saveItemMeta(this.id, this.eta, this.status, this.assignedTo ? parseInt(this.assignedTo) : null);
                        this.close();
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.saving = false;
                    }
                },
                styleString(){ return `position:fixed; top:${this.top}px; left:${this.left}px; width:280px; z-index:1060;`; },
            }
        }
    </script>

    <style>
        .table-hover tbody tr { cursor: default; }
        .bulk-bar { border: 1.5px solid #c7d2fe; border-radius: 14px; padding: .75rem 1.1rem; background: linear-gradient(135deg,#eef2ff,#f0f9ff); }
        [x-cloak]{display:none!important;}
        .chat-drawer-overlay { background: rgba(0, 0, 0, .35); }
        .chat-drawer { width: 480px; max-width: 100vw; background: #fff; }
        .suggestion-item:hover { background: var(--bs-light); }
        .mention { color: #0d6efd; font-weight: 500; }
    </style>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @endpush
</div>
