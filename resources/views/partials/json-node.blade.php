{{-- Recursive JSON tree: readable without JavaScript thanks to <details>. --}}
@if (is_array($value) && $value !== [])
    @php($list = array_is_list($value))
    <details @if ($depth < 2) open @endif class="pl-{{ $depth > 0 ? '4' : '0' }}">
        <summary class="cursor-pointer text-muted">{{ $list ? '[' : '{' }} <span class="text-subtle">{{ count($value) }}</span> {{ $list ? ']' : '}' }}</summary>
        <div class="pl-4">
            @foreach ($value as $key => $item)
                <div data-json-row>
                    @unless ($list)<span class="json-key">"{{ $key }}"</span>: @endunless
                    @if (is_array($item) && $item !== [])
                        @include('auditor::partials.json-node', ['value' => $item, 'depth' => $depth + 1])
                    @else
                        @php($v = \Rembon\LaravelAuditor\Support\Dashboard\Present::value($item))
                        @switch($v['kind'])
                            @case('redacted')<x-auditor::badge tone="neutral" icon="lock">{{ __('auditor::auditor.common.redacted') }}</x-auditor::badge>@break
                            @case('null')<span class="value-null">null</span>@break
                            @case('literal')<span class="json-literal">{{ $v['text'] }}</span>@break
                            @case('number')<span class="json-number">{{ $v['text'] }}</span>@break
                            @case('json')<span class="text-muted">{{ is_array($item) && array_is_list($item) ? '[]' : '{}' }}</span>@break
                            @default<span class="json-string break-all">"{{ $v['text'] }}"</span>
                        @endswitch
                    @endif
                </div>
            @endforeach
        </div>
    </details>
@else
    <span class="text-muted">{{ is_array($value) ? '{}' : json_encode($value) }}</span>
@endif
