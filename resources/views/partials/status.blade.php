@if (session('status'))
    <p role="status" class="rounded-2xl bg-ok-soft px-4 py-3 text-sm font-medium text-olive-700">{{ session('status') }}</p>
@endif
