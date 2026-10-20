import { useEffect, useMemo, useState } from 'react'
import * as Icons from 'lucide-react'

const navItems = [
  { id: 'overview', label: 'Overview', icon: Icons.LayoutDashboard, roles: ['Admin', 'Staff', 'Member'] },
  { id: 'members', label: 'Members', icon: Icons.UsersRound, roles: ['Admin', 'Staff'] },
  { id: 'attendance', label: 'Access logs', icon: Icons.ScanLine, roles: ['Admin', 'Staff'] },
  { id: 'reservations', label: 'Reservations', icon: Icons.CalendarDays, roles: ['Admin', 'Staff', 'Member'] },
  { id: 'maintenance', label: 'Maintenance', icon: Icons.Wrench, roles: ['Admin', 'Staff'] },
  { id: 'analytics', label: 'Analytics', icon: Icons.ChartNoAxesCombined, roles: ['Admin'] },
  { id: 'rfid', label: 'RFID control', icon: Icons.RadioTower, roles: ['Admin', 'Staff', 'Member'] }
]

const roleCopy = {
  Admin: { eyebrow: 'OWNER / ADMINISTRATOR', title: 'The floor, at a glance.', subtitle: 'Run the gym from one live operating surface.' },
  Staff: { eyebrow: 'STAFF OPERATIONS', title: 'Keep the floor moving.', subtitle: 'Access, members, and maintenance without the noise.' },
  Member: { eyebrow: 'MEMBER PORTAL', title: 'Your training window.', subtitle: 'Reserve your slot, track your access, and show up ready.' }
}

const api = async (url, options) => {
  const response = await fetch(url, { headers: { 'Content-Type': 'application/json' }, ...options })
  const body = await response.json()
  if (!response.ok) throw new Error(body.message || 'Something went wrong')
  return body
}

const formatTime = (value) => new Intl.DateTimeFormat('en-PH', { hour: '2-digit', minute: '2-digit' }).format(new Date(value))
const formatDate = (value) => new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(value))
const initials = (name = '') => name.split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase()
const downloadCSV = (rows, filename) => {
  if (!rows?.length) return
  const headers = Object.keys(rows[0])
  const csv = [headers.join(','), ...rows.map((row) => headers.map((header) => JSON.stringify(row[header] ?? '')).join(','))].join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  link.click()
  URL.revokeObjectURL(link.href)
}

