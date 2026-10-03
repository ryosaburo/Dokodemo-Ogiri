@if (app()->environment('local') && request('as'))
    <input type="hidden" name="as" value="{{ request('as') }}">
@endif
