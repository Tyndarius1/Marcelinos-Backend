@php
    use function Filament\Support\prepare_inherited_attributes;

    $fieldWrapperView = $getFieldWrapperView();
    $extraAlpineAttributes = $getExtraAlpineAttributes();
    $extraAttributeBag = $getExtraAttributeBag();
    $hasInlineLabel = $hasInlineLabel();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field" :has-inline-label="$hasInlineLabel"
    class="fi-fo-text-input-wrp">
    <style>
        .qr-scanner-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            width: 100%;
            padding: 8px 0;
        }

        .qr-scanner-card {
            position: relative;
            width: 100%;
            max-width: 420px;
            background: linear-gradient(145deg, rgba(17, 24, 39, 0.95) 0%, rgba(31, 41, 55, 0.9) 100%);
            border: 1px solid rgba(99, 102, 241, 0.35);
            border-radius: 20px;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(99, 102, 241, 0.15),
                0 20px 40px rgba(0, 0, 0, 0.4),
                0 0 60px rgba(99, 102, 241, 0.08);
        }

        .qr-scanner-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px 10px;
        }

        .qr-scanner-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .qr-scanner-title {
            font-size: 15px;
            font-weight: 600;
            color: #f1f5f9;
            margin: 0;
            letter-spacing: -0.01em;
        }

        .qr-scanner-subtitle {
            font-size: 11px;
            color: #64748b;
            margin: 0;
            margin-top: 1px;
        }

        .qr-status-badge {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .qr-status-badge.scanning {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }

        .qr-status-badge.idle {
            background: rgba(100, 116, 139, 0.15);
            border: 1px solid rgba(100, 116, 139, 0.3);
            color: #64748b;
        }

        .qr-status-badge.success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.5);
            color: #10b981;
        }

        .qr-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .scanning .qr-status-dot {
            background: #10b981;
            animation: pulse-dot 1.5s ease-in-out infinite;
        }

        .idle .qr-status-dot {
            background: #64748b;
        }

        .success .qr-status-dot {
            background: #10b981;
        }

        @keyframes pulse-dot {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.4;
                transform: scale(0.8);
            }
        }

        .qr-viewport-wrapper {
            position: relative;
            margin: 0 16px 16px;
            border-radius: 14px;
            overflow: hidden;
            background: #000;
        }

        /* Corner accent brackets */
        .qr-viewport-wrapper::before,
        .qr-viewport-wrapper::after {
            content: '';
            position: absolute;
            inset: 10px;
            border-radius: 8px;
            pointer-events: none;
            z-index: 10;
            border: 2px solid transparent;
        }

        .qr-corner-tl,
        .qr-corner-tr,
        .qr-corner-bl,
        .qr-corner-br {
            position: absolute;
            width: 26px;
            height: 26px;
            z-index: 11;
            pointer-events: none;
        }

        .qr-corner-tl {
            top: 10px;
            left: 10px;
            border-top: 3px solid #6366f1;
            border-left: 3px solid #6366f1;
            border-radius: 4px 0 0 0;
        }

        .qr-corner-tr {
            top: 10px;
            right: 10px;
            border-top: 3px solid #6366f1;
            border-right: 3px solid #6366f1;
            border-radius: 0 4px 0 0;
        }

        .qr-corner-bl {
            bottom: 10px;
            left: 10px;
            border-bottom: 3px solid #6366f1;
            border-left: 3px solid #6366f1;
            border-radius: 0 0 0 4px;
        }

        .qr-corner-br {
            bottom: 10px;
            right: 10px;
            border-bottom: 3px solid #6366f1;
            border-right: 3px solid #6366f1;
            border-radius: 0 0 4px 0;
        }

        /* Animated horizontal scan line */
        .qr-scan-line {
            position: absolute;
            left: 14px;
            right: 14px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #6366f1, #8b5cf6, #6366f1, transparent);
            border-radius: 2px;
            z-index: 12;
            top: 14px;
            animation: scan-sweep 2.5s ease-in-out infinite;
            box-shadow: 0 0 12px 2px rgba(99, 102, 241, 0.6);
            pointer-events: none;
        }

        @keyframes scan-sweep {
            0% {
                top: 14px;
                opacity: 0;
            }

            5% {
                opacity: 1;
            }

            95% {
                opacity: 1;
            }

            100% {
                top: calc(100% - 16px);
                opacity: 0;
            }
        }

        /* Override html5-qrcode library default styles */
        #reader-{{ $getName() }} {
            border: none !important;
            background: #000 !important;
        }

        #reader-{{ $getName() }} * {
            font-family: inherit !important;
        }

        #reader-{{ $getName() }} video {
            border-radius: 10px !important;
            width: 100% !important;
        }

        #reader-{{ $getName() }} img[src*="scan"] {
            display: none !important;
        }

        #reader-{{ $getName() }} #{{ $getName() }}__scan_region {
            background: #000 !important;
            border-radius: 10px !important;
        }

        #reader-{{ $getName() }} #{{ $getName() }}__dashboard {
            padding: 10px 12px !important;
            background: rgba(30, 41, 59, 0.95) !important;
            border-top: 1px solid rgba(99, 102, 241, 0.2) !important;
        }

        #reader-{{ $getName() }} #{{ $getName() }}__dashboard button,
        #reader-{{ $getName() }} button {
            background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
            color: #fff !important;
            border: none !important;
            padding: 7px 16px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            letter-spacing: 0.02em !important;
            text-transform: uppercase !important;
        }

        #reader-{{ $getName() }} select {
            background: rgba(30, 41, 59, 0.9) !important;
            color: #e2e8f0 !important;
            border: 1px solid rgba(99, 102, 241, 0.3) !important;
            border-radius: 8px !important;
            padding: 6px 10px !important;
            font-size: 12px !important;
        }

        #reader-{{ $getName() }} #{{ $getName() }}__status_span {
            color: #94a3b8 !important;
            font-size: 11px !important;
        }

        .qr-instruction {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px 14px;
            color: #94a3b8;
            font-size: 12px;
        }

        .qr-instruction svg {
            flex-shrink: 0;
            color: #6366f1;
        }

        /* Success overlay */
        .qr-success-overlay {
            position: absolute;
            inset: 0;
            background: rgba(16, 185, 129, 0.1);
            backdrop-filter: blur(2px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            z-index: 20;
            border-radius: 14px;
        }

        .qr-success-icon {
            width: 56px;
            height: 56px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pop-in 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.5);
        }

        @keyframes pop-in {
            0% {
                transform: scale(0.4);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .qr-success-text {
            color: #d1fae5;
            font-size: 14px;
            font-weight: 600;
        }
    </style>

    <div xmlns:x-filament="http://www.w3.org/1999/html" x-load-js="['{{ config('filament-qrcode-field.asset_js') }}']"
        x-on:close-modal.window="stopScanning()" x-on:open-modal.window="startCameraWhenReady()" x-data="{
            html5QrcodeScanner: null,
            isStarting: false,
            scanStatus: 'idle',
            stopScanning() {
                if (!this.html5QrcodeScanner) return;
                this.html5QrcodeScanner.pause();
                this.html5QrcodeScanner.clear();
                this.html5QrcodeScanner = null;
                this.scanStatus = 'idle';
            },
            onScanSuccess(decodedText, decodedResult) {
                this.scanStatus = 'success';
                $wire.set('{{ $getStatePath() }}', decodedText);
            },
            startCameraWhenReady() {
                if (this.html5QrcodeScanner || this.isStarting || {{ $isDisabled ? 'true' : 'false' }}) {
                    return;
                }
                this.isStarting = true;
                this.scanStatus = 'scanning';
                const tryStart = () => {
                    if (typeof Html5QrcodeScanner === 'undefined') {
                        setTimeout(tryStart, 150);
                        return;
                    }
                    this.html5QrcodeScanner = new Html5QrcodeScanner(
                        'reader-{{ $getName() }}',
                        {
                            fps: {{ config('filament-qrcode-field.scanner.fps') }},
                            qrbox: {
                                width: {{ config('filament-qrcode-field.scanner.width') }},
                                height: {{ config('filament-qrcode-field.scanner.height') }}
                            },
                            formatsToSupport: [0],
                            showTorchButtonIfSupported: true,
                            showZoomSliderIfSupported: true,
                        },
                        false
                    );
                    this.html5QrcodeScanner.render(this.onScanSuccess.bind(this));
                    this.isStarting = false;
                };
                tryStart();
            }
        }" x-init="startCameraWhenReady()">
        <div class="qr-scanner-wrapper" {{ prepare_inherited_attributes($extraAttributeBag)->class([]) }}>
            <div class="qr-scanner-card">

                {{-- Header --}}
                <div class="qr-scanner-header">
                    <div class="qr-scanner-icon">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75V16.5zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 18.75h.75v.75h-.75v-.75zM16.5 13.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 18.75h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                        </svg>
                    </div>
                    <div>
                        <p class="qr-scanner-title">QR Code Scanner</p>
                        <p class="qr-scanner-subtitle">Point camera at booking QR</p>
                    </div>
                    <div class="qr-status-badge" :class="scanStatus">
                        <div class="qr-status-dot"></div>
                        <span
                            x-text="scanStatus === 'scanning' ? 'Live' : scanStatus === 'success' ? 'Found' : 'Idle'"></span>
                    </div>
                </div>

                {{-- Viewport --}}
                <div class="qr-viewport-wrapper">
                    <div class="qr-corner-tl"></div>
                    <div class="qr-corner-tr"></div>
                    <div class="qr-corner-bl"></div>
                    <div class="qr-corner-br"></div>
                    <div class="qr-scan-line" x-show="scanStatus === 'scanning'"></div>

                    <div id="reader-{{ $getName() }}" width="{{ config('filament-qrcode-field.reader.width') }}"
                        height="{{ config('filament-qrcode-field.reader.height') }}"></div>

                    {{-- Success overlay --}}
                    <div class="qr-success-overlay" x-show="scanStatus === 'success'" x-transition>
                        <div class="qr-success-icon">
                            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="white"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <p class="qr-success-text">Booking found! Redirecting…</p>
                    </div>
                </div>

                {{-- Instructions --}}
                <div class="qr-instruction">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    Hold the booking QR code steady within the camera frame to scan automatically.
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>