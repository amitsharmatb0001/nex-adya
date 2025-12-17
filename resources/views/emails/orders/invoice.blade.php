@component('mail::message')
# Your Order #{{ $order->id }} has been Delivered 🎉

Thank you for ordering with **Nexadya**.  
Your order from **{{ $order->restaurant->name }}** has been delivered.

We’ve attached your invoice as a PDF.

@component('mail::button', ['url' => config('app.url')])
Open Nexadya
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
