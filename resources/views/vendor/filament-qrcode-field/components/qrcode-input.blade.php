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
        /* ── Clean minimal scanner ── */
        .qr-scan-root {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .qr-scan-container {
            position: relative;
            width: 100%;
            max-width: 520px;
            border-radius: 16px;
            overflow: hidden;
            background: #0a0a0a;
            border: 1px solid rgba(125, 155, 105, 0.25);
        }

        /* Full width on mobile */
        @media (max-width: 640px) {
            .qr-scan-container {
                max-width: 100%;
                border-radius: 12px;
            }
        }

        /* ── Video area ── */
        .qr-scan-video-area {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            background: #000;
            overflow: hidden;
        }

        @media (max-width: 640px) {
            .qr-scan-video-area {
                aspect-ratio: 3 / 4;
            }
        }

        /* ── Scan frame overlay ── */
        .qr-scan-frame {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 280px;
            height: 280px;
            z-index: 5;
            pointer-events: none;
        }

        @media (max-width: 640px) {
            .qr-scan-frame {
                width: 260px;
                height: 260px;
            }
        }

        .qr-scan-frame-corner {
            position: absolute;
            width: 28px;
            height: 28px;
        }

        .qr-scan-frame-corner.tl {
            top: 0; left: 0;
            border-top: 3px solid #016730;
            border-left: 3px solid #016730;
            border-radius: 6px 0 0 0;
        }
        .qr-scan-frame-corner.tr {
            top: 0; right: 0;
            border-top: 3px solid #016730;
            border-right: 3px solid #016730;
            border-radius: 0 6px 0 0;
        }
        .qr-scan-frame-corner.bl {
            bottom: 0; left: 0;
            border-bottom: 3px solid #016730;
            border-left: 3px solid #016730;
            border-radius: 0 0 0 6px;
        }
        .qr-scan-frame-corner.br {
            bottom: 0; right: 0;
            border-bottom: 3px solid #016730;
            border-right: 3px solid #016730;
            border-radius: 0 0 6px 0;
        }

        /* ── Scan laser ── */
        .qr-scan-laser {
            position: absolute;
            left: 8px;
            right: 8px;
            height: 2px;
            background: linear-gradient(90deg, transparent 0%, #016730 30%, #1f5d1e 50%, #016730 70%, transparent 100%);
            z-index: 6;
            top: 0;
            animation: qr-laser-move 2s ease-in-out infinite;
            box-shadow: 0 0 8px 1px rgba(1, 103, 48, 0.5);
            pointer-events: none;
        }

        @keyframes qr-laser-move {
            0%   { top: 0;   opacity: 0; }
            5%   { opacity: 1; }
            95%  { opacity: 1; }
            100% { top: calc(100% - 2px); opacity: 0; }
        }

        /* ── Dim overlay outside frame ── */
        .qr-scan-dim {
            position: absolute;
            inset: 0;
            z-index: 4;
            pointer-events: none;
            box-shadow: inset 0 0 0 9999px rgba(0,0,0,0.45);
        }

        /* ── Bottom bar ── */
        .qr-scan-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #111;
            border-top: 1px solid rgba(125, 155, 105, 0.15);
        }

        .qr-scan-bar-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .qr-scan-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .qr-scan-dot.live {
            background: #016730;
            animation: qr-dot-pulse 1.4s ease-in-out infinite;
        }
        .qr-scan-dot.idle {
            background: #555;
        }
        .qr-scan-dot.found {
            background: #f4c95d;
        }

        @keyframes qr-dot-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.35; }
        }

        .qr-scan-label {
            font-size: 13px;
            font-weight: 500;
            color: #ccc;
            font-family: inherit;
        }

        .qr-scan-hint {
            font-size: 11px;
            color: #777;
            font-family: inherit;
        }

        /* ── Success overlay ── */
        .qr-scan-success {
            position: absolute;
            inset: 0;
            z-index: 20;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
        }

        .qr-scan-success-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #016730;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: qr-pop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 0 40px rgba(1, 103, 48, 0.5);
        }

        @keyframes qr-pop {
            0%   { transform: scale(0.3); opacity: 0; }
            100% { transform: scale(1);   opacity: 1; }
        }

        .qr-scan-success-text {
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
        }

        /* ── Override html5-qrcode internals ── */
        #reader-{{ $getName() }} {
            border: none !important;
            background: transparent !important;
            width: 100% !important;
        }
        #reader-{{ $getName() }} * {
            font-family: inherit !important;
        }
        #reader-{{ $getName() }} video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 0 !important;
        }
        #reader-{{ $getName() }} img[src*="scan"] {
            display: none !important;
        }
        #reader-{{ $getName() }} #{{ $getName() }}__scan_region {
            background: transparent !important;
            min-height: 0 !important;
        }
        #reader-{{ $getName() }} #{{ $getName() }}__scan_region > img {
            display: none !important;
        }
        /* Hide the library dashboard entirely – we have our own UI */
        #reader-{{ $getName() }} #{{ $getName() }}__dashboard {
            display: none !important;
        }
        #reader-{{ $getName() }} #{{ $getName() }}__dashboard_section,
        #reader-{{ $getName() }} #{{ $getName() }}__dashboard_section_swaplink,
        #reader-{{ $getName() }} #{{ $getName() }}__status_span,
        #reader-{{ $getName() }} #{{ $getName() }}__header_message {
            display: none !important;
        }
    </style>

    <div xmlns:x-filament="http://www.w3.org/1999/html" x-load-js="['{{ config('filament-qrcode-field.asset_js') }}']"
        x-on:close-modal.window="stopScanning()"
        x-on:open-modal.window="startCameraWhenReady()"
        x-on:modal-closed.window="stopScanning()"
        x-data="{
            html5QrcodeScanner: null,
            isStarting: false,
            shouldScan: false,
            startTimer: null,
            scanStatus: 'idle',

            clearStartTimer() {
                if (this.startTimer !== null) {
                    clearTimeout(this.startTimer);
                    this.startTimer = null;
                }
            },

            queueStart(delay = 120) {
                this.clearStartTimer();
                this.startTimer = setTimeout(() => {
                    this.startCameraWhenReady();
                }, delay);
            },

            stopScanning() {
                this.shouldScan = false;
                this.clearStartTimer();

                if (!this.html5QrcodeScanner) return;
                const scanner = this.html5QrcodeScanner;
                this.html5QrcodeScanner = null;
                this.scanStatus = 'idle';
                this.isStarting = false;

                try {
                    const state = scanner.getState();
                    if (state === Html5QrcodeScannerState.SCANNING || state === Html5QrcodeScannerState.PAUSED) {
                        scanner.stop().then(() => {
                            try { scanner.clear(); } catch(e) {}
                        }).catch(() => {
                            try { scanner.clear(); } catch(e) {}
                        });
                    } else {
                        try { scanner.clear(); } catch(e) {}
                    }
                } catch (e) {
                    try { scanner.clear(); } catch(e2) {}
                }
            },

            onScanSuccess(decodedText) {
                this.scanStatus = 'success';
                this.stopScanning();
                $wire.set('{{ $getStatePath() }}', decodedText);
            },

            startCameraWhenReady() {
                if ({{ $isDisabled ? 'true' : 'false' }}) {
                    return;
                }

                if (!this.shouldScan) {
                    this.shouldScan = true;
                }

                if (this.html5QrcodeScanner || this.isStarting) {
                    return;
                }

                this.isStarting = true;
                this.scanStatus = 'scanning';

                const tryStart = () => {
                    if (!this.shouldScan) {
                        this.isStarting = false;
                        this.scanStatus = 'idle';
                        return;
                    }

                    if (typeof Html5Qrcode === 'undefined') {
                        this.startTimer = setTimeout(tryStart, 150);
                        return;
                    }

                    const readerId = 'reader-{{ $getName() }}';
                    const el = document.getElementById(readerId);
                    if (!el || !el.offsetParent) {
                        this.startTimer = setTimeout(tryStart, 150);
                        return;
                    }

                    this.html5QrcodeScanner = new Html5Qrcode(readerId);

                    this.html5QrcodeScanner.start(
                        { facingMode: 'environment' },
                        {
                            fps: {{ config('filament-qrcode-field.scanner.fps', 15) }},
                            qrbox: function(viewfinderWidth, viewfinderHeight) {
                                let size = Math.min(viewfinderWidth, viewfinderHeight) * 0.7;
                                return { width: Math.floor(size), height: Math.floor(size) };
                            },
                            aspectRatio: window.innerWidth < 640 ? 0.75 : 1.333,
                            disableFlip: false,
                        },
                        this.onScanSuccess.bind(this),
                        () => {}
                    ).then(() => {
                        this.clearStartTimer();
                        this.isStarting = false;
                    }).catch(err => {
                        console.error('Scanner start error:', err);
                        this.clearStartTimer();
                        this.html5QrcodeScanner = null;
                        this.isStarting = false;
                        this.scanStatus = 'idle';

                        if (this.shouldScan) {
                            // Permission prompts and modal transitions can race; retry briefly.
                            this.queueStart(300);
                        }
                    });
                };
                tryStart();
            }
        }" x-init="shouldScan = true; queueStart(120)">

        <div class="qr-scan-root" {{ prepare_inherited_attributes($extraAttributeBag)->class([]) }}>
            <div class="qr-scan-container">

                {{-- Camera viewport --}}
                <div class="qr-scan-video-area">
                    <div id="reader-{{ $getName() }}"></div>

                    {{-- Scan frame --}}
                    <div class="qr-scan-frame" x-show="scanStatus === 'scanning'">
                        <div class="qr-scan-frame-corner tl"></div>
                        <div class="qr-scan-frame-corner tr"></div>
                        <div class="qr-scan-frame-corner bl"></div>
                        <div class="qr-scan-frame-corner br"></div>
                        <div class="qr-scan-laser"></div>
                    </div>

                    {{-- Success overlay --}}
                    <div class="qr-scan-success" x-show="scanStatus === 'success'" x-transition>
                        <div class="qr-scan-success-circle">
                            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <p class="qr-scan-success-text">Booking found!</p>
                    </div>
                </div>

                {{-- Bottom info bar --}}
                <div class="qr-scan-bar">
                    <div class="qr-scan-bar-left">
                        <div class="qr-scan-dot"
                             :class="scanStatus === 'scanning' ? 'live' : scanStatus === 'success' ? 'found' : 'idle'"></div>
                        <span class="qr-scan-label"
                              x-text="scanStatus === 'scanning' ? 'Scanning…' : scanStatus === 'success' ? 'QR Found' : 'Camera off'"></span>
                    </div>
                    <span class="qr-scan-hint" x-show="scanStatus === 'scanning'">Hold QR code steady</span>
                </div>

            </div>
        </div>
    </div>
</x-dynamic-component>