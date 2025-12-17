@component('mail::message')
# Payout #{{ $payout->id }} — {{ ucfirst($newStatus) }}

Your payout request for **₹{{ number_format($payout->amount / 100, 2) }}** has changed status.

- **Old status:** {{ $oldStatus }}
- **New status:** {{ $newStatus }}
- **Restaurant:** {{ $payout->restaurant?->name ?? $payout->restaurant_id }}
- **Requested at:** {{ $payout->created_at?->toDayDateTimeString() }}

@if($payout->approved_at)
**Approved at:** {{ $payout->approved_at->toDayDateTimeString() }}
@endif

@if($payout->notes)
**Notes:** {{ $payout->notes }}
@endif

@component('mail::button', ['url' => url('/')])
Open Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
