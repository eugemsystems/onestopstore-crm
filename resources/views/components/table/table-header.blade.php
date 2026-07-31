@php
    $order = isset(request()->order) && request()->order == 'asc' ? 'desc' : 'asc';
@endphp
<th class="check-column">
    <div class="form-check"><input type="checkbox" class="form-check-input checkAll"></div>
</th>
@foreach ($columns as $column)
    <th @if (isset($column['sortable']) && $column['sortable']) class="sorting-hover" @endif>
        @if ($column['sortable'])
            <a href="{{ url()->current().'?orderby='.$column['field'].'&order='.$order }}">
                <span>{{ $column['title'] }}</span>
                <span class="sorting-indicators">
                    @if(isset(request()->order) && request()->order == 'asc')
                        <span class="sorting-indicator asc"></span>
                    @elseif(isset(request()->order) && request()->order == 'desc')
                        <span class="sorting-indicator desc"></span>
                    @else
                        <span class="sorting-indicator asc desc"></span>
                    @endif
                </span>
            </a>
        @else
            {{ $column['title'] }}
        @endif
    </th>
@endforeach