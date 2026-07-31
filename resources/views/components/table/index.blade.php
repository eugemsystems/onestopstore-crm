@props(['columns','data','filters','total','search','actions','bulkactions'])
<table class="table">
    <thead class="table-light">
        <tr>
            @include('components.table.table-header', ['columns' => $columns])
        </tr>
    </thead>
    <tbody>
        @forelse($data as $row)
            @foreach($columns as $column)
                @include('components.table.table-body', ['column' => $column, 'row' => $row])
            @endforeach
        @empty
        @endforelse
    </tbody>
</table>