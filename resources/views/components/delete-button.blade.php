@props(['action', 'confirm' => 'Are you sure? This cannot be undone.', 'label' => 'Delete'])
<form method="POST" action="{{ $action }}" x-data @submit="if (! confirm(@js($confirm))) $event.preventDefault()" {{ $attributes->only('class') }}>
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-danger {{ $attributes->get('size') === 'sm' ? 'btn-sm' : '' }}"><x-icon name="trash" size="16" /> {{ $label }}</button>
</form>
