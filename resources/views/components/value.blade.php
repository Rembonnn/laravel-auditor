@props(['value'])
@php($v = \Rembon\LaravelAuditor\Support\Dashboard\Present::value($value))
@switch($v['kind'])
    @case('redacted')
        <x-auditor::badge tone="neutral" icon="lock">{{ __('auditor::auditor.common.redacted') }}</x-auditor::badge>
        @break
    @case('null')
        <span class="value-null">null</span>
        @break
    @case('empty')
        <span class="value-empty">{{ __('auditor::auditor.common.empty') }}</span>
        @break
    @case('literal')
        <span class="json-literal font-mono">{{ $v['text'] }}</span>
        @break
    @case('number')
        <span class="json-number font-mono tabular">{{ $v['text'] }}</span>
        @break
    @case('json')
        <pre class="whitespace-pre-wrap break-all font-mono text-xs">{{ $v['text'] }}</pre>
        @break
    @default
        @if (mb_strlen($v['text']) > 200)
            <details class="group"><summary class="cursor-pointer break-all">{{ mb_substr($v['text'], 0, 200) }}… <span class="link text-xs">{{ __('auditor::auditor.common.show_more') }}</span></summary><span class="break-all">{{ $v['text'] }}</span></details>
        @else
            <span class="break-all">{{ $v['text'] }}</span>
        @endif
@endswitch