function App() {
  const [role, setRole] = useState('Admin')
  const [page, setPage] = useState('overview')
  const [data, setData] = useState({ dashboard: null, members: [], attendance: [], reservations: [], maintenance: [] })
  const [loading, setLoading] = useState(true)
  const [toast, setToast] = useState(null)
  const [modal, setModal] = useState(null)
  const [query, setQuery] = useState('')
  const [showNotifications, setShowNotifications] = useState(false)

  const visibleNav = navItems.filter((item) => item.roles.includes(role))
  const copy = roleCopy[role]

  const refresh = async () => {
    setLoading(true)
    try {
      const [dashboard, members, attendance, reservations, maintenance] = await Promise.all([
        api('/api/dashboard'), api('/api/members'), api('/api/attendance'), api('/api/reservations'), api('/api/maintenance')
      ])
      setData({ dashboard, members, attendance, reservations, maintenance })
    } catch (error) {
      notify(error.message, 'error')
    } finally {
      setLoading(false)
    }
  }

  const notify = (message, tone = 'success') => {
    setToast({ message, tone })
    window.setTimeout(() => setToast(null), 3200)
  }

  useEffect(() => { refresh() }, [])
  useEffect(() => {
    const allowed = visibleNav.some((item) => item.id === page)
    if (!allowed) setPage('overview')
  }, [role])

  const handleRole = (nextRole) => {
    setRole(nextRole)
    setPage('overview')
    notify(`Viewing ${nextRole.toLowerCase()} workspace`, 'info')
  }

  const runAction = async (action, success) => {
    try {
      await action()
      setModal(null)
      await refresh()
      notify(success)
    } catch (error) { notify(error.message, 'error') }
  }

  const pageTitle = navItems.find((item) => item.id === page)?.label || 'Overview'

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand" onClick={() => setPage('overview')} role="button" tabIndex="0">
          <div className="brand-mark"><span>SG</span><i /></div>
          <div><strong>smartgym</strong><small>OPS SYSTEM</small></div>
        </div>
        <div className="rail-label">Workspace</div>
        <nav className="nav-list">
          {visibleNav.map((item) => <button key={item.id} className={`nav-item ${page === item.id ? 'active' : ''}`} onClick={() => setPage(item.id)}><item.icon size={17} strokeWidth={1.8} /><span>{item.label}</span>{item.id === 'rfid' && <b className="live-dot" />}</button>)}
        </nav>
        <div className="sidebar-bottom">
          <div className="system-card"><div className="status-line"><span className="pulse" /> All systems nominal</div><small>Last sync · just now</small></div>
          <button className="nav-item muted" onClick={() => setModal('settings')}><Icons.Settings2 size={17} /><span>System settings</span></button>
          <div className="user-mini"><div className="avatar lime">MS</div><div><strong>{role === 'Admin' ? 'Maya Santos' : role === 'Staff' ? 'Jonas Cruz' : 'Paolo Reyes'}</strong><small>{role}</small></div><Icons.MoreHorizontal size={17} /></div>
        </div>
      </aside>

      <main className="main-content">
        <header className="topbar">
          <div className="breadcrumb"><span>SMARTGYM</span><Icons.ChevronRight size={14} /><strong>{pageTitle.toUpperCase()}</strong></div>
          <div className="top-actions"><div className="notification-wrap"><button className="icon-button" aria-label="Notifications" onClick={() => setShowNotifications((value) => !value)}><Icons.Bell size={18} /><span className="notification-dot" /></button>{showNotifications && <div className="notification-popover"><div className="popover-head"><strong>Notifications</strong><span>2 new</span></div><div className="notification-item"><span className="queue-icon amber"><Icons.Wrench size={14} /></span><div><strong>Cable Crossover</strong><small>Maintenance update needed</small></div></div><div className="notification-item"><span className="queue-icon blue"><Icons.CalendarDays size={14} /></span><div><strong>Next workout block</strong><small>2 reservations are pending</small></div></div></div>}</div><div className="role-switcher"><span>View as</span><select value={role} onChange={(event) => handleRole(event.target.value)}><option>Admin</option><option>Staff</option><option>Member</option></select></div></div>
        </header>

        <div className="page-wrap">
          {page === 'overview' && <Overview data={data} copy={copy} loading={loading} setPage={setPage} onRefresh={refresh} />}
          {page === 'members' && <Members data={data} query={query} setQuery={setQuery} openModal={setModal} />}
          {page === 'attendance' && <Attendance rows={data.attendance} />}
          {page === 'reservations' && <Reservations rows={data.reservations} role={role} openModal={setModal} runAction={runAction} />}
          {page === 'maintenance' && <Maintenance rows={data.maintenance} openModal={setModal} runAction={runAction} />}
          {page === 'analytics' && <Analytics dashboard={data.dashboard} />}
          {page === 'rfid' && <RFID members={data.members} dashboard={data.dashboard} openModal={setModal} notify={notify} />}
        </div>
      </main>

      {modal === 'member' && <MemberModal onClose={() => setModal(null)} runAction={runAction} />}
      {modal === 'maintenance' && <MaintenanceModal onClose={() => setModal(null)} runAction={runAction} />}
      {modal === 'reservation' && <ReservationModal onClose={() => setModal(null)} runAction={runAction} />}
      {modal === 'settings' && <SettingsModal onClose={() => setModal(null)} notify={notify} />}
      {toast && <div className={`toast ${toast.tone}`}><span className="toast-icon">{toast.tone === 'error' ? '!' : '✓'}</span>{toast.message}</div>}
    </div>
  )
}

