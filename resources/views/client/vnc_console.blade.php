<!DOCTYPE html>
<html lang="en">
<head>
    <title>VNC Console — Service #{{ $serviceId }}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; background: #0f172a; color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; overflow: hidden; }

        /* ── Header Bar ─────────────────────────────────── */
        #vnc-header {
            background: #1e293b;
            padding: 0 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #334155;
            height: 44px;
            flex-shrink: 0;
            user-select: none;
        }
        #vnc-header .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
        }
        #vnc-header .brand svg { color: #818cf8; }
        #vnc-header .controls { display: flex; align-items: center; gap: 8px; }

        #status-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 9999px;
            background: #1e3a5f;
            color: #60a5fa;
            letter-spacing: 0.02em;
            transition: background 0.2s, color 0.2s;
        }
        #status-pill.connected  { background: #14532d; color: #4ade80; }
        #status-pill.error      { background: #450a0a; color: #f87171; }
        #status-pill.connecting { background: #1e3a5f; color: #60a5fa; }

        .header-btn {
            background: #334155;
            color: #e2e8f0;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.15s;
        }
        .header-btn:hover   { background: #475569; }

        /* ── Screen ─────────────────────────────────────── */
        #screen-wrap {
            height: calc(100vh - 44px);
            width: 100%;
            position: relative;
            background: #020617;
        }
        #vnc-iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #020617;
        }

        /* ── Loading overlay ────────────────────────────── */
        #loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            background: #020617;
            z-index: 10;
            transition: opacity 0.3s;
        }
        #loading.hidden { opacity: 0; pointer-events: none; }
        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid #334155;
            border-top-color: #818cf8;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        #loading p { font-size: 13px; color: #94a3b8; }

        /* ── Error overlay ──────────────────────────────── */
        #error-overlay {
            display: none;
            position: absolute;
            inset: 0;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: #020617;
            z-index: 10;
        }
        #error-overlay.visible { display: flex; }
        #error-overlay h3 { color: #f87171; font-size: 16px; }
        #error-overlay p  { color: #94a3b8; font-size: 13px; max-width: 360px; text-align: center; }
    </style>
</head>
<body>
    <!-- Header -->
    <div id="vnc-header">
        <div class="brand">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            VNC Console — VPS {{ $vpsId }}
        </div>
        <div class="controls">
            <span id="status-pill" class="connecting">Loading…</span>
            <button class="header-btn" title="Fullscreen" onclick="toggleFullscreen()">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                </svg>
                Fullscreen
            </button>
            <button class="header-btn" onclick="window.location.reload()">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Reload
            </button>
        </div>
    </div>

    <!-- Screen area -->
    <div id="screen-wrap">
        <!-- Loading overlay -->
        <div id="loading">
            <div class="spinner"></div>
            <p>Loading VNC console…</p>
        </div>

        <!-- Error overlay -->
        <div id="error-overlay">
            <svg width="40" height="40" fill="none" stroke="#f87171" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h3>VNC Failed to Load</h3>
            <p id="error-detail">The VNC console could not be loaded. Please try again.</p>
            <button onclick="window.location.reload()" style="margin-top:12px;background:#4f46e5;color:white;border:none;padding:8px 24px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600">
                Retry
            </button>
        </div>

        <!-- Iframe to WHMCS noVNC (via orcus_sso.php signed proxy) -->
        <iframe id="vnc-iframe" src="{{ $vncProxyUrl }}" allowfullscreen></iframe>
    </div>

    <script>
        const iframe    = document.getElementById('vnc-iframe');
        const loading   = document.getElementById('loading');
        const errorEl   = document.getElementById('error-overlay');
        const statusPill = document.getElementById('status-pill');

        // Hide loading when iframe loads
        iframe.addEventListener('load', function () {
            loading.classList.add('hidden');
            statusPill.textContent = 'Connected';
            statusPill.className   = 'connected';
        });

        // If iframe doesn't load in 15 seconds, show error
        setTimeout(function () {
            if (!loading.classList.contains('hidden')) {
                loading.style.display = 'none';
                errorEl.classList.add('visible');
                statusPill.textContent = 'Failed';
                statusPill.className   = 'error';
            }
        }, 15000);

        // Fullscreen toggle
        function toggleFullscreen() {
            const el = document.getElementById('screen-wrap');
            if (!document.fullscreenElement) {
                el.requestFullscreen().catch(function(){});
            } else {
                document.exitFullscreen();
            }
        }
    </script>
</body>
</html>
