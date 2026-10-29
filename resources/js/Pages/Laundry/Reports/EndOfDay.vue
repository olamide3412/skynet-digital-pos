<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import PosLayout from '@/Layouts/PosLayout.vue'
import PaginationLinks from '@/Components/PaginationLinks.vue'
import TillSlipModal from '@/Components/POS/TillSlipModal.vue'
import { useCurrency } from '@/Composables/useCurrency'
import dayjs from 'dayjs'

defineOptions({ layout: PosLayout })

const props = defineProps({
    date:             String,
    summary:          Object,
    serviceBreakdown: Array,
    tillSessions:     { type: Array, default: () => [] },
    orders:           Object,  // paginated
    payments:         Object,  // paginated
    debtPayments:     Object,  // paginated
    users:            Array,
    canSeeAll:        Boolean,
    filters:          Object,
})

const page = usePage()
const { format } = useCurrency()

const branchSlug = computed(() => page.props.current_branch?.slug || page.props.branch?.slug || '')
const safeRoute = (name, params = {}) => route(name, { branch: branchSlug.value, ...params })

const selectedUser = ref(props.filters.user_id || '')
const selectedDate = ref(props.filters.date || props.date || dayjs().format('YYYY-MM-DD'))
const activeSlipSession = ref(null)
const activeTab = ref('orders') // 'orders' | 'payments' | 'debt'

function doFilter() {
    router.get(safeRoute('laundry.reports.end-of-day'), {
        date:    selectedDate.value,
        user_id: selectedUser.value || undefined,
    }, { preserveState: true, replace: true })
}

function changeDate(daysOffset) {
    selectedDate.value = dayjs(selectedDate.value).add(daysOffset, 'day').format('YYYY-MM-DD')
    doFilter()
}

function setDateToday() {
    selectedDate.value = dayjs().format('YYYY-MM-DD')
    doFilter()
}

function fmtTime(val) {
    if (!val) return '—'
    return dayjs(val).format('HH:mm')
}