function Overview({ data, copy, loading, setPage, onRefresh }) {
  const dashboard = data.dashboard
  if (loading || !dashboard) return <div className="loading-state"><div className="loader" /><span>Syncing the floor data…</span></div>
  const cap = dashboard.capacity
  return <>
    <section className="hero-row"><div><div className="eyebrow">{copy.eyebrow}</div><h1>{copy.title}</h1><p className="hero-subtitle">{copy.subtitle}</p></div><div className="hero-meta"><div className="live-pill"><span className="pulse" /> LIVE CAPACITY</div><button className="button ghost" onClick={onRefresh}><Icons.RefreshCw size={15} /> Refresh data</button></div></section>
    <section className="metric-grid">
      <Metric label="People on floor" value={cap.inside} suffix={`/ ${cap.max}`} accent="lime" note={`${cap.percentage}% capacity`} icon={Icons.Activity} />
      <Metric label="Active members" value={dashboard.activeMembers} note="+8.4% this month" icon={Icons.UsersRound} />
      <Metric label="Check-ins today" value={dashboard.checkInsToday} note="Peak at 17:00–19:00" icon={Icons.ScanLine} />
      <Metric label="Open maintenance" value={dashboard.openMaintenance} note="Needs attention" accent="coral" icon={Icons.Wrench} />
    </section>
    <section className="operation-grid">
      <div className="panel capacity-panel"><div className="panel-head"><div><div className="eyebrow">THE FLOOR · RIGHT NOW</div><h2>Capacity is healthy.</h2></div><span className="status-chip good"><span className="pulse" /> Open</span></div><div className="capacity-visual"><div className="capacity-ring" style={{ '--progress': `${Math.max(cap.percentage, 8)}%` }}><div><strong>{cap.inside}</strong><small>inside now</small></div></div><div className="capacity-copy"><div className="big-percent">{cap.percentage}<span>%</span></div><p>of the recommended 80-person floor capacity.</p><div className="capacity-bar"><span style={{ width: `${Math.max(cap.percentage, 8)}%` }} /></div><div className="capacity-legend"><span><i className="legend-dot lime-dot" /> In the gym</span><span><i className="legend-dot grey-dot" /> Available</span></div></div></div><div className="panel-foot"><span>Last RFID event · {formatTime(data.recentActivity?.[0]?.timestamp || new Date())}</span><button className="text-button" onClick={() => setPage('rfid')}>Open access control <Icons.ArrowUpRight size={14} /></button></div></div>
      <div className="panel activity-panel"><div className="panel-head"><div><div className="eyebrow">ACTIVITY STREAM</div><h2>Recent access</h2></div><button className="text-button" onClick={() => setPage('attendance')}>View all <Icons.ArrowUpRight size={14} /></button></div><div className="activity-list">{dashboard.recentActivity.map((item) => <ActivityRow key={item.log_id} item={item} />)}</div></div>
    </section>
    <section className="lower-grid"><div className="panel chart-panel"><div className="panel-head"><div><div className="eyebrow">FLOOR TRAFFIC</div><h2>Peak hours</h2></div><span className="chart-note">Last 7 days <Icons.ChevronDown size={14} /></span></div><MiniChart points={dashboard.peakHours} /></div><div className="panel attention-panel"><div className="panel-head"><div><div className="eyebrow">NEEDS ATTENTION</div><h2>Operations queue</h2></div><span className="count-badge">{dashboard.openMaintenance}</span></div><div className="queue-list"><QueueItem icon={Icons.Wrench} title="Cable Crossover" detail="Under repair · pulley noise" tone="amber" onClick={() => setPage('maintenance')} /><QueueItem icon={Icons.CalendarDays} title="Next workout block" detail={`${dashboard.reservationsToday} reservations scheduled`} tone="blue" onClick={() => setPage('reservations')} /><QueueItem icon={Icons.RadioTower} title="RFID gateway" detail="ESP32-FRONT-01 · connected" tone="lime" onClick={() => setPage('rfid')} /></div></div></section>
  </>
}

function Metric({ label, value, suffix, note, accent, icon: Icon }) { return <div className={`metric-card ${accent || ''}`}><div className="metric-top"><span>{label}</span><Icon size={17} /></div><div className="metric-value">{value}<small>{suffix}</small></div><div className="metric-note"><span className={accent === 'coral' ? 'coral-text' : accent === 'lime' ? 'lime-text' : ''}>{note}</span><Icons.TrendingUp size={13} /></div></div> }
function ActivityRow({ item }) { return <div className="activity-row"><div className={`avatar ${item.log_type === 'IN' ? 'lime' : 'ink'}`}>{initials(item.name)}</div><div className="activity-main"><strong>{item.name}</strong><span>{item.log_type === 'IN' ? 'Entered the gym' : 'Checked out'} · {item.device_id}</span></div><div className={`event-type ${item.log_type === 'IN' ? 'in' : 'out'}`}>{item.log_type}<small>{formatTime(item.timestamp)}</small></div></div> }
function QueueItem({ icon: Icon, title, detail, tone, onClick }) { return <button className="queue-item" onClick={onClick}><span className={`queue-icon ${tone}`}><Icon size={16} /></span><span><strong>{title}</strong><small>{detail}</small></span><Icons.ChevronRight size={15} /></button> }
function MiniChart({ points }) { const max = Math.max(...points.map((point) => point.value)); return <div className="mini-chart"><div className="chart-y"><span>80</span><span>60</span><span>40</span><span>20</span><span>0</span></div><div className="chart-bars">{points.map((point) => <div className="bar-col" key={point.hour}><div className="bar-track"><span style={{ height: `${(point.value / max) * 100}%` }}><i /></span></div><small>{point.hour.replace(':00', '')}</small></div>)}</div></div> }

