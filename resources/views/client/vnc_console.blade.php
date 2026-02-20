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
        .header-btn.danger  { background: #7f1d1d; color: #fca5a5; }
        .header-btn.danger:hover { background: #991b1b; }
        .header-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        /* ── Screen ─────────────────────────────────────── */
        #screen-wrap {
            height: calc(100vh - 44px);
            width: 100%;
            position: relative;
            background: #020617;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #screen { width: 100%; height: 100%; }
        #screen canvas { display: block; }

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
        }
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
        #retry-btn {
            margin-top: 8px;
            background: #4f46e5;
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }
        #retry-btn:hover { background: #4338ca; }
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
            VNC Console
        </div>
        <div class="controls">
            <span id="status-pill" class="connecting">Connecting…</span>
            <button class="header-btn" id="fullscreen-btn" title="Fullscreen" onclick="toggleFullscreen()">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                </svg>
                Fullscreen
            </button>
            <button class="header-btn danger" id="cad-btn" title="Send Ctrl+Alt+Del" disabled>
                Ctrl+Alt+Del
            </button>
        </div>
    </div>

    <!-- Screen area -->
    <div id="screen-wrap">
        <div id="screen"></div>

        <!-- Loading overlay -->
        <div id="loading">
            <div class="spinner"></div>
            <p>Connecting to VNC…</p>
        </div>

        <!-- Error overlay -->
        <div id="error-overlay">
            <svg width="40" height="40" fill="none" stroke="#f87171" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h3>Connection Failed</h3>
            <p id="error-detail">Unable to connect to the VNC server.</p>
            <div style="display:flex;gap:8px;margin-top:12px">
                <button id="retry-btn" onclick="window.location.reload()" style="background:#4f46e5;color:white;border:none;padding:8px 24px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600">Retry Connection</button>
                @if(!empty($ssoUrl))
                <a href="{{ $ssoUrl }}" target="_self" style="background:#334155;color:#e2e8f0;border:none;padding:8px 24px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center">Open in Virtualizor Panel</a>
                @endif
            </div>
        </div>
    </div>

    <!-- noVNC -->
    <script src="/novnc/vendor/promise.js"></script>
    <script type="module">
        import RFB from '/novnc/core/rfb.js';

        // ── Credentials (filled server-side) ──────────────
        const host     = @json($host);       // Virtualizor hostname (e.g. elina.noc01.com)
        const port     = @json($port);       // 4083 — enduser panel port (websockify proxy)
        const password = @json($password);   // VNC password
        const vpsid    = @json($vpsid);      // VPS ID
        const ssoUrl   = @json($ssoUrl ?? '');

        // ── DOM refs ────────────────────────────────────────
        const statusPill  = document.getElementById('status-pill');
        const loadingEl   = document.getElementById('loading');
        const errorEl     = document.getElementById('error-overlay');
        const errorDetail = document.getElementById('error-detail');
        const cadBtn      = document.getElementById('cad-btn');

        function setStatus(text, state) {
            statusPill.textContent  = text;
            statusPill.className    = state;
        }

        function showError(msg) {
            loadingEl.style.display   = 'none';
            errorEl.classList.add('visible');
            errorDetail.textContent   = msg;
            setStatus('Disconnected', 'error');
            cadBtn.disabled = true;
        }

        // ── Redirect to SSO VNC if direct WebSocket fails ──
        let wsAttempted = false;
        function fallbackToSso() {
            if (ssoUrl && !wsAttempted) {
                wsAttempted = true;
                // Direct WebSocket failed — redirect to Virtualizor's built-in noVNC via SSO
                window.location.href = ssoUrl;
                return;
            }
        }

        // ── Build WebSocket URL ─────────────────────────────
        // Virtualizor runs a websockify proxy on the enduser panel port
        // URL format: wss://hostname:4083/novnc/websockify?token=VPSID
        // Also try: wss://hostname:4083/novnc/?virttoken=VPSID
        const wsUrl = `wss://${host}:${port}/novnc/websockify?token=${encodeURIComponent(vpsid)}`;

        console.log('[VNC] Connecting to:', wsUrl);
        console.log('[VNC] Host:', host, 'Port:', port, 'VPS:', vpsid, 'Pass:', password ? '***' : '(empty)');

        // ── Connect ─────────────────────────────────────────
        let rfb;
        let connectTimeout;
        try {
            rfb = new RFB(
                document.getElementById('screen'),
                wsUrl,
                { credentials: { password: password } }
            );

            rfb.scaleViewport  = true;
            rfb.resizeSession  = false;

            // If not connected within 8 seconds, try SSO fallback
            connectTimeout = setTimeout(() => {
                console.log('[VNC] Connection timeout — falling back to SSO');
                fallbackToSso();
            }, 8000);

            rfb.addEventListener('connect', () => {
                clearTimeout(connectTimeout);
                loadingEl.style.display = 'none';
                setStatus('Connected', 'connected');
                cadBtn.disabled = false;
            });

            rfb.addEventListener('disconnect', (e) => {
                clearTimeout(connectTimeout);
                cadBtn.disabled = true;
                if (e.detail.clean) {
                    setStatus('Disconnected', '');
                } else {
                    // Try SSO fallback on disconnect
                    if (!wsAttempted && ssoUrl) {
                        console.log('[VNC] Disconnected — falling back to SSO');
                        fallbackToSso();
                    } else {
                        showError('The VNC connection was lost. The WebSocket proxy may require authentication.');
                    }
                }
            });

            rfb.addEventListener('credentialsrequired', () => {
                if (password) {
                    rfb.sendCredentials({ password: password });
                } else {
                    const pass = prompt('VNC Password Required:');
                    if (pass) {
                        rfb.sendCredentials({ password: pass });
                    } else {
                        showError('VNC password is required to connect.');
                    }
                }
            });

            // Ctrl+Alt+Del button
            cadBtn.addEventListener('click', () => rfb.sendCtrlAltDel());

        } catch (err) {
            clearTimeout(connectTimeout);
            console.error('[VNC] Error:', err);
            // Try SSO fallback
            if (ssoUrl) {
                fallbackToSso();
            } else {
                showError(err.message || 'Failed to initialise VNC client.');
            }
        }

        // ── Fullscreen ───────────────────────────────────────
        window.toggleFullscreen = function () {
            const el = document.getElementById('screen-wrap');
            if (!document.fullscreenElement) {
                el.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen();
            }
        };
    </script>
</body>
</html>
