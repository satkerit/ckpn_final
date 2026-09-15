@php /** @var array $errors */ @endphp

<div class="space-y-2">
    @if(empty($errors))
        <p class="text-sm text-zinc-400">Tidak ada error yang tercatat.</p>
    @else
        <ul class="list-disc pl-5 space-y-1 text-sm text-danger-600">
            @foreach($errors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
</div>