function Members({ data, query, setQuery, openModal }) { const members = data.members.filter((member) => `${member.name} ${member.email} ${member.rfid_tag_uid}`.toLowerCase().includes(query.toLowerCase())); return <PageSection eyebrow="PEOPLE DIRECTORY" title="Members" description="Manage membership status and the access identity behind every tap." actions={<button className="button primary" onClick={() => openModal('member')}><Icons.UserPlus size={16} /> Add member</button>}><div className="toolbar"><div className="search-box"><Icons.Search size={16} /><input placeholder="Search name, email, RFID…" value={query} onChange={(event) => setQuery(event.target.value)} /></div><Filter label="All statuses" /><Filter label="All roles" /></div><div className="panel table-panel"><table><thead><tr><th>Member</th><th>Role</th><th>RFID tag</th><th>Status</th><th>Last activity</th><th /></tr></thead><tbody>{members.map((member) => <tr key={member.user_id}><td><div className="person-cell"><div className="avatar ink">{initials(member.name)}</div><div><strong>{member.name}</strong><small>{member.email}</small></div></div></td><td><span className="role-label">{member.role}</span></td><td><code>{member.rfid_tag_uid || 'Not assigned'}</code></td><td><span className={`status-chip ${member.membership_status === 'Active' ? 'good' : 'muted-chip'}`}>{member.membership_status}</span></td><td><span className="table-muted">Today · 08:42</span></td><td><button className="icon-button small"><Icons.MoreHorizontal size={16} /></button></td></tr>)}</tbody></table></div></PageSection> }
function Attendance({ rows }) { return <PageSection eyebrow="ACCESS CONTROL" title="Attendance logs" description="Every RFID event, resolved to a member and device, in one auditable stream." actions={<button className="button ghost" onClick={() => downloadCSV(rows, 'attendance-logs.csv')}><Icons.Download size={15} /> Export CSV</button>}><div className="toolbar"><div className="date-tab active">Today</div><div className="date-tab">Yesterday</div><div className="date-tab">This week</div><div className="toolbar-spacer" /><Filter label="All event types" /><Filter label="All devices" /></div><div className="panel table-panel"><table><thead><tr><th>Member</th><th>Event</th><th>Timestamp</th><th>Source</th><th>Device</th><th>Record ID</th></tr></thead><tbody>{rows.map((row) => <tr key={row.log_id}><td><div className="person-cell"><div className={`avatar ${row.log_type === 'IN' ? 'lime' : 'ink'}`}>{initials(row.name)}</div><strong>{row.name}</strong></div></td><td><span className={`event-type ${row.log_type === 'IN' ? 'in' : 'out'}`}>{row.log_type}</span></td><td>{formatDate(row.timestamp)} <span className="table-muted">{formatTime(row.timestamp)}</span></td><td><span className="source-label"><Icons.RadioTower size={13} /> {row.source}</span></td><td><code>{row.device_id}</code></td><td><code>LOG-{String(row.log_id).padStart(4, '0')}</code></td></tr>)}</tbody></table></div></PageSection> }
function Reservations({ rows, role, openModal, runAction }) { return <PageSection eyebrow="SCHEDULE CONTROL" title="Workout reservations" description="Keep every training block intentional, spaced, and visible." actions={role === 'Member' ? <button className="button primary" onClick={() => openModal('reservation')}><Icons.Plus size={16} /> Reserve a slot</button> : <button className="button ghost" onClick={() => openModal('reservation')}><Icons.CalendarPlus size={15} /> Add reservation</button>}><div className="schedule-strip"><div className="schedule-date active"><small>THU</small><strong>15</strong><span>Today</span></div><div className="schedule-date"><small>FRI</small><strong>16</strong><span>Tomorrow</span></div><div className="schedule-date"><small>SAT</small><strong>17</strong><span>Weekend</span></div><div className="schedule-date"><small>SUN</small><strong>18</strong><span>Weekend</span></div></div><div className="reservation-list">{rows.map((row) => <div className="reservation-card" key={row.reservation_id}><div className="reservation-time"><strong>{row.slot_time_start}</strong><span>{row.slot_time_end}</span></div><div className="reservation-line" /><div className="reservation-body"><div className="eyebrow">{row.status.toUpperCase()} · TRAINING BLOCK</div><h3>{row.name}</h3><p>Strength & conditioning floor · Main gym</p></div><span className={`status-chip ${row.status === 'Confirmed' ? 'good' : row.status === 'Pending' ? 'warning' : 'muted-chip'}`}>{row.status}</span><button className="icon-button small" onClick={() => runAction(() => api(`/api/reservations/${row.reservation_id}`, { method: 'PATCH', body: JSON.stringify({ status: 'Cancelled' }) }), 'Reservation cancelled')}><Icons.X size={15} /></button></div>)}</div></PageSection> }
function Maintenance({ rows, openModal, runAction }) { return <PageSection eyebrow="ASSET HEALTH" title="Equipment maintenance" description="Surface issues early and keep every station training-ready." actions={<button className="button primary" onClick={() => openModal('maintenance')}><Icons.Plus size={16} /> Report issue</button>}><div className="maintenance-summary"><SummaryPill label="Open issues" value={rows.filter((row) => row.maintenance_status !== 'Fixed').length} tone="coral" /><SummaryPill label="Under repair" value={rows.filter((row) => row.maintenance_status === 'Under Repair').length} tone="amber" /><SummaryPill label="Fixed this month" value="14" tone="lime" /></div><div className="maintenance-list">{rows.map((row) => <div className="maintenance-card" key={row.equipment_id}><div className={`equipment-icon ${row.maintenance_status === 'Fixed' ? 'lime' : 'coral'}`}><Icons.Dumbbell size={19} /></div><div className="maintenance-main"><div className="maintenance-title"><h3>{row.equipment_name}</h3><span className={`status-chip ${row.maintenance_status === 'Fixed' ? 'good' : row.maintenance_status === 'Under Repair' ? 'warning' : 'danger'}`}>{row.maintenance_status}</span></div><p>{row.issue_description}</p><small>Reported by {row.reporter} · {formatDate(row.date_reported)}</small></div><select value={row.maintenance_status} onChange={(event) => runAction(() => api(`/api/maintenance/${row.equipment_id}`, { method: 'PATCH', body: JSON.stringify({ maintenance_status: event.target.value }) }), 'Maintenance status updated')}><option>Damaged</option><option>Under Repair</option><option>Fixed</option></select></div>)}</div></PageSection> }
function Analytics({ dashboard }) { return <PageSection eyebrow="DECISION SUPPORT" title="Gym analytics" description="Turn the access stream into a clear operating rhythm." actions={<button className="button ghost" onClick={() => downloadCSV(dashboard?.weekly || [], 'weekly-attendance.csv')}><Icons.Download size={15} /> Export report</button>}><div className="analytics-grid"><div className="panel analytics-wide"><div className="panel-head"><div><div className="eyebrow">VISITS BY DAY</div><h2>Weekly attendance volume</h2></div><span className="chart-note">Oct 09 — Oct 15 <Icons.ChevronDown size={14} /></span></div><WeeklyChart points={dashboard?.weekly || []} /></div><div className="panel insight-panel"><div className="eyebrow">OPERATIONAL INSIGHT</div><div className="insight-number">17:00<span>–19:00</span></div><h3>Your peak window</h3><p>Evening traffic is 28% above the weekly average. Consider staffing one additional floor lead during this window.</p><button className="text-button">Create staffing note <Icons.ArrowUpRight size={14} /></button></div></div><div className="analytics-grid bottom"><div className="panel chart-panel"><div className="panel-head"><div><div className="eyebrow">HOURLY RHYTHM</div><h2>Peak hours</h2></div></div><MiniChart points={dashboard?.peakHours || []} /></div><div className="panel report-panel"><div className="eyebrow">MONTH TO DATE</div><div className="report-row"><strong>4,812</strong><span>total visits</span><b>+12.8%</b></div><div className="report-row"><strong>86%</strong><span>member retention</span><b>+3.2%</b></div><div className="report-row"><strong>92%</strong><span>RFID resolution rate</span><b>+0.8%</b></div></div></div></PageSection> }
function WeeklyChart({ points }) { const max = Math.max(...points.map((point) => point.value), 1); return <div className="weekly-chart">{points.map((point) => <div className="week-col" key={point.day}><div className="week-track"><span style={{ height: `${(point.value / max) * 100}%` }} /></div><strong>{point.value}</strong><small>{point.day}</small></div>)}</div> }
function RFID({ members, dashboard, openModal, notify }) { const [uid, setUid] = useState('M-10294'); const [response, setResponse] = useState(null); const tap = async () => { try { const result = await api('/api/rfid/tap', { method: 'POST', body: JSON.stringify({ rfidTagUid: uid, deviceId: 'ESP32-FRONT-01' }) }); setResponse(result); notify(`${result.user.name} marked ${result.event.log_type}`) } catch (error) { setResponse({ ok: false, message: error.message }); notify(error.message, 'error') } }; return <PageSection eyebrow="HARDWARE BRIDGE" title="RFID access control" description="One tap from the floor to a verified access event." actions={<button className="button primary" onClick={tap}><Icons.ScanLine size={16} /> Simulate tap</button>}><div className="rfid-grid"><div className="panel rfid-console"><div className="console-head"><div className="device-status"><span className="pulse" /> Connected</div><code>ESP32-FRONT-01</code></div><div className="reader-visual"><div className="reader-ring"><Icons.RadioTower size={42} /></div><span>RC522 READER</span><small>Ready for next tap</small></div><div className="tap-form"><label>RFID tag UID<select value={uid} onChange={(event) => setUid(event.target.value)}>{members.filter((member) => member.rfid_tag_uid).map((member) => <option key={member.user_id} value={member.rfid_tag_uid}>{member.rfid_tag_uid} · {member.name}</option>)}</select></label><button className="button primary full" onClick={tap}>Send test event <Icons.ArrowUpRight size={15} /></button></div>{response && <div className={`console-response ${response.ok ? 'success' : 'error'}`}><strong>{response.ok ? `✓ ${response.user.name} · ${response.event.log_type}` : `! ${response.message}`}</strong>{response.ok && <span>Capacity is now {response.capacity.inside} / {response.capacity.max}</span>}</div>}</div><div className="panel flow-panel"><div className="eyebrow">SYSTEM FLOW</div><h2>From tap to dashboard.</h2><div className="flow-list"><FlowStep number="01" icon={Icons.CreditCard} title="RFID Card / Keyfob" detail="Member identity" /><FlowStep number="02" icon={Icons.RadioTower} title="RC522 Reader" detail="UID capture" /><FlowStep number="03" icon={Icons.Wifi} title="ESP32 Wi-Fi Node" detail="HTTP POST event" /><FlowStep number="04" icon={Icons.Server} title="REST API" detail="Resolve + log IN/OUT" /><FlowStep number="05" icon={Icons.Database} title="SQL Database" detail="Persist attendance" /></div><div className="code-note"><code>POST /api/rfid/tap</code><span>200 OK · real-time capacity update</span></div></div></div></PageSection> }
function FlowStep({ number, icon: Icon, title, detail }) { return <div className="flow-step"><span>{number}</span><div className="flow-icon"><Icon size={16} /></div><div><strong>{title}</strong><small>{detail}</small></div><Icons.ChevronRight size={14} /></div> }
function PageSection({ eyebrow, title, description, actions, children }) { return <><section className="page-heading"><div><div className="eyebrow">{eyebrow}</div><h1>{title}</h1><p>{description}</p></div><div className="heading-actions">{actions}</div></section>{children}</> }
function Filter({ label }) { return <button className="filter-button">{label}<Icons.ChevronDown size={14} /></button> }
function SummaryPill({ label, value, tone }) { return <div className={`summary-pill ${tone}`}><span>{label}</span><strong>{value}</strong></div> }
function ModalShell({ title, eyebrow, onClose, children }) { return <div className="modal-backdrop" onMouseDown={onClose}><div className="modal" onMouseDown={(event) => event.stopPropagation()}><div className="modal-head"><div><div className="eyebrow">{eyebrow}</div><h2>{title}</h2></div><button className="icon-button" onClick={onClose}><Icons.X size={18} /></button></div>{children}</div></div> }
function MemberModal({ onClose, runAction }) { const [form, setForm] = useState({ name: '', email: '', rfid_tag_uid: '' }); const change = (key) => (event) => setForm({ ...form, [key]: event.target.value }); return <ModalShell eyebrow="PEOPLE DIRECTORY" title="Add a member" onClose={onClose}><div className="form-grid"><label>Full name<input value={form.name} onChange={change('name')} placeholder="e.g. Alex Rivera" /></label><label>Email address<input value={form.email} onChange={change('email')} placeholder="alex@email.com" type="email" /></label><label>RFID tag UID<input value={form.rfid_tag_uid} onChange={change('rfid_tag_uid')} placeholder="M-11042" /></label><label>Membership status<select><option>Active</option><option>Inactive</option></select></label></div><div className="modal-actions"><button className="button ghost" onClick={onClose}>Cancel</button><button className="button primary" disabled={!form.name || !form.email} onClick={() => runAction(() => api('/api/members', { method: 'POST', body: JSON.stringify(form) }), 'Member added to the directory')}><Icons.UserPlus size={15} /> Add member</button></div></ModalShell> }
function MaintenanceModal({ onClose, runAction }) { const [form, setForm] = useState({ equipment_name: '', issue_description: '' }); const change = (key) => (event) => setForm({ ...form, [key]: event.target.value }); return <ModalShell eyebrow="ASSET HEALTH" title="Report equipment issue" onClose={onClose}><div className="form-grid"><label>Equipment name<input value={form.equipment_name} onChange={change('equipment_name')} placeholder="e.g. Bench Press 03" /></label><label className="span-two">Issue description<textarea value={form.issue_description} onChange={change('issue_description')} placeholder="Describe what needs attention…" rows="4" /></label></div><div className="modal-actions"><button className="button ghost" onClick={onClose}>Cancel</button><button className="button primary" disabled={!form.equipment_name || !form.issue_description} onClick={() => runAction(() => api('/api/maintenance', { method: 'POST', body: JSON.stringify(form) }), 'Maintenance issue logged')}><Icons.Wrench size={15} /> Log issue</button></div></ModalShell> }
function SettingsModal({ onClose, notify }) { return <ModalShell eyebrow="SYSTEM CONFIGURATION" title="System settings" onClose={onClose}><div className="settings-list"><label className="setting-row"><span><strong>Live capacity refresh</strong><small>Refresh dashboard data after each RFID event.</small></span><input type="checkbox" defaultChecked /></label><label className="setting-row"><span><strong>Maintenance alerts</strong><small>Show open equipment issues in the notification center.</small></span><input type="checkbox" defaultChecked /></label><label className="setting-row"><span><strong>Compact tables</strong><small>Use denser rows for operations teams.</small></span><input type="checkbox" /></label></div><div className="modal-actions"><button className="button ghost" onClick={onClose}>Close</button><button className="button primary" onClick={() => { notify('Settings saved'); onClose() }}><Icons.Check size={15} /> Save settings</button></div></ModalShell> }
function ReservationModal({ onClose, runAction }) { const [form, setForm] = useState({ user_id: 3, slot_date: '2026-10-16', slot_time_start: '07:00', slot_time_end: '08:00' }); const change = (key) => (event) => setForm({ ...form, [key]: event.target.value }); return <ModalShell eyebrow="SCHEDULE CONTROL" title="Reserve a workout slot" onClose={onClose}><div className="form-grid"><label>Member<select value={form.user_id} onChange={change('user_id')}><option value="3">Paolo Reyes</option><option value="4">Camille Uy</option><option value="5">Nico Mendoza</option></select></label><label>Date<input type="date" value={form.slot_date} onChange={change('slot_date')} /></label><label>Starts<input type="time" value={form.slot_time_start} onChange={change('slot_time_start')} /></label><label>Ends<input type="time" value={form.slot_time_end} onChange={change('slot_time_end')} /></label></div><div className="modal-actions"><button className="button ghost" onClick={onClose}>Cancel</button><button className="button primary" onClick={() => runAction(() => api('/api/reservations', { method: 'POST', body: JSON.stringify(form) }), 'Workout slot reserved')}><Icons.CalendarPlus size={15} /> Reserve slot</button></div></ModalShell> }

export default App
