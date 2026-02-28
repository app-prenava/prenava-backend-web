<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Tester — SEHATI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #161822;
            --bg-card: #1a1d2e;
            --bg-input: #12141f;
            --border-color: #252a3a;
            --accent-blue: #3b82f6;
            --accent-green: #22c55e;
            --accent-orange: #f59e0b;
            --accent-red: #ef4444;
            --accent-purple: #8b5cf6;
            --text-primary: #e2e8f0;
            --text-muted: #94a3b8;
            --radius: 12px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 280px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            z-index: 100;
            padding-bottom: 2rem;
        }

        .sidebar-brand {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: .6rem;
            color: var(--accent-blue);
        }

        .sidebar-section {
            padding: .75rem 1.5rem .25rem;
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--text-muted);
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .55rem 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            font-size: .85rem;
            font-weight: 500;
            transition: all 150ms;
            border-left: 3px solid transparent;
            border-right: none;
            border-top: none;
            border-bottom: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .sidebar-item:hover { background: rgba(59, 130, 246, .06); color: var(--text-primary); }
        .sidebar-item.active { background: rgba(59, 130, 246, .1); color: var(--accent-blue); border-left-color: var(--accent-blue); }

        .method-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .6rem;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            padding: .15rem .35rem;
            border-radius: 4px;
            min-width: 34px;
            text-transform: uppercase;
        }
        .method-get { background: rgba(34, 197, 94, .15); color: var(--accent-green); }
        .method-post { background: rgba(59, 130, 246, .15); color: var(--accent-blue); }
        .method-put { background: rgba(245, 158, 11, .15); color: var(--accent-orange); }
        .method-patch { background: rgba(139, 92, 246, .15); color: var(--accent-purple); }
        .method-delete { background: rgba(239, 68, 68, .15); color: var(--accent-red); }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        .card-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .card-panel-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .card-panel-body { padding: 1.25rem; }

        .token-bar {
            display: flex;
            gap: .75rem;
            align-items: stretch;
        }

        .token-bar input {
            flex: 1;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: .6rem 1rem;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
            font-size: .82rem;
        }
        .token-bar input:focus { outline: none; border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
        .token-bar input::placeholder { color: var(--text-muted); }

        .btn-primary-custom {
            background: var(--accent-blue);
            color: white;
            border: none;
            padding: .6rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: .85rem;
            cursor: pointer;
            transition: all 150ms;
            white-space: nowrap;
        }
        .btn-primary-custom:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-primary-custom:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        .request-url {
            display: flex;
            gap: .5rem;
            align-items: stretch;
        }

        .request-url select {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: .6rem .75rem;
            color: var(--accent-green);
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: .82rem;
            width: 100px;
        }
        .request-url select:focus { outline: none; border-color: var(--accent-blue); }

        .request-url input {
            flex: 1;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: .6rem 1rem;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
            font-size: .85rem;
        }
        .request-url input:focus { outline: none; border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }

        .body-editor {
            width: 100%;
            min-height: 160px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
            font-size: .82rem;
            resize: vertical;
        }
        .body-editor:focus { outline: none; border-color: var(--accent-blue); }

        .response-panel {
            position: relative;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .75rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
        }
        .status-2xx { background: rgba(34, 197, 94, .12); color: var(--accent-green); }
        .status-4xx { background: rgba(245, 158, 11, .12); color: var(--accent-orange); }
        .status-5xx { background: rgba(239, 68, 68, .12); color: var(--accent-red); }
        .status-loading { background: rgba(59, 130, 246, .12); color: var(--accent-blue); }

        .response-output {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: .8rem;
            max-height: 500px;
            overflow: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.5;
            color: #a5d6ff;
        }

        .time-badge {
            font-size: .72rem;
            color: var(--text-muted);
            font-family: 'JetBrains Mono', monospace;
        }

        .tab-pills {
            display: flex;
            gap: 2px;
            background: var(--bg-input);
            padding: 3px;
            border-radius: 8px;
        }

        .tab-pill {
            padding: .4rem .8rem;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: .8rem;
            font-weight: 500;
            cursor: pointer;
            border-radius: 6px;
            transition: all 150ms;
        }
        .tab-pill.active { background: var(--bg-card); color: var(--text-primary); }
        .tab-pill:hover:not(.active) { color: var(--text-primary); }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: .4; }

        .quick-actions { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .75rem; }

        .quick-btn {
            padding: .3rem .6rem;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-muted);
            font-size: .72rem;
            cursor: pointer;
            transition: all 150ms;
            font-family: 'JetBrains Mono', monospace;
        }
        .quick-btn:hover { border-color: var(--accent-blue); color: var(--accent-blue); }

        @media (max-width: 992px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-flask"></i> API Tester
    </div>

    <div class="sidebar-section">Thread / Community</div>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/threads/main', null, 'Thread List')">
        <span class="method-badge method-get">GET</span> <span>Threads List</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/threads/main?search=test', null, 'Thread Search')">
        <span class="method-badge method-get">GET</span> <span>Search Threads</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('POST', '/api/threads/create', JSON.stringify({content:'Test thread dari API Tester',category:'general'}, null, 2), 'Create Thread')">
        <span class="method-badge method-post">POST</span> <span>Create Thread</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('PUT', '/api/threads/update/1', JSON.stringify({content:'Updated content',category:'general'}, null, 2), 'Update Thread')">
        <span class="method-badge method-put">PUT</span> <span>Update Thread</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/threads/detail/1', null, 'Thread Detail')">
        <span class="method-badge method-get">GET</span> <span>Thread Detail</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('POST', '/api/threads/like/1', null, 'Like Thread')">
        <span class="method-badge method-post">POST</span> <span>Like Thread</span>
    </button>

    <div class="sidebar-section">Komunitas</div>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/komunitas', null, 'Komunitas List')">
        <span class="method-badge method-get">GET</span> <span>Komunitas List</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/komunitas/1', null, 'Komunitas Detail')">
        <span class="method-badge method-get">GET</span> <span>Komunitas Detail</span>
    </button>

    <div class="sidebar-section">Saldo & Insentif</div>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/user/saldo', null, 'My Saldo')">
        <span class="method-badge method-get">GET</span> <span>My Saldo Balance</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/user/saldo/history', null, 'Saldo History')">
        <span class="method-badge method-get">GET</span> <span>Saldo History</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/user/incentives/summary', null, 'Incentive Summary')">
        <span class="method-badge method-get">GET</span> <span>Incentive Summary</span>
    </button>

    <div class="sidebar-section">Appointment — User</div>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/user/appointments', null, 'My Appointments')">
        <span class="method-badge method-get">GET</span> <span>My Appointments</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/user/appointments/stats', null, 'Appointment Stats')">
        <span class="method-badge method-get">GET</span> <span>Appointment Stats</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('POST', '/api/user/appointments', JSON.stringify({bidan_id:1,preferred_date:'2026-03-15',preferred_time:'10:00',consent_accepted:true,consent_version:'1.0',shared_fields:{name:true,phone:true}}, null, 2), 'Create Appointment')">
        <span class="method-badge method-post">POST</span> <span>Create Appointment</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('PATCH', '/api/user/appointments/1/reschedule', JSON.stringify({preferred_date:'2026-03-20',preferred_time:'14:00'}, null, 2), 'Reschedule (User)')">
        <span class="method-badge method-patch">PATCH</span> <span>Reschedule</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('PATCH', '/api/user/appointments/1/cancel', JSON.stringify({reason:'Berhalangan hadir'}, null, 2), 'Cancel Appointment')">
        <span class="method-badge method-patch">PATCH</span> <span>Cancel Appointment</span>
    </button>

    <div class="sidebar-section">Appointment — Bidan</div>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/bidan/appointments', null, 'Bidan Appointments')">
        <span class="method-badge method-get">GET</span> <span>Bidan Appointments</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('PATCH', '/api/bidan/appointments/1/accept', JSON.stringify({notes:'Silakan datang tepat waktu'}, null, 2), 'Accept Appointment')">
        <span class="method-badge method-patch">PATCH</span> <span>Accept</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('PATCH', '/api/bidan/appointments/1/reject', JSON.stringify({reason:'Jadwal penuh'}, null, 2), 'Reject Appointment')">
        <span class="method-badge method-patch">PATCH</span> <span>Reject</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('PATCH', '/api/bidan/appointments/1/reschedule', JSON.stringify({confirmed_date:'2026-03-22',confirmed_time:'09:00',notes:'Pindah ke pagi'}, null, 2), 'Reschedule (Bidan)')">
        <span class="method-badge method-patch">PATCH</span> <span>Reschedule</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('PATCH', '/api/bidan/appointments/1/complete', JSON.stringify({notes:'Pasien sehat'}, null, 2), 'Complete Appointment')">
        <span class="method-badge method-patch">PATCH</span> <span>Complete</span>
    </button>

    <div class="sidebar-section">Bidan Discovery</div>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/user/bidans/locations', null, 'Bidan Locations')">
        <span class="method-badge method-get">GET</span> <span>Bidan Locations</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/user/consent-info', null, 'Consent Info')">
        <span class="method-badge method-get">GET</span> <span>Consent Info</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/user/consultation-types', null, 'Consultation Types')">
        <span class="method-badge method-get">GET</span> <span>Consultation Types</span>
    </button>

    <div class="sidebar-section">Shop</div>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/shop/all', null, 'All Products')">
        <span class="method-badge method-get">GET</span> <span>All Products</span>
    </button>
    <button type="button" class="sidebar-item" onclick="loadEndpoint('GET', '/api/shop', null, 'My Products')">
        <span class="method-badge method-get">GET</span> <span>My Products</span>
    </button>
</aside>

<!-- Main Content -->
<div class="main-content">

    <!-- Auth Token Bar -->
    <div class="card-panel mb-3">
        <div class="card-panel-body">
            <div class="token-bar">
                <input type="text" id="baseUrl" placeholder="Base URL" value="{{ url('/') }}" style="max-width: 240px;">
                <input type="text" id="authToken" placeholder="Paste JWT Token here (Bearer token from /api/auth/login)">
                <button class="btn-primary-custom" onclick="testAuth()">
                    <i class="fas fa-key"></i> Test
                </button>
            </div>
            <div class="quick-actions">
                <span style="color: var(--text-muted); font-size: .72rem; padding: .3rem .3rem;">Quick login:</span>
                <button class="quick-btn" onclick="quickLogin('ibu_hamil')">Login as ibu_hamil</button>
                <button class="quick-btn" onclick="quickLogin('bidan')">Login as bidan</button>
                <button class="quick-btn" onclick="quickLogin('admin')">Login as admin</button>
            </div>
        </div>
    </div>

    <!-- Request Panel -->
    <div class="card-panel mb-3">
        <div class="card-panel-header">
            <i class="fas fa-paper-plane" style="color: var(--accent-blue);"></i>
            <span id="requestTitle">Request</span>
        </div>
        <div class="card-panel-body">
            <div class="request-url mb-3">
                <select id="reqMethod" onchange="updateMethodColor(this)">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="PATCH">PATCH</option>
                    <option value="DELETE">DELETE</option>
                </select>
                <input type="text" id="reqUrl" placeholder="/api/threads/main">
                <button class="btn-primary-custom" id="sendBtn" onclick="sendRequest()">
                    <i class="fas fa-bolt"></i> Send
                </button>
            </div>

            <div class="tab-pills mb-3">
                <button class="tab-pill active" onclick="switchTab(this, 'bodyTab')">Body</button>
                <button class="tab-pill" onclick="switchTab(this, 'headersTab')">Headers</button>
                <button class="tab-pill" onclick="switchTab(this, 'paramsTab')">Params</button>
            </div>

            <div id="bodyTab">
                <textarea class="body-editor" id="reqBody" placeholder='{ "key": "value" }'></textarea>
            </div>
            <div id="headersTab" style="display:none">
                <textarea class="body-editor" id="reqHeaders" placeholder='Custom headers (JSON), e.g.: { "X-Custom": "value" }'></textarea>
            </div>
            <div id="paramsTab" style="display:none">
                <textarea class="body-editor" id="reqParams" placeholder='Query params (JSON), e.g.: { "page": 1, "search": "test" }'></textarea>
            </div>
        </div>
    </div>

    <!-- Response Panel -->
    <div class="card-panel response-panel" id="responsePanel">
        <div class="card-panel-header" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: .5rem;">
                <i class="fas fa-arrow-down" style="color: var(--accent-green);"></i>
                <span>Response</span>
                <span class="status-pill" id="statusPill" style="display: none;"></span>
            </div>
            <span class="time-badge" id="timeBadge"></span>
        </div>
        <div class="card-panel-body">
            <div id="responseEmpty" class="empty-state">
                <i class="fas fa-satellite-dish"></i>
                <p>Click on an endpoint from the sidebar or press <strong>Send</strong> to make a request.</p>
            </div>
            <pre class="response-output" id="responseOutput" style="display: none;"></pre>
        </div>
    </div>
</div>

<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" data-bs-theme="dark">
    <div class="modal-dialog">
        <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="modal-header" style="border-bottom-color: var(--border-color);">
                <h5 class="modal-title">Quick Login</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="loginEmail" class="form-label" style="color: var(--text-muted); font-size: .85rem;">Email</label>
                    <input type="email" class="form-control" id="loginEmail" style="background: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);">
                </div>
                <div class="mb-3">
                    <label for="loginPassword" class="form-label" style="color: var(--text-muted); font-size: .85rem;">Password</label>
                    <input type="password" class="form-control" id="loginPassword" style="background: var(--bg-input); border-color: var(--border-color); color: var(--text-primary);">
                </div>
                <div id="loginError" class="text-danger small" style="display:none;"></div>
            </div>
            <div class="modal-footer" style="border-top-color: var(--border-color);">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="doLogin()">
                    <i class="fas fa-sign-in-alt"></i> Login & Get Token
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let loginRole = '';

    function getBase() { return document.getElementById('baseUrl').value.replace(/\/+$/, ''); }
    function getToken() { return document.getElementById('authToken').value.trim(); }

    function loadEndpoint(method, url, body, title) {
        document.getElementById('reqMethod').value = method;
        document.getElementById('reqUrl').value = url;
        document.getElementById('reqBody').value = body || '';
        document.getElementById('requestTitle').textContent = title || 'Request';
        updateMethodColor(document.getElementById('reqMethod'));

        // Highlight active sidebar item
        document.querySelectorAll('.sidebar-item').forEach(el => el.classList.remove('active'));
        event.currentTarget.classList.add('active');
    }

    function updateMethodColor(select) {
        const colors = { GET: '#22c55e', POST: '#3b82f6', PUT: '#f59e0b', PATCH: '#8b5cf6', DELETE: '#ef4444' };
        select.style.color = colors[select.value] || '#e2e8f0';
    }

    function switchTab(btn, tabId) {
        document.querySelectorAll('.tab-pill').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        ['bodyTab', 'headersTab', 'paramsTab'].forEach(id => {
            document.getElementById(id).style.display = id === tabId ? 'block' : 'none';
        });
    }

    async function sendRequest() {
        const method = document.getElementById('reqMethod').value;
        let url = document.getElementById('reqUrl').value.trim();
        const bodyRaw = document.getElementById('reqBody').value.trim();
        const paramsRaw = document.getElementById('reqParams')?.value?.trim();
        const headersRaw = document.getElementById('reqHeaders')?.value?.trim();
        const token = getToken();
        const base = getBase();

        if (!url) return;

        // Build full URL
        let fullUrl = url.startsWith('http') ? url : base + url;

        // Add query params
        if (paramsRaw) {
            try {
                const params = JSON.parse(paramsRaw);
                const qs = new URLSearchParams(params).toString();
                fullUrl += (fullUrl.includes('?') ? '&' : '?') + qs;
            } catch (e) { /* ignore */ }
        }

        // Build headers
        const headers = { 'Accept': 'application/json' };
        if (token) headers['Authorization'] = 'Bearer ' + token;
        if (bodyRaw && method !== 'GET') headers['Content-Type'] = 'application/json';

        // Merge custom headers
        if (headersRaw) {
            try { Object.assign(headers, JSON.parse(headersRaw)); } catch (e) { /* ignore */ }
        }

        // Build options
        const opts = { method, headers };
        if (bodyRaw && method !== 'GET') {
            opts.body = bodyRaw;
        }

        // UI feedback
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        sendBtn.disabled = true;

        const statusPill = document.getElementById('statusPill');
        statusPill.style.display = 'inline-flex';
        statusPill.className = 'status-pill status-loading';
        statusPill.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Loading...';

        document.getElementById('responseEmpty').style.display = 'none';
        document.getElementById('responseOutput').style.display = 'block';
        document.getElementById('responseOutput').textContent = 'Sending request...';

        const start = performance.now();

        try {
            const response = await fetch(fullUrl, opts);
            const elapsed = Math.round(performance.now() - start);
            const status = response.status;

            let data;
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }

            // Status pill
            const statusClass = status < 300 ? 'status-2xx' : status < 500 ? 'status-4xx' : 'status-5xx';
            statusPill.className = 'status-pill ' + statusClass;
            statusPill.innerHTML = '<i class="fas fa-circle"></i> ' + status + ' ' + response.statusText;

            // Time
            document.getElementById('timeBadge').textContent = elapsed + 'ms';

            // Response body
            const output = typeof data === 'object' ? JSON.stringify(data, null, 2) : data;
            document.getElementById('responseOutput').textContent = output;
            syntaxHighlight();

        } catch (err) {
            const elapsed = Math.round(performance.now() - start);
            statusPill.className = 'status-pill status-5xx';
            statusPill.innerHTML = '<i class="fas fa-times-circle"></i> Error';
            document.getElementById('timeBadge').textContent = elapsed + 'ms';
            document.getElementById('responseOutput').textContent = 'Network Error: ' + err.message + '\n\nMake sure the server is running at: ' + base;
        } finally {
            sendBtn.innerHTML = '<i class="fas fa-bolt"></i> Send';
            sendBtn.disabled = false;
        }
    }

    function syntaxHighlight() {
        const el = document.getElementById('responseOutput');
        const text = el.textContent;
        try {
            const json = JSON.parse(text);
            const highlighted = JSON.stringify(json, null, 2)
                .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?)/g, match => {
                    if (/:$/.test(match)) return '<span style="color:#7dd3fc">' + match + '</span>';
                    return '<span style="color:#86efac">' + match + '</span>';
                })
                .replace(/\b(true|false)\b/g, '<span style="color:#fbbf24">$1</span>')
                .replace(/\bnull\b/g, '<span style="color:#f87171">null</span>')
                .replace(/\b(\d+)\b/g, '<span style="color:#c4b5fd">$1</span>');
            el.innerHTML = highlighted;
        } catch (e) { /* not json */ }
    }

    function quickLogin(role) {
        loginRole = role;
        
        let defaultEmail = '';
        if (role === 'ibu_hamil') defaultEmail = 'hamil@prenava.com';
        if (role === 'bidan') defaultEmail = 'bidan.rita@prenava.com';
        if (role === 'admin') defaultEmail = 'admin@prenava.com';
        
        document.getElementById('loginEmail').value = defaultEmail;
        document.getElementById('loginPassword').value = 'password123';
        document.getElementById('loginError').style.display = 'none';
        document.getElementById('loginEmail').placeholder = 'Enter ' + role + ' email';
        new bootstrap.Modal(document.getElementById('loginModal')).show();
    }

    async function doLogin() {
        const email = document.getElementById('loginEmail').value.trim();
        const password = document.getElementById('loginPassword').value.trim();
        const base = getBase();

        if (!email || !password) {
            document.getElementById('loginError').textContent = 'Email and password are required';
            document.getElementById('loginError').style.display = 'block';
            return;
        }

        try {
            const res = await fetch(base + '/api/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ email, password })
            });

            const data = await res.json();

            if (data.access_token || data.token) {
                const token = data.access_token || data.token;
                document.getElementById('authToken').value = token;
                bootstrap.Modal.getInstance(document.getElementById('loginModal')).hide();
            } else {
                document.getElementById('loginError').textContent = data.message || data.error || 'Login failed';
                document.getElementById('loginError').style.display = 'block';
            }
        } catch (err) {
            document.getElementById('loginError').textContent = 'Network error: ' + err.message;
            document.getElementById('loginError').style.display = 'block';
        }
    }

    async function testAuth() {
        const token = getToken();
        if (!token) {
            alert('Please enter a JWT token first, or use Quick Login');
            return;
        }
        loadEndpoint('GET', '/api/auth/me', null, 'Test Auth Token');
        setTimeout(sendRequest, 100);
    }

    // Keyboard shortcut: Ctrl+Enter to send
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            sendRequest();
        }
    });

    // Initialize method color
    updateMethodColor(document.getElementById('reqMethod'));
</script>

</body>
</html>
