<div style="max-width: 600px; margin: 0 auto; background: #f9f9f9; border-radius: 8px; border: 1px solid #e0e0e0; font-family: Arial, sans-serif; overflow: hidden;">
    <div style="background: #2d3748; padding: 24px 24px 12px 24px; text-align: center;">
        <!-- Logo or App Name -->
        <img src="https://marinduquevehiclerental.com/wp-content/uploads/2022/12/YPkIoO_4.png" alt="App Logo" style="height: 48px; margin-bottom: 8px;" onerror="this.style.display='none'">
        <h1 style="color: #fff; margin: 0; font-size: 1.5rem;">{{ config('app.name', 'Your App') }}</h1>
    </div>
    <div style="padding: 24px; background: #fff;">
        <h2 style="color: #2d3748; margin-top: 0;">{{ $subject ?? 'Notification' }}</h2>
        <p style="font-size: 1.1rem; color: #4a5568;">{{ $body }}</p>
        @if(isset($details) && is_array($details))
            <ul style="padding-left: 20px; color: #2d3748;">
                @foreach($details as $key => $value)
                    <li><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    <div style="background: #f1f1f1; padding: 16px 24px; text-align: center; color: #888; font-size: 0.95rem;">
        &copy; {{ date('Y') }} {{ config('app.name', 'Your App') }}. All rights reserved.
    </div>
</div>
