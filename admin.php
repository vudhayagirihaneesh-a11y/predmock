<?php
session_start();

// Admin Password — reads from environment variable for production; dev fallback set at deploy-time only
// Default admin password for dev/testing
// (Override in production by setting ADMIN_PASSWORD env var)
$admin_password = getenv('ADMIN_PASSWORD');
if (empty($admin_password)) {
    // SECURITY WARNING: Hardcoded password is a major security risk.
    // This should be removed in production.
    $admin_password = 'admin_0123';
    error_log('ADMIN_PASSWORD environment variable is not set. Using dev default (admin_0123).');
}


if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header("Location: admin.php");
  exit;
}
if (isset($_POST['password'])) {
    if ($_POST['password'] === $admin_password) $_SESSION['admin_logged_in'] = true;
    else $error = "Invalid Password";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen p-4 md:p-8">

    <?php if (empty($_SESSION['admin_logged_in'])): ?>
    <div class="max-w-sm mx-auto mt-20 bg-white p-8 rounded-xl shadow-lg text-center">
        <h2 class="text-2xl font-bold mb-6">Admin Login</h2>
        <?php if (isset($error)) echo "<p class='text-rose-500 mb-4 font-bold'>$error</p>"; ?>
        <form action="admin.php" method="POST" class="space-y-4">
            <input type="password" name="password" placeholder="Enter Admin Password" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-slate-800 outline-none">
            <button type="submit" class="w-full bg-slate-800 text-white font-bold py-2 rounded hover:bg-slate-900">Access Dashboard</button>
        </form>
    </div>
    <?php else: ?>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h1 class="text-3xl font-extrabold text-slate-800">Admin Dashboard</h1>
            <a href="admin.php?logout=1" class="px-4 py-2 bg-rose-100 text-rose-700 font-bold rounded hover:bg-rose-200 transition">Logout</a>
        </div>
        
        <div class="flex gap-2 border-b border-slate-300 pb-2 overflow-x-auto">
            <button onclick="switchTab('overview')" id="btn-overview" class="px-5 py-2.5 font-bold rounded-t-lg bg-indigo-600 text-white transition whitespace-nowrap">Overview</button>
            <button onclick="switchTab('payments')" id="btn-payments" class="px-5 py-2.5 font-bold rounded-t-lg bg-slate-200 text-slate-600 hover:bg-slate-300 transition whitespace-nowrap">Payments</button>
            <button onclick="switchTab('agents')" id="btn-agents" class="px-5 py-2.5 font-bold rounded-t-lg bg-slate-200 text-slate-600 hover:bg-slate-300 transition whitespace-nowrap">Agents Workstation</button>
            <button onclick="switchTab('reports')" id="btn-reports" class="px-5 py-2.5 font-bold rounded-t-lg bg-slate-200 text-slate-600 hover:bg-slate-300 transition whitespace-nowrap">Agent Reports</button>
            <button onclick="switchTab('tickets')" id="btn-tickets" class="px-5 py-2.5 font-bold rounded-t-lg bg-slate-200 text-slate-600 hover:bg-slate-300 transition whitespace-nowrap">Ticket Search</button>
            <button onclick="switchTab('users')" id="btn-users" class="px-5 py-2.5 font-bold rounded-t-lg bg-slate-200 text-slate-600 hover:bg-slate-300 transition whitespace-nowrap">Users</button>
            <button onclick="switchTab('coupons')" id="btn-coupons" class="px-5 py-2.5 font-bold rounded-t-lg bg-slate-200 text-slate-600 hover:bg-slate-300 transition whitespace-nowrap">Coupons</button>
            <button onclick="switchTab('refunds')" id="btn-refunds" class="px-5 py-2.5 font-bold rounded-t-lg bg-slate-200 text-slate-600 hover:bg-slate-300 transition whitespace-nowrap">Refunds</button>
        </div>

        <!-- Overview Tab -->
        <div id="tab-overview" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-xl shadow border border-slate-200 flex items-center gap-5">
                <div class="bg-emerald-100 text-emerald-600 p-4 rounded-full"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
                <div><p class="text-sm text-slate-500 font-semibold">Total Revenue</p><p id="stat-revenue" class="text-3xl font-bold text-slate-800">₹0</p></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border border-slate-200 flex items-center gap-5">
                <div class="bg-amber-100 text-amber-600 p-4 rounded-full"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path><polyline points="12 6 12 12 16 14"></polyline></svg></div>
                <div><p class="text-sm text-slate-500 font-semibold">Pending Payments</p><p id="stat-pending-payments" class="text-3xl font-bold text-slate-800">0</p></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border border-slate-200 flex items-center gap-5">
                <div class="bg-indigo-100 text-indigo-600 p-4 rounded-full"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div>
                <div><p class="text-sm text-slate-500 font-semibold">Open Tickets</p><p id="stat-open-tickets" class="text-3xl font-bold text-slate-800">0</p></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border border-slate-200 flex items-center gap-5">
                <div class="bg-sky-100 text-sky-600 p-4 rounded-full"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
                <div><p class="text-sm text-slate-500 font-semibold">Online Agents</p><p id="stat-online-agents" class="text-3xl font-bold text-slate-800">0 / 0</p></div>
            </div>
        </div>

        <!-- Payments Tab -->
        <div id="tab-payments" class="bg-white rounded-xl shadow overflow-hidden border border-slate-200 hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="p-4">Order ID</th>
                            <th class="p-4">Plan</th>
                            <th class="p-4">Amount</th>
                            <th class="p-4">Date</th>
                            <th class="p-4">User Email</th>
                            <th class="p-4">Type</th>
                            <th class="p-4">Screenshot</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-payments" class="divide-y divide-slate-200">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Agents Tab -->
        <div id="tab-agents" class="bg-white rounded-xl shadow overflow-hidden border border-slate-200 hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
                <h2 class="text-lg font-bold text-slate-800">Agent Approvals & Management</h2>
                <button onclick="loadAgents()" class="px-3 py-1.5 bg-indigo-100 text-indigo-700 font-bold rounded hover:bg-indigo-200 text-sm w-full md:w-auto">Refresh List</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="p-4">ID</th>
                            <th class="p-4">Name / Age</th>
                            <th class="p-4">Email</th>
                            <th class="p-4">Proof</th>
                            <th class="p-4">Joined</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-agents" class="divide-y divide-slate-200">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Agent Reports Tab -->
        <div id="tab-reports" class="bg-white rounded-xl shadow overflow-hidden border border-slate-200 hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
                <h2 class="text-lg font-bold text-slate-800">Agent Reports</h2>
                <button onclick="loadAgentReports()" class="px-3 py-1.5 bg-indigo-100 text-indigo-700 font-bold rounded hover:bg-indigo-200 text-sm w-full md:w-auto">Refresh List</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="p-4">ID</th>
                            <th class="p-4">Ticket ID</th>
                            <th class="p-4">Student Email</th>
                            <th class="p-4">Message</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Date</th>
                            <th class="p-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-reports" class="divide-y divide-slate-200"></tbody>
                </table>
            </div>
        </div>

        <!-- Tickets Tab -->
        <div id="tab-tickets" class="bg-white rounded-xl shadow border border-slate-200 hidden">
            <div class="p-6 border-b border-slate-200 bg-slate-50">
                <h2 class="text-lg font-bold text-slate-800 mb-4">Search Support Ticket</h2>
                <div class="flex flex-col sm:flex-row gap-4">
                    <input type="number" id="search-ticket-id" placeholder="Enter Ticket ID (e.g. 5)" class="flex-1 max-w-md px-4 py-2.5 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                    <button onclick="searchTicket()" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition">Search</button>
                </div>
            </div>
            <div id="ticket-result" class="p-6">
                <div class="text-center text-slate-500 py-8">Enter a Ticket ID to view details and agent activity.</div>
            </div>
        </div>

        <!-- Users Tab -->
        <div id="tab-users" class="bg-white rounded-xl shadow border border-slate-200 hidden">
            <div class="p-6 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 class="text-lg font-bold text-slate-800">Manage User Access</h2>
                <div class="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                    <input type="number" id="search-user-input" placeholder="Search by User ID..." class="flex-1 min-w-[250px] px-4 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 text-sm font-mono">
                    <button onclick="loadUsers()" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition text-sm">Search</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="p-4">User ID</th>
                            <th class="p-4">Email (Masked)</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Joined Date</th>
                            <th class="p-4">Action</th>
                        </tr>
                    </thead>
                    <tbody id="table-users" class="divide-y divide-slate-200">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Coupons Tab -->
        <div id="tab-coupons" class="bg-white rounded-xl shadow border border-slate-200 hidden">
            <div class="p-6 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-slate-800">Coupon Management</h2>
            </div>
            <div class="p-6 border-b border-slate-200 flex flex-wrap gap-4 items-end bg-slate-50/50">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-xs font-bold text-slate-500 uppercase">Coupon Code</label>
                    <input type="text" id="coupon-code" placeholder="e.g. SAVE50" class="w-full mt-1 px-4 py-2.5 border border-slate-300 rounded-lg uppercase outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="w-32">
                    <label class="text-xs font-bold text-slate-500 uppercase">Discount (₹)</label>
                    <input type="number" id="coupon-discount" placeholder="e.g. 100" class="w-full mt-1 px-4 py-2.5 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="text-xs font-bold text-slate-500 uppercase">Expiry (Optional)</label>
                    <input type="datetime-local" id="coupon-expiry" class="w-full mt-1 px-4 py-2.5 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <button onclick="createCoupon()" class="px-6 py-2.5 bg-emerald-600 text-white font-bold rounded-lg hover:bg-emerald-700 transition">Create Coupon</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-800 text-white"><tr><th class="p-4">ID</th><th class="p-4">Code</th><th class="p-4">Discount</th><th class="p-4">Expiry Date</th><th class="p-4">Uses</th><th class="p-4">Status</th><th class="p-4">Action</th></tr></thead>
                    <tbody id="table-coupons" class="divide-y divide-slate-200"></tbody>
                </table>
            </div>
        </div>

        <!-- Refunds Tab -->
        <div id="tab-refunds" class="bg-white rounded-xl shadow border border-slate-200 hidden">
            <div class="p-6 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-slate-800">Refund Requests</h2>
                <button onclick="loadRefunds()" class="px-3 py-1.5 bg-indigo-100 text-indigo-700 font-bold rounded hover:bg-indigo-200 text-sm">Refresh</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="p-4">ID</th>
                            <th class="p-4">Refund Token</th>
                            <th class="p-4">Order ID</th>
                            <th class="p-4">Username + User ID</th>
                            <th class="p-4">Plan</th>

                            <th class="p-4">QR Code</th>

                            <th class="p-4">Date</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-refunds" class="divide-y divide-slate-200"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- User Detail Modal -->
    <div id="user-modal" class="hidden fixed inset-0 bg-slate-900/90 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col">
            <div class="p-4 bg-slate-800 text-white flex justify-between items-center">
                <h3 class="font-bold text-lg">User Details</h3>
                <button onclick="document.getElementById('user-modal').classList.add('hidden')" class="text-white hover:text-rose-400 font-bold text-xl leading-none">&times;</button>
            </div>
            <div id="user-modal-content" class="p-6 overflow-y-auto max-h-[80vh]"></div>
        </div>
    </div>

    <!-- Image / Attachment Modal -->
    <div id="image-modal" class="hidden fixed inset-0 bg-slate-900/90 z-50 flex items-center justify-center p-4 backdrop-blur-sm" onclick="this.classList.add('hidden')">
        <div id="modal-content" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl border-4 border-white/10 overflow-hidden bg-slate-950" onclick="event.stopPropagation()"></div>
    </div>

    <script>
        function switchTab(tab) { // Removed coupon-usage
            ['overview', 'payments', 'agents', 'reports', 'tickets', 'users', 'coupons', 'refunds'].forEach(t => {
                const elTab = document.getElementById(`tab-${t}`);
                if (elTab) elTab.classList.add('hidden');
                const btn = document.getElementById(`btn-${t}`);
                if (btn) {
                    btn.classList.remove('bg-indigo-600', 'text-white');
                    btn.classList.add('bg-slate-200', 'text-slate-600');
                }
            });
            const activeTab = document.getElementById(`tab-${tab}`);
            if (activeTab) activeTab.classList.remove('hidden');
            const activeBtn = document.getElementById(`btn-${tab}`);
            if (activeBtn) {
                activeBtn.classList.remove('bg-slate-200', 'text-slate-600');
                activeBtn.classList.add('bg-indigo-600', 'text-white');
            }

            if(tab === 'overview') loadAnalytics();
            if(tab === 'payments') loadPayments();
            if(tab === 'agents') loadAgents();
            if(tab === 'reports') loadAgentReports();
            if(tab === 'users') loadUsers();
            if(tab === 'coupons') loadCoupons();
            if(tab === 'refunds') loadRefunds();
        }

        function loadAnalytics() {
            fetch('api.php?action=admin_get_analytics').then(r => r.json()).then(data => {
                if(data.success && data.analytics) {
                    const analytics = data.analytics;
                    document.getElementById('stat-revenue').textContent = `₹${analytics.total_revenue.toLocaleString('en-IN')}`;
                    document.getElementById('stat-pending-payments').textContent = analytics.pending_payments;
                    document.getElementById('stat-open-tickets').textContent = analytics.open_tickets;
                    document.getElementById('stat-online-agents').textContent = `${analytics.online_agents} / ${analytics.total_agents}`;
                }
            }).catch(err => {
                console.error("Failed to load analytics:", err);
                document.getElementById('stat-revenue').textContent = 'Error';
            });
        }

        function loadPayments() {
            fetch('api.php?action=admin_get_payments').then(r => r.json()).then(data => {
                const tbody = document.getElementById('table-payments');
                tbody.innerHTML = '';
                if(data.success && data.payments) {
                    data.payments.forEach(p => {
                        // Determine payment type label
                        let paymentTypeHtml = '<span class="text-xs font-bold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-full border border-slate-200">Normal</span>';
                        if ((p.status || '') === 'refunded') {
                            paymentTypeHtml = '<span class="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-200">Refunded</span>';
                        } else if (p.screenshot_path === 'free_access') {
                            paymentTypeHtml = '<span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full border border-indigo-200">Free Claim</span>';
                        } else if (p.coupon_code && p.coupon_code !== '') {
                            paymentTypeHtml = '<span class="text-xs font-bold text-purple-600 bg-purple-50 px-2.5 py-1 rounded-full border border-purple-200">Coupon Applied</span>';
                        }
                        tbody.innerHTML += `
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 font-mono text-sm font-bold text-slate-700">${p.order_id}</td>
                                <td class="p-4 font-bold uppercase text-indigo-700">${p.plan}</td>
                                <td class="p-4 font-semibold text-slate-800">₹${p.amount}</td>
                                <td class="p-4 text-xs text-slate-500">${p.created_at}</td>
                                <td class="p-4 text-sm text-slate-600">${p.email}</td>
                                <td class="p-4">${paymentTypeHtml}</td>
                                <td class="p-4">${p.screenshot_path === 'free_access' || p.screenshot_path === 'coupon_free_access' ? '<span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">Free Claim</span>' : (p.screenshot_path === 'manual_admin_grant' ? '<span class="text-xs font-bold text-sky-600 bg-sky-50 px-2.5 py-1 rounded-full border border-sky-200">Manual Grant</span>' : `<button onclick="viewImage('${p.screenshot_path}')" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded hover:bg-slate-200 font-medium text-xs">View Proof</button>`)}</td>
                                <td class="p-4"><span class="px-2.5 py-1 text-xs font-bold rounded-full ${p.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : (p.status === 'rejected' ? 'bg-rose-100 text-rose-800' : p.status === 'refunded' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')} border ${p.status === 'approved' ? 'border-emerald-200' : (p.status === 'rejected' || p.status === 'refunded' ? 'border-rose-200' : 'border-amber-200')}">${p.status.toUpperCase()}</span></td>
                                <td class="p-4 flex gap-2">
                                    ${p.status === 'pending' ? `<button onclick="updateStatus('${p.order_id}', 'approved')" class="px-3 py-1.5 bg-emerald-600 text-white rounded text-xs font-bold hover:bg-emerald-700 transition">Approve</button>
                                    <button onclick="updateStatus('${p.order_id}', 'rejected')" class="px-3 py-1.5 bg-rose-600 text-white rounded text-xs font-bold hover:bg-rose-700 transition">Reject</button>` : '-'}
                                </td>
                            </tr>`;
                    });
                }
            });
        }

        function loadAgents() {
            fetch('api.php?action=admin_agent_list').then(r => r.json()).then(data => {
                const tbody = document.getElementById('table-agents');
                tbody.innerHTML = '';
                if(data.success && data.agents) {
                    data.agents.forEach(a => {
                        const isOnline = a.seconds_since_last_seen !== null && parseInt(a.seconds_since_last_seen) < 300;

                        let statusHtml = '';
                        if (a.is_removed == 1) statusHtml = '<span class="px-2 py-1 bg-rose-100 text-rose-800 text-xs font-bold rounded-full border border-rose-200">Removed</span>';
                        else if (a.is_resigned == 1) statusHtml = '<span class="px-2 py-1 bg-slate-200 text-slate-800 text-xs font-bold rounded-full border border-slate-300">Resigned</span>';
                        else if (a.is_approved == 1) {
                            statusHtml = `<span class="px-2 py-1 ${isOnline ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'} text-xs font-bold rounded-full border">${isOnline ? 'Online' : 'Offline'}</span>`;
                        }
                        else statusHtml = '<span class="px-2 py-1 bg-amber-100 text-amber-800 text-xs font-bold rounded-full border border-amber-200">Pending</span>';

                        const proofBtn = a.proof_path ? `<button onclick="viewImage('${a.proof_path}')" class="text-indigo-600 underline text-xs font-bold">View ID</button>` : '<span class="text-slate-400 text-xs">No Proof</span>';

                        let actions = '';
                        if (a.is_approved == 0 && a.proof_path) {
                            actions += `<button onclick="updateAgentStatus(${a.id}, 'approve')" class="px-3 py-1 bg-emerald-600 text-white rounded text-xs font-bold hover:bg-emerald-700 transition">Approve</button>`;
                        } 
                        // Per request, remove suspend button.
                        // else if (a.is_approved == 1) {
                        //     actions += `<button onclick="updateAgentStatus(${a.id}, 'disapprove')" class="px-3 py-1 bg-amber-500 text-white rounded text-xs font-bold hover:bg-amber-600 transition">Suspend</button>`;
                        // }
                        if (a.is_removed == 0) {
                            actions += `<button onclick="updateAgentStatus(${a.id}, 'remove')" class="px-3 py-1 bg-rose-600 text-white rounded text-xs font-bold hover:bg-rose-700 transition ml-2">Remove</button>`;
                        }

                        tbody.innerHTML += `
                            <tr class="hover:bg-slate-50 transition border-b border-slate-100">
                                <td class="p-4 font-mono font-bold text-slate-500">${a.id}</td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-800">${escapeHtml(a.first_name || '')} ${escapeHtml(a.last_name || '')}</div>
                                    <div class="text-xs text-slate-500">Age: ${a.age || 'N/A'}</div>
                                </td>
                                <td class="p-4 text-slate-700 font-medium">${escapeHtml(a.email)}</td>
                                <td class="p-4">${proofBtn}</td>
                                <td class="p-4 text-xs text-slate-500">${a.created_at}</td>
                                <td class="p-4">${statusHtml}</td>
                                <td class="p-4 flex flex-wrap gap-2">${actions || '-'}</td>
                            </tr>
                        `;
                    });
                }
            });
        }

        function loadAgentReports() {
            fetch('api.php?action=admin_get_agent_reports').then(r => r.json()).then(data => {
                const tbody = document.getElementById('table-reports'); tbody.innerHTML = '';
                if(data.success && data.reports) {
                    if (data.reports.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-500">No agent reports found.</td></tr>';
                    } else {
                        data.reports.forEach(r => {
                            let statusBadge = r.status === 'replied' ? '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">REPLIED</span>' : '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200">OPEN</span>';
                            tbody.innerHTML += `
                                <tr class="hover:bg-slate-50 transition border-b border-slate-100">
                                    <td class="p-4 font-mono text-sm font-bold text-slate-700">${r.id}</td>
                                    <td class="p-4 font-bold text-indigo-600">#${r.ticket_id}</td>
                                    <td class="p-4 text-sm text-slate-600">${escapeHtml(r.student_email)}</td>
                                    <td class="p-4 text-sm text-slate-800 max-w-xs truncate" title="${escapeHtml(r.message)}">${escapeHtml(r.message)}</td>
                                    <td class="p-4">${statusBadge}</td>
                                    <td class="p-4 text-xs text-slate-500">${r.created_at}</td>
                                    <td class="p-4">
                                        <button onclick="replyAgentReport(${r.id})" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 font-bold border border-indigo-200 rounded hover:bg-indigo-100 transition text-xs">View/Reply</button>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-rose-500">Error loading reports.</td></tr>';
                }
            });
        }

        function replyAgentReport(reportId) {
            const reply = prompt("Enter your reply to the student:");
            if (!reply) return;
            const fd = new FormData(); fd.append('action', 'admin_reply_agent_report'); fd.append('report_id', reportId); fd.append('reply', reply);
            fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if(d.success) { alert("Reply sent successfully!"); loadAgentReports(); } else { alert("Error: " + d.message); } });
        }

        function searchTicket() {
            const id = document.getElementById('search-ticket-id').value.trim();
            const resEl = document.getElementById('ticket-result');
            if(!id) {
                resEl.innerHTML = '<div class="text-rose-500 font-bold">Please enter a valid Ticket ID.</div>';
                return;
            }
            resEl.innerHTML = '<div class="text-slate-500">Searching...</div>';

            fetch(`api.php?action=admin_get_ticket&ticket_id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if(!data.success) {
                        resEl.innerHTML = `<div class="p-4 bg-rose-50 text-rose-700 font-bold rounded-lg border border-rose-200">${escapeHtml(data.message)}</div>`;
                        return;
                    }
                    const t = data.ticket;
                    let html = `
                        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                            <div class="bg-slate-800 text-white p-4 flex justify-between items-center">
                                <h3 class="text-lg font-bold">Ticket #${t.id}</h3>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-full ${t.status === 'resolved' ? 'bg-emerald-500' : 'bg-amber-500'}">${t.status.toUpperCase()}</span>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Student Email</p>
                                    <p class="font-semibold text-slate-800">${escapeHtml(t.student_email)}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Created At</p>
                                    <p class="font-semibold text-slate-800">${t.created_at}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Subject</p>
                                    <p class="font-bold text-lg text-indigo-700">${escapeHtml(t.subject)}</p>
                                </div>
                                <div class="md:col-span-2 bg-slate-50 p-4 rounded-lg border border-slate-200">
                                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Original Message / Chat</p>
                                    <p id="ticket-original-msg" class="text-slate-700 whitespace-pre-wrap"></p>
                                </div>
                            </div>
                    `;

                    if (t.resolutions && t.resolutions.length > 0) {
                        html += `<div class="bg-slate-100 p-6 border-t border-slate-200">
                            <h4 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-indigo-600"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                Agent Resolutions
                            </h4>
                            <div id="resolutions-container" class="space-y-4"></div>
                        `;
                        html += `</div></div>`;
                    } else {
                        html += `<div class="p-6 border-t border-slate-200 text-center text-slate-500 font-medium">No resolutions recorded yet.</div>`;
                    }
                    html += `</div>`;
                    resEl.innerHTML = html;

                    // Safely set text content for user input to prevent XSS
                    document.getElementById('ticket-original-msg').textContent = t.message;
                    
                    if (t.resolutions && t.resolutions.length > 0) {
                        const resContainer = document.getElementById('resolutions-container');
                        t.resolutions.forEach(r => {
                            const isAgent = r.sender_type === 'agent';
                            const senderName = isAgent ? (r.agent_name || 'Agent') : 'Student';
                            const senderEmail = isAgent ? (r.agent_email || '') : t.student_email;
                            const bubbleClass = isAgent ? 'bg-indigo-50 border-indigo-200' : 'bg-slate-50 border-slate-200';
                            const nameClass = isAgent ? 'text-indigo-700' : 'text-slate-700';
                            
                            const div = document.createElement('div');
                            div.className = `bg-white p-4 rounded-lg border shadow-sm ${bubbleClass}`;
                            
                            const headerDiv = document.createElement('div');
                            headerDiv.className = 'flex justify-between items-start mb-2';
                            headerDiv.innerHTML = `<div><span class="font-bold ${nameClass}">${escapeHtml(senderName)}</span><span class="text-xs text-slate-500 ml-2">&lt;${escapeHtml(senderEmail)}&gt;</span></div><span class="text-xs text-slate-400">${escapeHtml(r.created_at)}</span>`;
                            
                            const msgP = document.createElement('p');
                            msgP.className = 'text-slate-700 whitespace-pre-wrap';
                            msgP.textContent = r.message;
                            
                            div.appendChild(headerDiv);
                            div.appendChild(msgP);
                            resContainer.appendChild(div);
                        });
                    }
                })
                .catch(err => {
                    resEl.innerHTML = `<div class="text-rose-500 font-bold">Error: ${err.message}</div>`;
                });
        }

        function updateAgentStatus(agentId, mode) {
            if(!confirm(`Are you sure you want to ${mode} this agent?`)) return;
            const fd = new FormData(); 
            fd.append('action', 'admin_agent_update_status'); 
            fd.append('agent_id', agentId); 
            fd.append('mode', mode);
            fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { 
                if(d.success) loadAgents(); 
                else alert('Error updating status: ' + (d.message || 'Unknown')); 
            });
        }

        function viewImage(path) {
            const content = document.getElementById('modal-content');
            const ext = (path.split('.').pop() || '').toLowerCase();
            let html = '';
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                html = `<img src="${path}" class="w-full h-auto max-h-[90vh] object-contain bg-slate-900" alt="Attachment">`;
            } else if (ext === 'pdf') {
                html = `<object data="${path}" type="application/pdf" class="w-full h-[90vh]">` +
                       `<p class="p-6 text-white">PDF preview unavailable. <a href="${path}" target="_blank" class="underline text-indigo-300">Open in new tab</a></p></object>`;
            } else {
                html = `<div class="p-6 text-white"><p class="mb-4">Unable to preview this file type.</p><a href="${path}" target="_blank" class="underline text-indigo-300">Open attachment in a new tab</a></div>`;
            }
            content.innerHTML = html;
            document.getElementById('image-modal').classList.remove('hidden');
        }

        function loadRefunds() {
            fetch('api.php?action=admin_get_refund_requests').then(r => r.json()).then(data => {
                const tbody = document.getElementById('table-refunds'); tbody.innerHTML = '';
                if(data.success && data.requests) {
                    if (data.requests.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-slate-500">No refund requests found.</td></tr>';
                    } else {
                        data.requests.forEach(req => {
                            let statusBadge = '';
                            if(req.status === 'pending') statusBadge = '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200">PENDING</span>';
                            else if(req.status === 'refunded') statusBadge = '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">REFUNDED</span>';
                            else statusBadge = '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-rose-100 text-rose-800 border border-rose-200">REJECTED</span>';
                            
                            let actions = '-';
                            if (req.status === 'pending') {
                                actions = `
                                    <button onclick="processRefundRequest(${req.id}, 'approve')" class="px-3 py-1.5 bg-emerald-600 text-white rounded text-xs font-bold hover:bg-emerald-700 transition">Approve</button>
                                    <button onclick="processRefundRequest(${req.id}, 'reject')" class="px-3 py-1.5 bg-rose-600 text-white rounded text-xs font-bold hover:bg-rose-700 transition">Reject</button>
                                `;
                            }

                            tbody.innerHTML += `
                                <tr class="hover:bg-slate-50 transition border-b border-slate-100">
                                    <td class="p-4 font-mono text-sm font-bold text-slate-700">${req.id}</td>
                                    <td class="p-4 font-mono text-xs font-bold text-indigo-600">${req.refund_token || 'N/A'}</td>
                                    <td class="p-4 font-mono text-xs font-bold text-slate-600">${req.order_id || 'N/A'}</td>
                                    <td class="p-4 text-sm text-slate-600">${escapeHtml(req.username || 'N/A')} (ID: ${escapeHtml(req.user_id)})</td>
                                    <td class="p-4 font-bold uppercase text-indigo-700">${escapeHtml(req.plan)}</td>

                                    <td class="p-4"><button onclick="viewImage('${req.qr_code_path}')" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded hover:bg-slate-200 font-medium text-xs">View QR</button></td>
                                    <td class="p-4 text-xs text-slate-500">${req.created_at}</td>
                                    <td class="p-4">${statusBadge}</td>
                                    <td class="p-4 flex gap-2">${actions}</td>
                                </tr>
                            `;
                        });
                    }
                }
            });
        }

        function processRefundRequest(requestId, actionType) {
            const confirmMsg = actionType === 'approve' 
                ? 'Are you sure you want to APPROVE this refund? This will permanently revoke the user\'s access to the package and notify them.' 
                : 'Are you sure you want to REJECT this refund?';
            if(!confirm(confirmMsg)) return;

            const fd = new FormData();
            fd.append('action', 'admin_process_refund');
            fd.append('request_id', requestId);
            fd.append('action_type', actionType);
            fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                if(d.success) {
                    alert('Refund request processed successfully.');
                    loadRefunds();
                    loadAnalytics();
                } else {
                    alert('Error: ' + (d.message || 'Unknown error'));
                }
            });
        }

        function updateStatus(orderId, status) {
            if(!confirm(`Are you sure you want to ${status} this payment?`)) return;
            const fd = new FormData(); 
            fd.append('action', 'admin_update_status'); 
            fd.append('order_id', orderId); 
            fd.append('status', status);
            fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { 
                if(d.success) loadPayments(); else alert('Error updating status'); 
            });
        }

        function escapeHtml(s) {
            if (!s) return '';
            return String(s)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        document.getElementById('search-ticket-id').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') searchTicket();
        });

    function loadUsers() {
        const search = document.getElementById('search-user-input') ? document.getElementById('search-user-input').value : '';
        fetch(`api.php?action=admin_list_users&search=${encodeURIComponent(search)}`).then(r => r.json()).then(d => {
            const tbody = document.getElementById('table-users');
            tbody.innerHTML = '';
            if (d.success && d.users) {
                d.users.forEach(u => {
                    const statusBadge = u.is_verified == 1 ? '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">Verified</span>' : '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-slate-100 text-slate-600 border border-slate-200">Unverified</span>';
                    const namePart = u.masked_name ? `<div class="text-xs text-slate-500 mt-0.5">${escapeHtml(u.masked_name)}</div>` : '';
                    tbody.innerHTML += `<tr class="hover:bg-slate-50 transition border-b border-slate-100"><td class="p-4 font-mono font-bold text-slate-500">${u.id}</td><td class="p-4 font-medium text-slate-800">${escapeHtml(u.masked_email)}${namePart}</td><td class="p-4">${statusBadge}</td><td class="p-4 text-xs text-slate-500">${u.created_at}</td><td class="p-4"><button onclick="openUserModal(${u.id})" class="px-4 py-2 bg-indigo-50 text-indigo-700 font-bold rounded border border-indigo-200 hover:bg-indigo-100 transition text-xs">Manage</button></td></tr>`;
                });
            }
        });
    }

        function openUserModal(userId) {
            fetch(`api.php?action=admin_get_user&user_id=${userId}`).then(r => r.json()).then(d => {
                const res = document.getElementById('user-modal-content');
                if(!d.success) { res.innerHTML = `<div class="text-rose-500 font-bold">${escapeHtml(d.message)}</div>`; return; }
                let html = `<div><h3 class="font-bold text-lg mb-2 text-slate-800">User: ${escapeHtml(d.user.masked_email)}</h3>`;
                html += `<h4 class="font-bold text-sm text-slate-500 mb-2 mt-4 uppercase tracking-wide">Unlocked Packages:</h4><ul class="list-disc pl-5 mb-6 text-sm text-slate-700">`;
                if(d.purchases && d.purchases.length > 0) {
                    d.purchases.forEach(p => html += `<li class="mb-1"><span class="font-bold text-indigo-600 uppercase">${escapeHtml(p.plan)}</span> (Since: ${p.created_at})</li>`);
                } else { html += `<li class="text-slate-500">No active packages</li>`; }
                html += `</ul><div class="flex gap-3 items-center mt-4 pt-4 border-t border-slate-200">
                    <select id="manual-plan-${d.user.id}" class="flex-1 border border-slate-300 bg-slate-50 rounded-lg px-4 py-2 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="met">MET Package</option><option value="vit">VITEEE Package</option>
                        <option value="srm">SRMJEEE Package</option><option value="bitsat">BITSAT Package</option>
                        <option value="jee">JEE Mains Package</option>
                        <option value="amrita">Amrita (AEEE) Package</option>
                        <option value="gitam">GITAM (GAT) Package</option>
                        <option value="combo">Combo (Mega Bundle)</option>
                    </select>
                    <button onclick="grantAccess(${d.user.id})" class="px-5 py-2 bg-emerald-600 text-white font-bold rounded-lg text-sm hover:bg-emerald-700 transition">Grant Access</button>
                </div></div>`;
                res.innerHTML = html;
                document.getElementById('user-modal').classList.remove('hidden');
            });
        }

        function grantAccess(userId) {
            const plan = document.getElementById(`manual-plan-${userId}`).value;
            if(!confirm(`Grant manual access for ${plan.toUpperCase()} to user ID ${userId}?`)) return;
            const fd = new FormData(); fd.append('action', 'admin_manual_grant'); fd.append('user_id', userId); fd.append('plan', plan);
            fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                if(d.success) { alert('Access granted successfully!'); openUserModal(userId); } else alert('Error: ' + d.message);
            });
        }

        function loadCoupons() {
            fetch('api.php?action=admin_list_coupons').then(r => r.json()).then(data => {
                const tbody = document.getElementById('table-coupons'); tbody.innerHTML = '';
                if(data.success && data.coupons) {
                    data.coupons.forEach(c => {
                        let isExpired = c.expires_at && new Date(c.expires_at) < new Date();
                        let isActive = (c.is_active === undefined || c.is_active == 1);
                        let statusBadge = (!isActive || isExpired) ? '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-rose-100 text-rose-800 border border-rose-200">EXPIRED</span>' : '<span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">ACTIVE</span>';
                        
                        let isTimed = c.expires_at && c.expires_at.trim() !== '';
                        let toggleBtn = '';
                        if (!isTimed) {
                            toggleBtn = isActive ? `<button onclick="toggleCoupon(${c.id}, 0)" class="px-3 py-1.5 bg-amber-500 text-white rounded text-xs font-bold hover:bg-amber-600 transition">Deactivate</button>` : `<button onclick="toggleCoupon(${c.id}, 1)" class="px-3 py-1.5 bg-emerald-600 text-white rounded text-xs font-bold hover:bg-emerald-700 transition">Activate</button>`;
                        }
                        let deleteBtn = `<button onclick="deleteCoupon(${c.id})" class="px-3 py-1.5 bg-rose-600 text-white rounded text-xs font-bold hover:bg-rose-700 transition ml-2">Delete</button>`;
                        
                        tbody.innerHTML += `<tr class="hover:bg-slate-50 transition border-b border-slate-100"><td class="p-4 font-mono text-sm font-bold text-slate-700">${c.id}</td><td class="p-4 font-bold uppercase text-indigo-700">${escapeHtml(c.code)}</td><td class="p-4 font-semibold text-slate-800">₹${c.discount_amount}</td><td class="p-4 text-sm text-slate-500">${c.expires_at || 'Never'}</td><td class="p-4 text-slate-500 font-mono font-bold">${c.usage_count}</td><td class="p-4">${statusBadge}</td><td class="p-4 flex gap-2 items-center">${toggleBtn}${deleteBtn}</td></tr>`;
                    });
                }
            });
        }

        function createCoupon() {
            const code = document.getElementById('coupon-code').value; const disc = document.getElementById('coupon-discount').value; const exp = document.getElementById('coupon-expiry').value;
            if(!code || !disc) return alert('Code and discount are required.');
            const fd = new FormData(); fd.append('action', 'admin_create_coupon'); fd.append('code', code); fd.append('discount', disc); if(exp) fd.append('expiry', exp);
            fetch('api.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                if(d.success) { document.getElementById('coupon-code').value=''; document.getElementById('coupon-discount').value=''; document.getElementById('coupon-expiry').value=''; loadCoupons(); } else alert('Error: ' + d.message);
            });
        }

        function toggleCoupon(id, newState) {
            if(!confirm(`Are you sure you want to ${newState ? 'activate' : 'deactivate'} this coupon?`)) return;
            const fd = new FormData(); fd.append('action', 'admin_toggle_coupon'); fd.append('id', id); fd.append('is_active', newState);
            fetch('api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{ if(d.success) loadCoupons(); else alert(d.message || 'Error toggling coupon.'); });
        }
        
        function deleteCoupon(id) {
            if(!confirm('Are you sure you want to delete this coupon?')) return;
            const fd = new FormData(); fd.append('action', 'admin_delete_coupon'); fd.append('id', id);
            fetch('api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{ if(d.success) loadCoupons(); else alert(d.message || 'Error deleting coupon.'); });
        }

        loadAnalytics(); // Load overview by default
    </script>
    <?php endif; ?>
</body>
</html>