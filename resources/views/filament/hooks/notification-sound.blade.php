@if (filament()->auth()->check())
    @php
        $user = filament()->auth()->user();
        $broadcastChannel = null;
        if ($user) {
            $broadcastChannel = method_exists($user, 'receivesBroadcastNotificationsOn')
                ? $user->receivesBroadcastNotificationsOn()
                : str_replace('\\', '.', $user::class) . '.' . $user->getKey();
        }
    @endphp
    @if (filled($broadcastChannel))
        <script>
            (function () {
                const channel = @js($broadcastChannel);

                if (window.__marcelinosNotificationSoundInit) {
                    return;
                }
                window.__marcelinosNotificationSoundInit = true;

                let audioCtx = null;
                let lastChimeAt = 0;
                let lastEchoChimeAt = 0;

                function getAudioContext() {
                    const Ctor = window.AudioContext || window.webkitAudioContext;
                    if (!Ctor) {
                        return null;
                    }
                    if (!audioCtx) {
                        audioCtx = new Ctor();
                    }
                    return audioCtx;
                }

                function unlockAudio() {
                    const ctx = getAudioContext();
                    if (ctx && ctx.state === 'suspended') {
                        ctx.resume().catch(function () {});
                    }
                }

                document.addEventListener('click', unlockAudio, { capture: true });
                document.addEventListener('keydown', unlockAudio, { capture: true });

                function playChime() {
                    if (Date.now() - lastChimeAt < 1200) {
                        return;
                    }
                    lastChimeAt = Date.now();

                    const ctx = getAudioContext();
                    if (!ctx) {
                        return;
                    }

                    function ring() {
                        if (ctx.state !== 'running') {
                            return;
                        }
                        try {
                            const t = ctx.currentTime;
                            const peak = 0.38;

                            function sharpBeep(start, freqHz, durationSec) {
                                const osc = ctx.createOscillator();
                                const g = ctx.createGain();
                                osc.type = 'square';
                                osc.frequency.setValueAtTime(freqHz, start);
                                osc.connect(g);
                                g.connect(ctx.destination);
                                g.gain.setValueAtTime(0, start);
                                g.gain.linearRampToValueAtTime(peak, start + 0.004);
                                g.gain.linearRampToValueAtTime(0, start + durationSec);
                                osc.start(start);
                                osc.stop(start + durationSec + 0.015);
                            }

                            sharpBeep(t, 2200, 0.07);
                            sharpBeep(t + 0.1, 3000, 0.08);
                        } catch (e) {}
                    }

                    if (ctx.state === 'suspended') {
                        ctx.resume().then(ring).catch(function () {});
                        return;
                    }
                    ring();
                }

                let subscribed = false;

                function subscribeEcho() {
                    if (subscribed || !window.Echo) {
                        return;
                    }
                    try {
                        window.Echo.private(channel).listen(
                            '.filament-notification.sound',
                            function () {
                                lastEchoChimeAt = Date.now();
                                playChime();
                            },
                        );
                        subscribed = true;
                    } catch (e) {}
                }

                window.addEventListener('EchoLoaded', subscribeEcho);
                if (window.Echo) {
                    subscribeEcho();
                }

                let echoRetries = 0;
                const echoRetryTimer = window.setInterval(function () {
                    if (subscribed || echoRetries > 160) {
                        window.clearInterval(echoRetryTimer);
                        return;
                    }
                    echoRetries++;
                    if (window.Echo) {
                        subscribeEcho();
                    }
                }, 75);

                function readUnreadBadge() {
                    const btn = document.querySelector('.fi-topbar-database-notifications-btn');
                    if (!btn) {
                        return null;
                    }
                    const badge = btn.querySelector('.fi-badge');
                    if (!badge) {
                        return 0;
                    }
                    const n = parseInt(badge.textContent.trim(), 10);
                    return Number.isFinite(n) ? n : 0;
                }

                let lastBadgeCount = null;

                function calibrateBadge() {
                    const n = readUnreadBadge();
                    if (n !== null) {
                        lastBadgeCount = n;
                    }
                }

                function checkBadgeDelta() {
                    const n = readUnreadBadge();
                    if (n === null) {
                        return;
                    }
                    if (lastBadgeCount === null) {
                        lastBadgeCount = n;
                        return;
                    }
                    if (n > lastBadgeCount) {
                        if (Date.now() - lastEchoChimeAt < 4500) {
                            lastBadgeCount = n;
                            return;
                        }
                        playChime();
                    }
                    lastBadgeCount = n;
                }

                calibrateBadge();

                document.addEventListener('livewire:navigated', function () {
                    lastBadgeCount = null;
                    calibrateBadge();
                });

                function registerLivewireBadgeHook() {
                    if (!window.Livewire || typeof window.Livewire.hook !== 'function') {
                        return;
                    }
                    window.Livewire.hook('morph.updated', function () {
                        window.requestAnimationFrame(checkBadgeDelta);
                    });
                }

                if (window.Livewire) {
                    registerLivewireBadgeHook();
                } else {
                    document.addEventListener('livewire:init', registerLivewireBadgeHook);
                }

                window.setInterval(checkBadgeDelta, 300);
            })();
        </script>
    @endif
@endif
