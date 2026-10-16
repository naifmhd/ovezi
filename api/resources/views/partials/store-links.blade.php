<div class="store-links" aria-label="Download Ovezi">
    @if (config('ovezi.app_store_url'))
        <a class="store-link" href="{{ config('ovezi.app_store_url') }}" rel="noreferrer" aria-label="Download Ovezi for iPhone on the App Store">
            <span>Download for iPhone</span>
            <strong>App Store</strong>
        </a>
    @endif
    @if (config('ovezi.play_store_url'))
        <a class="store-link" href="{{ config('ovezi.play_store_url') }}" rel="noreferrer" aria-label="Download Ovezi for Android on Google Play">
            <span>Download for Android</span>
            <strong>Google Play</strong>
        </a>
    @endif
</div>
