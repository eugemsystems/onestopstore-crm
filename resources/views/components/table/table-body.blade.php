@use('App\Helpers\Helpers')
@php
    $filter = request()->filled('filter') ? request()->filter : 'all';
@endphp
<td>
    @if (!empty($column['type']))
        @if (isset($row['status']) && $column['type'] == 'status')
            <label class="switch switch-sm">
                <input id="status-{{$row['id']}}" @if(isset($column['route'])) data-route="{{route($column['route'], $row['id'])}}"
                    @endif class="form-check-input toggle-class" value="1" type="checkbox" @if($row['status'])checked @endif>
                <span class="switch-state"></span>
            </label>
        @endif
    @else
        <div @if(isset($column['action']) && $column['action']) class="d-flex align-items-start gap-2" @endif>
            @if(isset($column['imageField']) && Helpers::getMedia($row[$column['imageField']]))
                <img class="table-image" src="{{ Helpers::getMedia($row[$column['imageField']])->original_url }}" alt="image">
            @elseif(isset($column['imageUrl']))
                <img class="table-image" src="{{ $row[$column['imageUrl']] }}" alt="image">
            @elseif(isset($column['placeholder']))
                <img class="table-image" src="{{ asset($column['placeholder']) }}" alt="placeholder">
            @endif
            @if(isset($column['action']) && $column['action'])
                <div class="user-detail">
                    @if(isset($column['route']) && $filter != 'trash')
                        <a href="{{ route($column['route'], $row['id']) }}">{{ $row[$column['field']] }}</a>
                    @else
                        {{ $row[$column['field']] }}
                    @endif
                    <ul class="row-actions">
                        @foreach($actions as $action)
                            @if(empty($action['whenFilter']) || (!empty($action['whenFilter']) && in_array($filter, $action['whenFilter'])))
                                @if(!isset($action['whenStatus']) || (isset($action['whenStatus']) && $action['whenStatus'] == $row['status']))
                                    <li class="{{ $action['class'] }}">
                                        @if(isset($action['route']))
                                            <a href="{{ route($action['route'], $row['id']) }}">{{ $action['title'] }}</a>
                                        @elseIf(isset($action['action']) && isset($action['field']))
                                            @if($action['action'] == 'download')
                                                <a href="{{ Helpers::getMedia($row[$action['field']])->original_url }}" download>{{ $action['title'] }}</a>
                                            @elseif($action['action'] == 'copy')
                                                <a href="{{ Helpers::getMedia($row[$action['field']])->original_url }}" class="copy-link">{{ $action['title'] }}</a>
                                            @endif
                                        @endif
                                    </li>
                                @endif
                            @endif
                        @endforeach
                    </ul>
                </div>
            @else
                {{ $row[$column['field']] }}
            @endif
        </div>
    @endif
</td>