function printSummary() {
    const branchName = page.props.current_branch?.name || page.props.settings?.business_name || 'Laundry Operations'
    const companyName = page.props.system_config?.company_name || page.props.settings?.business_name || branchName
    const address = page.props.current_branch?.address || page.props.settings?.address || 'Laundry & Dry Cleaning Operations'
    const phone = page.props.current_branch?.phone || page.props.settings?.phone || ''
    const cashierScope = selectedUser.value ? (props.users?.find(u => u.id == selectedUser.value)?.name || 'Selected Staff') : 'All Cashiers & Staff'
    const reportDate = formattedDisplayDate.value
    const generatedAt = dayjs().format('DD/MM/YYYY HH:mm:ss')

    // 1. KPI Cards HTML
    const kpiCardsHtml = `
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Intake Orders</div>
                <div class="kpi-val">${props.summary.total_orders}</div>
                <div class="kpi-sub">Worth ${format(props.summary.total_revenue)}</div>
            </div>
            <div class="kpi-card highlight-indigo">
                <div class="kpi-label">Net Collections</div>
                <div class="kpi-val">${format(props.summary.net_collections)}</div>
                <div class="kpi-sub">Gross: ${format(props.summary.gross_collections)}</div>
            </div>
            <div class="kpi-card highlight-emerald">
                <div class="kpi-label">Cash in Drawer</div>
                <div class="kpi-val">${format(props.summary.cash_collected)}</div>
                <div class="kpi-sub">Physical cash collected</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Bank Transfers</div>
                <div class="kpi-val">${format(props.summary.bank_collected)}</div>
                <div class="kpi-sub">Electronic & POS</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Discounts Given</div>
                <div class="kpi-val">${format(props.summary.total_discount)}</div>
                <div class="kpi-sub">Order & Service Promos</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Unpaid Receivables</div>
                <div class="kpi-val text-amber">${format(props.summary.debt_recorded)}</div>
                <div class="kpi-sub">Balance on today's intake</div>
            </div>
            <div class="kpi-card highlight-emerald">
                <div class="kpi-label">Debt Recovered</div>
                <div class="kpi-val">${format(props.summary.laundry_debt_recovered)}</div>
                <div class="kpi-sub">${props.debtPayments?.total ?? 0} customer settlements</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Orders Picked Up</div>
                <div class="kpi-val">${props.summary.pickups_completed}</div>
                <div class="kpi-sub">Handed to clients</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Daily Expenses</div>
                <div class="kpi-val text-rose">${format(props.summary.total_expenses)}</div>
                <div class="kpi-sub">Logged store costs</div>
            </div>
            <div class="kpi-card ${props.summary.net_cash_flow >= 0 ? 'highlight-teal' : 'highlight-rose'}">
                <div class="kpi-label">Net Cash Flow</div>
                <div class="kpi-val">${format(props.summary.net_cash_flow)}</div>
                <div class="kpi-sub">(Cash + Recov) - Exp</div>
            </div>
        </div>
    `

    // 2. Till Shifts HTML (if any)
    let tillHtml = ''
    if (props.tillSessions && props.tillSessions.length > 0) {
        const rows = props.tillSessions.map(s => {
            const varianceVal = s.status === 'closed' ? ((s.variance > 0 ? '+' : '') + format(s.variance)) : 'Open Shift'
            return `
                <tr>
                    <td style="font-family: monospace; font-weight: bold;">#${s.id}</td>
                    <td>${s.cashier?.name || 'Staff'}</td>
                    <td style="font-family: monospace;">${s.opened_at ? dayjs(s.opened_at).format('HH:mm') : '—'}</td>
                    <td><span class="badge ${s.status === 'open' ? 'badge-green' : 'badge-gray'}">${s.status.toUpperCase()}</span></td>
                    <td class="text-right" style="font-family: monospace;">${format(s.opening_amount)}</td>
                    <td class="text-right" style="font-family: monospace; color: #047857;">+${format(s.summary?.cash_sales || 0)}</td>
                    <td class="text-right" style="font-family: monospace; font-weight: bold;">${format(s.expected_amount ?? s.summary?.expected_cash ?? 0)}</td>
                    <td class="text-right" style="font-family: monospace; font-weight: bold;">${s.closing_amount !== null && s.closing_amount !== undefined ? format(s.closing_amount) : '—'}</td>
                    <td class="text-right" style="font-family: monospace; font-weight: bold;">${varianceVal}</td>
                </tr>
            `
        }).join('')

        tillHtml = `
            <div class="section-title">💼 Till Sessions & Cash Drawer Shifts (${props.tillSessions.length})</div>
            <table>
                <thead>
                    <tr>
                        <th>Shift ID</th>
                        <th>Cashier</th>
                        <th>Opened</th>
                        <th>Status</th>
                        <th class="text-right">Opening Float</th>
                        <th class="text-right">Cash Intake</th>
                        <th class="text-right">Expected Cash</th>
                        <th class="text-right">Counted Cash</th>
                        <th class="text-right">Variance</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `
    }

    // 3. Service Breakdown HTML (if any)
    let serviceHtml = ''
    if (props.serviceBreakdown && props.serviceBreakdown.length > 0) {
        const rows = props.serviceBreakdown.map(s => `
            <tr>
                <td style="font-weight: 600;">${s.service_name}</td>
                <td>${s.pricing_type === 'per_kg' ? '⚖️ Per Kg' : '👕 Per Piece'}</td>
                <td class="text-right" style="font-weight: bold; font-family: monospace;">${s.total_qty} ${s.pricing_type === 'per_kg' ? 'kg' : 'pcs'}</td>
                <td class="text-right" style="font-weight: bold; font-family: monospace; color: #4338ca;">${format(s.total_amount)}</td>
            </tr>
        `).join('')

        serviceHtml = `
            <div class="section-title">👔 Services Rendered Breakdown (${cashierScope})</div>
            <table>
                <thead>
                    <tr>
                        <th>Service Name</th>
                        <th>Pricing Unit</th>
                        <th class="text-right">Quantity / Kg Processed</th>
                        <th class="text-right">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `
    }

    // 4. Intake Orders HTML
    let ordersHtml = ''
    if (props.orders?.data && props.orders.data.length > 0) {
        const rows = props.orders.data.map(o => `
            <tr>
                <td style="font-family: monospace;">${o.received_at ? dayjs(o.received_at).format('HH:mm') : '—'}</td>
                <td style="font-family: monospace; font-weight: bold;">#${o.order_number}</td>
                <td>${o.creator?.full_name || o.creator?.name || o.creator?.username || '—'}</td>
                <td>${o.customer?.name || 'Walk-in'}${o.customer?.phone ? ' <span style="color:#6b7280; font-size:9px;">(' + o.customer.phone + ')</span>' : ''}</td>
                <td class="text-center" style="font-family: monospace;">${o.items?.length || 0}</td>
                <td class="text-center"><span class="badge badge-gray">${(o.status || '').toUpperCase()}</span></td>
                <td class="text-right" style="font-family: monospace; font-weight: bold;">${format(o.total)}</td>
                <td class="text-right" style="font-family: monospace; color: #047857;">${format(o.amount_paid)}</td>
                <td class="text-right" style="font-family: monospace; font-weight: bold; ${Number(o.balance) > 0 ? 'color: #b45309;' : 'color: #047857;'}">${Number(o.balance) > 0 ? format(o.balance) : 'PAID'}</td>
            </tr>
        `).join('')

        ordersHtml = `
            <div class="section-title">🧾 Intake Orders (${props.orders.total ?? props.orders.data.length}) — Total Value: ${format(props.summary.total_revenue)}</div>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Order #</th>
                        <th>Cashier / Staff</th>
                        <th>Customer</th>
                        <th class="text-center">Garments</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `
    }

    // 5. Payments HTML
    let paymentsHtml = ''
    if (props.payments?.data && props.payments.data.length > 0) {
        const rows = props.payments.data.map(p => `
            <tr>
                <td style="font-family: monospace;">${p.paid_at ? dayjs(p.paid_at).format('HH:mm') : '—'}</td>
                <td style="font-family: monospace;">${p.reference || 'PAY-' + p.id}</td>
                <td style="font-family: monospace; font-weight: bold;">${p.order ? '#' + p.order.order_number : '—'}</td>
                <td>${p.recorder?.full_name || p.recorder?.name || p.recorder?.username || '—'}</td>
                <td>${p.order?.customer?.name || 'Walk-in'}</td>
                <td><span style="font-weight: 600; text-transform: capitalize;">${p.method}</span></td>
                <td class="text-center"><span class="badge ${p.type === 'payment' ? 'badge-green' : 'badge-rose'}">${p.type.toUpperCase()}</span></td>
                <td class="text-right" style="font-family: monospace; font-weight: bold; ${p.type === 'refund' ? 'color: #e11d48;' : 'color: #047857;'}">${p.type === 'refund' ? '-' : '+'}${format(p.amount)}</td>
            </tr>
        `).join('')

        paymentsHtml = `
            <div class="section-title">💵 Today's Collections (${props.payments.total ?? props.payments.data.length}) — Net Collections: ${format(props.summary.net_collections)}</div>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Payment Ref</th>
                        <th>Order #</th>
                        <th>Cashier</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th class="text-center">Type</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `
    }

    // 6. Debt Payments HTML
    let debtHtml = ''
    if (props.debtPayments?.data && props.debtPayments.data.length > 0) {
        const rows = props.debtPayments.data.map(d => `
            <tr>
                <td style="font-family: monospace;">${d.created_at ? dayjs(d.created_at).format('HH:mm') : '—'}</td>
                <td style="font-family: monospace;">${d.reference || '—'}</td>
                <td>${d.customer?.name || '—'}</td>
                <td>${d.user?.full_name || d.user?.name || d.user?.username || '—'}</td>
                <td>${d.narration || 'Settlement'}</td>
                <td class="text-right" style="font-family: monospace; font-weight: bold; color: #047857;">${format(d.amount)}</td>
                <td class="text-right" style="font-family: monospace; font-weight: bold;">${format(d.balance_after)}</td>
            </tr>
        `).join('')

        debtHtml = `
            <div class="section-title">⏳ Customer Debt Recoveries (${props.debtPayments.total ?? props.debtPayments.data.length}) — Total Recovered: ${format(props.summary.laundry_debt_recovered)}</div>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Reference</th>
                        <th>Customer</th>
                        <th>Processed By</th>
                        <th>Narration</th>
                        <th class="text-right">Amount Paid</th>
                        <th class="text-right">Balance After</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `
    }

    // 7. Assemble Full Printable Document
    const fullHtml = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laundry End of Day Report - ${branchName} - ${reportDate}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 14mm 12mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #111827;
            background: #ffffff;
            font-size: 10.5px;
            line-height: 1.35;
            padding: 0;
            margin: 0;
        }
        .report-header {
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 18px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #111827;
        }
        .company-sub {
            font-size: 11px;
            color: #4b5563;
            margin-top: 2px;
        }
        .report-badge {
            background: #111827;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-block;
            text-align: right;
            letter-spacing: 0.5px;
        }
        .report-meta {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 10px;
            color: #374151;
            font-family: monospace;
        }
        .section-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #111827;
            margin: 14px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #d1d5db;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            margin-bottom: 12px;
        }
        .kpi-card {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 6px 8px;
            background: #f9fafb;
        }
        .highlight-indigo { border-color: #a5b4fc; background: #eef2ff; }
        .highlight-emerald { border-color: #a7f3d0; background: #ecfdf5; }
        .highlight-teal { border-color: #99f6e4; background: #f0fdfa; }
        .highlight-rose { border-color: #fecdd3; background: #fff1f2; }
        .kpi-label {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #4b5563;
            margin-bottom: 2px;
        }
        .kpi-val {
            font-size: 13px;
            font-weight: 900;
            font-family: monospace;
            color: #111827;
        }
        .kpi-sub {
            font-size: 8.5px;
            color: #6b7280;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .text-amber { color: #d97706 !important; }
        .text-rose { color: #e11d48 !important; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            page-break-inside: auto;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        thead {
            display: table-header-group;
        }
        th {
            background-color: #f3f4f6;
            color: #111827;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 5px 6px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        td {
            padding: 4px 6px;
            border: 1px solid #e5e7eb;
            font-size: 9.5px;
            color: #1f2937;
        }
        tr:nth-child(even) td {
            background-color: #fbfbfb;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-green { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .badge-rose { background: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3; }
        .badge-gray { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
        .signoff-section {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            padding-top: 10px;
            page-break-inside: avoid;
        }
        .signoff-box {
            width: 45%;
            border-top: 1.5px solid #111827;
            padding-top: 6px;
            text-align: center;
            font-size: 9.5px;
        }
        .signoff-role {
            font-weight: 700;
            color: #111827;
        }
        .signoff-hint {
            color: #6b7280;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <div class="header-top">
            <div>
                <div class="company-name">${companyName}</div>
                <div class="company-sub">${address} ${phone ? '• Tel: ' + phone : ''}</div>
            </div>
            <div style="text-align: right;">
                <div class="report-badge">Laundry Shift Z-Report</div>
                <div style="font-size: 9px; color: #6b7280; font-family: monospace; margin-top: 3px;">Generated: ${generatedAt}</div>
            </div>
        </div>
        <div class="report-meta">
            <div><strong>Branch:</strong> ${branchName}</div>
            <div style="text-align: center;"><strong>Report Date:</strong> ${reportDate}</div>
            <div style="text-align: right;"><strong>Cashier / Scope:</strong> ${cashierScope}</div>
        </div>
    </div>

    ${kpiCardsHtml}
    ${tillHtml}
    ${serviceHtml}
    ${ordersHtml}
    ${paymentsHtml}
    ${debtHtml}

    <div class="signoff-section">
        <div class="signoff-box">
            <div class="signoff-role">Prepared By: Cashier / Attendant</div>
            <div class="signoff-hint">Signature & Date</div>
        </div>
        <div class="signoff-box">
            <div class="signoff-role">Audited & Verified By: Branch Manager</div>
            <div class="signoff-hint">Signature & Date</div>
        </div>
    </div>
</body>
</html>
    `

    let iframe = document.getElementById('laundry-eod-print-frame')
    if (!iframe) {
        iframe = document.createElement('iframe')
        iframe.id = 'laundry-eod-print-frame'
        iframe.style.position = 'fixed'
        iframe.style.right = '0'
        iframe.style.bottom = '0'
        iframe.style.width = '0'
        iframe.style.height = '0'
        iframe.style.border = '0'
        document.body.appendChild(iframe)
    }

    const doc = iframe.contentWindow.document
    doc.open()
    doc.write(fullHtml)
    doc.close()

    setTimeout(() => {
        try {
            iframe.contentWindow.focus()
            iframe.contentWindow.print()
        } catch (e) {
            console.error('Direct iframe print failed, falling back to window.print', e)
            window.print()
        }
    }, 200)
}

const statusBadgeClasses = {
    received:    'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300',
    in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-300',
    ready:       'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300',
    collected:   'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-300',
    cancelled:   'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300',
}

const isToday = computed(() => {
    return selectedDate.value === dayjs().format('YYYY-MM-DD')
})

const formattedDisplayDate = computed(() => {
    return dayjs(selectedDate.value).format('dddd, DD MMMM YYYY')
})

const backRoute = computed(() => {
    return props.canSeeAll ? safeRoute('laundry.reports.index') : safeRoute('laundry.home')
})
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 transition-colors print:h-auto print:overflow-visible print:bg-white print:text-black">

        <!-- Header -->
        <div class="print:hidden flex flex-wrap items-center justify-between gap-3 px-6 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <Link :href="backRoute" class="p-1 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition" :title="canSeeAll ? 'Back to Laundry Reports' : 'Back to Laundry Overview'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🧺 Laundry End of Day</span>
                        <span v-if="isToday" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300">
                            Today
                        </span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                        Daily Intake, Collections & Cash Drawer Reconciliation
                    </p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="flex items-center gap-2.5">
                <Link
                    v-if="$page.props.pos_settings?.pos_enabled !== false && ($page.props.auth?.permissions?.canAccessPos || canSeeAll)"
                    :href="safeRoute('pos.reports.end-of-day')"
                    class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                    title="Switch to Retail POS End of Day"
                >
                    <span>🛒 Switch to POS End of Day</span>
                </Link>

                <button
                    type="button"
                    @click="printSummary"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>Print Report</span>
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="print:hidden px-6 py-3 border-b border-slate-200 dark:border-slate-700/80 flex flex-wrap items-center justify-between gap-3 flex-shrink-0 bg-slate-100/70 dark:bg-slate-800/40">
            <div class="flex flex-wrap items-center gap-3">
                <!-- Staff Filter -->
                <div v-if="canSeeAll" class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 whitespace-nowrap">Staff:</label>
                    <select v-model="selectedUser" @change="doFilter"
                        class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white px-3 py-1.5 rounded-lg text-xs font-medium outline-none border border-slate-300 dark:border-slate-600 focus:border-indigo-500 transition min-w-[200px]">
                        <option value="" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">All Cashiers & Staff</option>
                        <option v-for="u in users" :key="u.id" :value="u.id" class="bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            {{ u.name || u.username }}
                        </option>
                    </select>
                </div>
                <div v-else class="flex items-center gap-2 bg-white dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 px-3 py-1.5 rounded-lg text-xs">
                    <span class="text-indigo-600 dark:text-indigo-400 font-bold">👤</span>
                    <span class="text-slate-800 dark:text-slate-200 font-medium">My Orders & Shift Only</span>
                </div>

                <!-- Date Navigation Controls -->
                <div class="flex items-center gap-1 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg p-0.5 shadow-2xs">
                    <button
                        type="button"
                        @click="changeDate(-1)"
                        class="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 transition"
                        title="Previous Day"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <input
                        type="date"
                        v-model="selectedDate"
                        @change="doFilter"
                        class="bg-transparent text-xs font-mono font-semibold text-slate-800 dark:text-slate-200 border-none outline-none px-2 py-0.5 focus:ring-0"
                    />

                    <button
                        type="button"
                        @click="changeDate(1)"
                        class="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 transition"
                        title="Next Day"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <button
                    v-if="!isToday"
                    type="button"
                    @click="setDateToday"
                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 transition"
                >
                    Jump to Today
                </button>
            </div>

            <!-- Date Label Display -->
            <div class="text-xs font-mono text-slate-600 dark:text-slate-300 font-medium">
                {{ formattedDisplayDate }}
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6 print:p-0 print:space-y-6 print:overflow-visible print:h-auto">

            <!-- ── Printable Header (Only Visible on Print) ──────────────────── -->
            <div class="hidden print:block border-b-2 border-slate-900 pb-4 mb-6">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <h1 class="text-xl font-black uppercase tracking-wider text-slate-900">
                            {{ $page.props.system_config?.company_name || $page.props.settings?.business_name || $page.props.current_branch?.name || 'SkyNet Laundry POS' }}
                        </h1>
                        <p class="text-xs text-slate-600 font-medium">
                            {{ $page.props.current_branch?.address || $page.props.settings?.address || 'Laundry & Dry Cleaning Operations' }}
                            <span v-if="$page.props.current_branch?.phone || $page.props.settings?.phone"> • Tel: {{ $page.props.current_branch?.phone || $page.props.settings?.phone }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-2.5 py-1 text-xs font-black uppercase tracking-wider bg-slate-900 text-white rounded">
                            Laundry Shift Z-Report
                        </span>
                        <p class="text-[10px] text-slate-500 font-mono mt-1">Generated: {{ dayjs().format('DD/MM/YYYY HH:mm:ss') }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-300 text-xs font-mono text-slate-800">
                    <div><strong>Branch:</strong> {{ $page.props.current_branch?.name || 'Current Branch' }}</div>
                    <div class="text-center"><strong>Report Date:</strong> {{ formattedDisplayDate }}</div>
                    <div class="text-right"><strong>Cashier / Scope:</strong> {{ selectedUser ? (users.find(u => u.id == selectedUser)?.name || 'Selected Staff') : 'All Cashiers & Staff' }}</div>
                </div>
            </div>

            <!-- ── Summary KPI Cards ─────────────────────────────────────── -->
            <div class="space-y-4">
                <!-- Row 1: Intake & Collections (5 Columns) -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5">
                    <!-- Intake Orders -->
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Intake Orders</p>
                            <span class="text-base">🧺</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono">{{ summary.total_orders }}</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-1 truncate" :title="format(summary.total_revenue)">
                            Worth {{ format(summary.total_revenue) }}
                        </p>
                    </div>

                    <!-- Net Collections -->
                    <div class="bg-white dark:bg-slate-800 border border-indigo-200/80 dark:border-indigo-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-indigo-700 dark:text-indigo-400 font-bold">Net Collections</p>
                            <span class="text-base text-indigo-500">💰</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono truncate" :title="format(summary.net_collections)">
                            {{ format(summary.net_collections) }}
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-1 truncate">
                            Gross: {{ format(summary.gross_collections) }}
                        </p>
                    </div>

                    <!-- Cash Collected -->
                    <div class="bg-white dark:bg-slate-800 border border-emerald-200/80 dark:border-emerald-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-emerald-700 dark:text-emerald-400 font-bold">Cash in Drawer</p>
                            <span class="text-base text-emerald-500">💵</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono truncate" :title="format(summary.cash_collected)">
                            {{ format(summary.cash_collected) }}
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-1">
                            Physical cash collected
                        </p>
                    </div>

                    <!-- Bank / Electronic Transfers -->
                    <div class="bg-white dark:bg-slate-800 border border-blue-200/80 dark:border-blue-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-blue-700 dark:text-blue-400 font-bold">Bank Transfers</p>
                            <span class="text-base text-blue-500">💳</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-blue-600 dark:text-blue-400 font-mono truncate" :title="format(summary.bank_collected)">
                            {{ format(summary.bank_collected) }}
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-1">
                            Electronic & POS
                        </p>
                    </div>

                    <!-- Discounts & Savings -->
                    <div class="bg-white dark:bg-slate-800 border border-purple-200/80 dark:border-purple-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-purple-700 dark:text-purple-400 font-bold">Discounts Given</p>
                            <span class="text-base text-purple-500">🏷️</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono truncate" :title="format(summary.total_discount)">
                            {{ format(summary.total_discount) }}
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-1">
                            Order & Service Promos
                        </p>
                    </div>
                </div>

                <!-- Row 2: Debt, Deliveries & Cash Flow (5 Columns) -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5">
                    <!-- Unpaid Debt Recorded Today -->
                    <div class="bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-amber-700 dark:text-amber-400 font-semibold">Unpaid Receivables</p>
                            <span class="text-base text-amber-500">⏳</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400 font-mono truncate" :title="format(summary.debt_recorded)">
                            {{ format(summary.debt_recorded) }}
                        </p>
                        <p class="text-[11px] text-amber-700/80 dark:text-amber-400/80 font-mono mt-1">
                            Balance on today's intake
                        </p>
                    </div>

                    <!-- Laundry Debt Recovered -->
                    <div class="bg-emerald-50/70 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-700/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-emerald-700 dark:text-emerald-400 font-semibold">Debt Recovered</p>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-bold">
                                {{ debtPayments.total ?? 0 }} {{ (debtPayments.total ?? 0) === 1 ? 'pmt' : 'pmts' }}
                            </span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-300 font-mono truncate" :title="format(summary.laundry_debt_recovered)">
                            {{ format(summary.laundry_debt_recovered) }}
                        </p>
                        <p class="text-[11px] text-emerald-700/80 dark:text-emerald-400/80 font-mono mt-1">
                            Customer debt settled
                        </p>
                    </div>

                    <!-- Pickups / Handed Over Today -->
                    <div class="bg-purple-50/50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-purple-700 dark:text-purple-400 font-semibold">Orders Picked Up</p>
                            <span class="text-base text-purple-500">🚚</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-purple-600 dark:text-purple-300 font-mono">
                            {{ summary.pickups_completed }}
                        </p>
                        <p class="text-[11px] text-purple-700/80 dark:text-purple-400/80 font-mono mt-1">
                            Garments handed to client
                        </p>
                    </div>

                    <!-- Daily Store Expenses -->
                    <div class="bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/40 rounded-xl p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs text-rose-700 dark:text-rose-400 font-semibold">Daily Expenses</p>
                            <span class="text-base text-rose-500">💸</span>
                        </div>
                        <p class="text-xl sm:text-2xl font-bold text-rose-600 dark:text-rose-400 font-mono truncate" :title="format(summary.total_expenses)">
                            {{ format(summary.total_expenses) }}
                        </p>
                        <p class="text-[11px] text-rose-700/80 dark:text-rose-400/80 font-mono mt-1">
                            Logged store costs
                        </p>
                    </div>

                    <!-- Net Cash Flow -->
                    <div :class="[
                        'rounded-xl p-4 shadow-xs border',
                        summary.net_cash_flow >= 0
                            ? 'bg-teal-50/50 dark:bg-teal-950/20 border-teal-200 dark:border-teal-800/40'
                            : 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-200 dark:border-rose-800/40'
                    ]">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Net Cash Flow</p>
                            <span class="text-base">⚖️</span>
                        </div>
                        <p :class="[
                            'text-xl sm:text-2xl font-black font-mono truncate',
                            summary.net_cash_flow >= 0 ? 'text-teal-600 dark:text-teal-400' : 'text-rose-600 dark:text-rose-400'
                        ]" :title="format(summary.net_cash_flow)">
                            {{ format(summary.net_cash_flow) }}
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-1">
                            (Cash + Recov) - Exp
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── Till Sessions & Cash Drawer Reconciliation ────────────── -->
            <div v-if="tillSessions && tillSessions.length">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-300 flex items-center gap-2">
                        <span>💼 Till Sessions & Cash Drawer Shifts</span>
                        <span class="text-xs font-normal text-slate-500 dark:text-slate-400">({{ tillSessions.length }} Shifts on this date)</span>
                    </h2>

                    <Link
                        :href="safeRoute('pos.till.sessions.index')"
                        class="print:hidden text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                    >
                        <span>View Till History →</span>
                    </Link>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs print:border-slate-300 print:shadow-none">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs min-w-[750px]">
                            <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                <tr>
                                    <th class="text-left px-4 py-2.5 font-bold">Shift ID</th>
                                    <th class="text-left px-4 py-2.5 font-bold">Cashier</th>
                                    <th class="text-left px-4 py-2.5 font-bold">Opened</th>
                                    <th class="text-left px-4 py-2.5 font-bold">Status</th>
                                    <th class="text-right px-4 py-2.5 font-bold">Opening Float</th>
                                    <th class="text-right px-4 py-2.5 font-bold">Cash Intake</th>
                                    <th class="text-right px-4 py-2.5 font-bold">Expected Cash</th>
                                    <th class="text-right px-4 py-2.5 font-bold">Counted Cash</th>
                                    <th class="text-right px-4 py-2.5 font-bold">Variance</th>
                                    <th class="print:hidden text-center px-4 py-2.5 font-bold">Slip</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                                <tr v-for="ts in tillSessions" :key="ts.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-4 py-2.5 font-mono font-bold text-slate-900 dark:text-white">#{{ ts.id }}</td>
                                    <td class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-200">{{ ts.cashier?.name || 'Staff' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-slate-500">{{ dayjs(ts.opened_at).format('HH:mm') }}</td>
                                    <td class="px-4 py-2.5">
                                        <span :class="[
                                            'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                            ts.status === 'open'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300'
                                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                        ]">
                                            {{ ts.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono font-semibold">{{ format(ts.opening_amount) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-emerald-600 dark:text-emerald-400 font-semibold">+{{ format(ts.summary?.cash_sales || 0) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900 dark:text-white">{{ format(ts.expected_amount ?? ts.summary?.expected_cash ?? 0) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold">{{ ts.closing_amount !== null && ts.closing_amount !== undefined ? format(ts.closing_amount) : '—' }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold">
                                        <span v-if="ts.status === 'closed'" :class="[
                                            'px-1.5 py-0.5 rounded text-[11px]',
                                            ts.variance == 0 ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50' :
                                            ts.variance < 0 ? 'text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/50' : 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/50'
                                        ]">
                                            {{ ts.variance > 0 ? '+' : '' }}{{ format(ts.variance) }}
                                        </span>
                                        <span v-else class="text-slate-400 text-[10px]">Open Shift</span>
                                    </td>
                                    <td class="print:hidden px-4 py-2.5 text-center">
                                        <button
                                            type="button"
                                            @click="activeSlipSession = ts"
                                            class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-[11px] font-bold cursor-pointer transition"
                                            title="Preview Till Slip"
                                        >
                                            🧾 Slip
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Services Rendered Breakdown ───────────────────────────── -->
            <div v-if="serviceBreakdown && serviceBreakdown.length">
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-300 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span>Services Rendered Breakdown ({{ selectedUser ? (users.find(u => u.id == selectedUser)?.name || 'Selected Staff') : 'All Staff' }})</span>
                </h2>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs min-w-[500px]">
                            <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                <tr>
                                    <th class="text-left px-5 py-3 font-semibold">Service Name</th>
                                    <th class="text-left px-5 py-3 font-semibold">Pricing Unit</th>
                                    <th class="text-right px-5 py-3 font-semibold">Quantity / Kg Processed</th>
                                    <th class="text-right px-5 py-3 font-semibold">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                                <tr v-for="item in serviceBreakdown" :key="`${item.service_name}-${item.pricing_type}`" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-5 py-2.5 text-slate-900 dark:text-white font-medium">{{ item.service_name }}</td>
                                    <td class="px-5 py-2.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                            {{ item.pricing_type === 'per_kg' ? '⚖️ Per Kg' : '👕 Per Piece' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-2.5 text-right font-mono font-bold text-slate-900 dark:text-white">
                                        {{ item.total_qty }} {{ item.pricing_type === 'per_kg' ? 'kg' : 'pcs' }}
                                    </td>
                                    <td class="px-5 py-2.5 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ format(item.total_amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ── Activity Tables with Tab Navigation ───────────────────── -->
            <div class="space-y-3">
                <!-- Tab Controls -->
                <div class="print:hidden flex items-center gap-2 border-b border-slate-200 dark:border-slate-700 pb-2">
                    <button
                        type="button"
                        @click="activeTab = 'orders'"
                        :class="[
                            'flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer',
                            activeTab === 'orders'
                                ? 'bg-indigo-600 text-white shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60'
                        ]"
                    >
                        <span>🧾 Intake Orders</span>
                        <span :class="[
                            'px-1.5 py-0.2 rounded-full text-[10px] font-mono',
                            activeTab === 'orders' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'
                        ]">
                            {{ orders.total ?? orders.data?.length ?? 0 }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'payments'"
                        :class="[
                            'flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer',
                            activeTab === 'payments'
                                ? 'bg-indigo-600 text-white shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60'
                        ]"
                    >
                        <span>💵 Collections Today</span>
                        <span :class="[
                            'px-1.5 py-0.2 rounded-full text-[10px] font-mono',
                            activeTab === 'payments' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'
                        ]">
                            {{ payments.total ?? payments.data?.length ?? 0 }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'debt'"
                        :class="[
                            'flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer',
                            activeTab === 'debt'
                                ? 'bg-indigo-600 text-white shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60'
                        ]"
                    >
                        <span>⏳ Debt Recoveries</span>
                        <span :class="[
                            'px-1.5 py-0.2 rounded-full text-[10px] font-mono',
                            activeTab === 'debt' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'
                        ]">
                            {{ debtPayments.total ?? debtPayments.data?.length ?? 0 }}
                        </span>
                    </button>
                </div>

                <!-- ── 1. Orders Intake Table ───────────────────────────── -->
                <div :class="[activeTab === 'orders' ? 'block' : 'hidden print:block', 'bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs print:border-slate-300 print:shadow-none print:mb-6']">
                    <div class="hidden print:flex items-center justify-between px-4 py-2 bg-slate-100 border-b border-slate-300 font-bold text-xs text-slate-800">
                        <span>Intake Orders ({{ orders.total ?? orders.data?.length ?? 0 }})</span>
                        <span class="font-mono">Total Value: {{ format(summary.total_revenue) }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs min-w-[700px]">
                            <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold">Time</th>
                                    <th class="text-left px-4 py-3 font-semibold">Order #</th>
                                    <th class="text-left px-4 py-3 font-semibold">Cashier / Staff</th>
                                    <th class="text-left px-4 py-3 font-semibold">Customer</th>
                                    <th class="text-center px-4 py-3 font-semibold">Garments</th>
                                    <th class="text-center px-4 py-3 font-semibold">Status</th>
                                    <th class="text-right px-4 py-3 font-semibold">Total</th>
                                    <th class="text-right px-4 py-3 font-semibold">Paid</th>
                                    <th class="text-right px-4 py-3 font-semibold">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                                <tr v-for="ord in orders.data" :key="ord.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400 font-mono">{{ fmtTime(ord.received_at) }}</td>
                                    <td class="px-4 py-2.5 font-mono font-bold">
                                        <Link :href="safeRoute('laundry.orders.show', { order: ord.id })" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            #{{ ord.order_number }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 font-medium">
                                        {{ ord.creator?.full_name || ord.creator?.name || ord.creator?.username || '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-800 dark:text-slate-200">
                                        <div class="font-semibold">{{ ord.customer?.name ?? 'Walk-in' }}</div>
                                        <div v-if="ord.customer?.phone" class="text-[10px] text-slate-400 font-mono">{{ ord.customer.phone }}</div>
                                    </td>
                                    <td class="px-4 py-2.5 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                        {{ ord.items?.length || 0 }} {{ (ord.items?.length || 0) === 1 ? 'item' : 'items' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider', statusBadgeClasses[ord.status] || 'bg-slate-100 text-slate-700']">
                                            {{ ord.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900 dark:text-white">
                                        {{ format(ord.total) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ format(ord.amount_paid) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono">
                                        <span v-if="Number(ord.balance) > 0" class="text-amber-600 dark:text-amber-400 font-bold">
                                            {{ format(ord.balance) }}
                                        </span>
                                        <span v-else class="text-emerald-600 dark:text-emerald-400 font-bold">
                                            Paid
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!orders.data?.length">
                                    <td colspan="9" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400 text-sm">
                                        No intake orders recorded on this date for the selected filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <PaginationLinks class="print:hidden" :links="orders.links" :meta="orders.meta ?? orders" />
                </div>

                <!-- ── 2. Payments Collected Table ──────────────────────── -->
                <div :class="[activeTab === 'payments' ? 'block' : 'hidden print:block', 'bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs print:border-slate-300 print:shadow-none print:mb-6']">
                    <div class="hidden print:flex items-center justify-between px-4 py-2 bg-slate-100 border-b border-slate-300 font-bold text-xs text-slate-800">
                        <span>Today's Collections ({{ payments.total ?? payments.data?.length ?? 0 }})</span>
                        <span class="font-mono">Net Collections: {{ format(summary.net_collections) }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs min-w-[700px]">
                            <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold">Time</th>
                                    <th class="text-left px-4 py-3 font-semibold">Payment Ref</th>
                                    <th class="text-left px-4 py-3 font-semibold">Order #</th>
                                    <th class="text-left px-4 py-3 font-semibold">Cashier</th>
                                    <th class="text-left px-4 py-3 font-semibold">Customer</th>
                                    <th class="text-left px-4 py-3 font-semibold">Method</th>
                                    <th class="text-center px-4 py-3 font-semibold">Type</th>
                                    <th class="text-right px-4 py-3 font-semibold">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                                <tr v-for="pay in payments.data" :key="pay.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400 font-mono">{{ fmtTime(pay.paid_at) }}</td>
                                    <td class="px-4 py-2.5 font-mono text-slate-700 dark:text-slate-300">
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700">
                                            {{ pay.reference || ('PAY-' + pay.id) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 font-mono font-bold">
                                        <Link v-if="pay.order" :href="safeRoute('laundry.orders.show', { order: pay.order.id })" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            #{{ pay.order.order_number }}
                                        </Link>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 font-medium">
                                        {{ pay.recorder?.full_name || pay.recorder?.name || pay.recorder?.username || '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-800 dark:text-slate-200">
                                        {{ pay.order?.customer?.name || 'Walk-in' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-semibold text-slate-800 dark:text-slate-200 capitalize">
                                            {{ pay.method }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span :class="[
                                            'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                            pay.type === 'payment'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300'
                                        ]">
                                            {{ pay.type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold" :class="pay.type === 'refund' ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">
                                        {{ pay.type === 'refund' ? '-' : '+' }}{{ format(pay.amount) }}
                                    </td>
                                </tr>
                                <tr v-if="!payments.data?.length">
                                    <td colspan="8" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400 text-sm">
                                        No payments collected on this date for the selected filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <PaginationLinks class="print:hidden" :links="payments.links" :meta="payments.meta ?? payments" />
                </div>

                <!-- ── 3. Debt Recovery Table ───────────────────────────── -->
                <div :class="[activeTab === 'debt' ? 'block' : 'hidden print:block', 'bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-xs print:border-slate-300 print:shadow-none print:mb-6']">
                    <div class="hidden print:flex items-center justify-between px-4 py-2 bg-slate-100 border-b border-slate-300 font-bold text-xs text-slate-800">
                        <span>Debt Settlements & Recoveries ({{ debtPayments.total ?? debtPayments.data?.length ?? 0 }})</span>
                        <span class="font-mono">Total Recovered: {{ format(summary.laundry_debt_recovered) }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs min-w-[700px]">
                            <thead class="bg-slate-100 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold">Time</th>
                                    <th class="text-left px-4 py-3 font-semibold">Reference</th>
                                    <th class="text-left px-4 py-3 font-semibold">Customer</th>
                                    <th class="text-left px-4 py-3 font-semibold">Processed By</th>
                                    <th class="text-left px-4 py-3 font-semibold">Narration</th>
                                    <th class="text-right px-4 py-3 font-semibold">Amount Paid</th>
                                    <th class="text-right px-4 py-3 font-semibold">Balance After</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/50">
                                <tr v-for="dp in debtPayments.data" :key="dp.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400 font-mono">{{ fmtTime(dp.created_at) }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded text-slate-700 dark:text-slate-300">
                                            {{ dp.reference ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-900 dark:text-slate-300 font-medium">{{ dp.customer?.name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400 font-medium">
                                        {{ dp.user?.full_name || dp.user?.name || dp.user?.username || '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400 max-w-[260px]">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                                🧺 Laundry
                                            </span>
                                            <span class="truncate">{{ dp.narration }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-emerald-600 dark:text-emerald-400 font-bold font-mono text-right">{{ format(dp.amount) }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span :class="['font-mono font-bold', dp.balance_after > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400']">
                                            {{ format(dp.balance_after) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!debtPayments.data?.length">
                                    <td colspan="7" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400 text-sm">
                                        No laundry debt recoveries recorded on this date for the selected filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <PaginationLinks class="print:hidden" :links="debtPayments.links" :meta="debtPayments.meta ?? debtPayments" />
                </div>
            </div>

            <!-- ── Sign-off Section for Physical Print Out (Print Only) ─────── -->
            <div class="hidden print:grid grid-cols-2 gap-12 pt-8 pb-4 text-xs">
                <div class="border-t-2 border-slate-900 pt-2 text-center">
                    <p class="font-bold text-slate-900">Prepared By: Cashier / Attendant</p>
                    <p class="text-slate-500 mt-1">Signature & Date</p>
                </div>
                <div class="border-t-2 border-slate-900 pt-2 text-center">
                    <p class="font-bold text-slate-900">Audited & Verified By: Branch Manager</p>
                    <p class="text-slate-500 mt-1">Signature & Date</p>
                </div>
            </div>

        </div>

        <!-- Slip Modal Preview -->
        <TillSlipModal
            v-if="activeSlipSession"
            :session="activeSlipSession"
            :summary="activeSlipSession.summary"
            @close="activeSlipSession = null"
        />
    </div>
</template>

<style>
@media print {
    @page {
        margin: 10mm 12mm 14mm 12mm;
        size: A4 portrait;
    }
    html, body, #app, main {
        background-color: #ffffff !important;
        color: #000000 !important;
        overflow: visible !important;
        height: auto !important;
        min-height: 100% !important;
        max-height: none !important;
        display: block !important;
    }
    aside, header, nav, .print\:hidden, .no-print {
        display: none !important;
    }
    .print\:block {
        display: block !important;
    }
    .print\:grid {
        display: grid !important;
    }
    .print\:flex {
        display: flex !important;
    }
    .overflow-y-auto, .overflow-x-auto, .overflow-hidden {
        overflow: visible !important;
        height: auto !important;
        max-height: none !important;
    }
    table {
        page-break-inside: auto;
        width: 100% !important;
        color: #000000 !important;
    }
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    thead {
        display: table-header-group;
    }
    tfoot {
        display: table-footer-group;
    }
    th, td {
        color: #000000 !important;
        border-color: #d1d5db !important;
    }
    p, span, h1, h2, h3, h4, div {
        color: #000000 !important;
        text-shadow: none !important;
    }
}
</style>
