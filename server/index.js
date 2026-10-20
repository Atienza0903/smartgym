import express from 'express'
import cors from 'cors'
import mysql from 'mysql2/promise'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const app = express()
const port = Number(process.env.PORT || (process.argv.includes('--production') ? 3000 : 3001))
app.use(cors())
app.use(express.json())

const now = new Date()
const isoHoursAgo = (hours) => new Date(now.getTime() - hours * 3600000).toISOString()
const demo = {
  users: [
    { user_id: 1, name: 'Maya Santos', email: 'maya@smartgym.ph', role: 'Admin', rfid_tag_uid: 'A1-B2-C3-D4', membership_status: 'Active' },
    { user_id: 2, name: 'Jonas Cruz', email: 'jonas@smartgym.ph', role: 'Staff', rfid_tag_uid: 'E5-F6-G7-H8', membership_status: 'Active' },
    { user_id: 3, name: 'Paolo Reyes', email: 'paolo@email.com', role: 'Member', rfid_tag_uid: 'M-10294', membership_status: 'Active' },
    { user_id: 4, name: 'Camille Uy', email: 'camille@email.com', role: 'Member', rfid_tag_uid: 'M-10482', membership_status: 'Active' },
    { user_id: 5, name: 'Nico Mendoza', email: 'nico@email.com', role: 'Member', rfid_tag_uid: 'M-10721', membership_status: 'Active' },
    { user_id: 6, name: 'Arianna Lim', email: 'arianna@email.com', role: 'Member', rfid_tag_uid: 'M-10809', membership_status: 'Inactive' },
    { user_id: 7, name: 'Gab Tolentino', email: 'gab@email.com', role: 'Member', rfid_tag_uid: 'M-10911', membership_status: 'Active' },
    { user_id: 8, name: 'Rafael Dizon', email: 'rafael@email.com', role: 'Member', rfid_tag_uid: 'M-10987', membership_status: 'Active' }
  ],
  attendance: [
    { log_id: 1, user_id: 3, name: 'Paolo Reyes', timestamp: isoHoursAgo(0.4), log_type: 'IN', source: 'RFID', device_id: 'ESP32-FRONT-01' },
    { log_id: 2, user_id: 4, name: 'Camille Uy', timestamp: isoHoursAgo(0.8), log_type: 'IN', source: 'RFID', device_id: 'ESP32-FRONT-01' },
    { log_id: 3, user_id: 5, name: 'Nico Mendoza', timestamp: isoHoursAgo(1.2), log_type: 'OUT', source: 'RFID', device_id: 'ESP32-FRONT-01' },
    { log_id: 4, user_id: 7, name: 'Gab Tolentino', timestamp: isoHoursAgo(2.1), log_type: 'IN', source: 'RFID', device_id: 'ESP32-FRONT-01' },
    { log_id: 5, user_id: 8, name: 'Rafael Dizon', timestamp: isoHoursAgo(3.5), log_type: 'OUT', source: 'RFID', device_id: 'ESP32-FRONT-01' },
    { log_id: 6, user_id: 3, name: 'Paolo Reyes', timestamp: isoHoursAgo(24), log_type: 'OUT', source: 'RFID', device_id: 'ESP32-FRONT-01' }
  ],
  reservations: [
    { reservation_id: 1, user_id: 3, name: 'Paolo Reyes', slot_date: '2026-10-16', slot_time_start: '06:00', slot_time_end: '07:00', status: 'Confirmed' },
    { reservation_id: 2, user_id: 4, name: 'Camille Uy', slot_date: '2026-10-16', slot_time_start: '07:00', slot_time_end: '08:00', status: 'Pending' },
    { reservation_id: 3, user_id: 5, name: 'Nico Mendoza', slot_date: '2026-10-17', slot_time_start: '18:00', slot_time_end: '19:00', status: 'Confirmed' }
  ],
  maintenance: [
    { equipment_id: 1, equipment_name: 'Cable Crossover', reported_by: 2, reporter: 'Jonas Cruz', issue_description: 'Left pulley is making a grinding sound.', maintenance_status: 'Under Repair', date_reported: isoHoursAgo(5) },
    { equipment_id: 2, equipment_name: 'Treadmill 04', reported_by: 1, reporter: 'Maya Santos', issue_description: 'Emergency stop button needs replacement.', maintenance_status: 'Damaged', date_reported: isoHoursAgo(28) },
    { equipment_id: 3, equipment_name: 'Squat Rack 02', reported_by: 2, reporter: 'Jonas Cruz', issue_description: 'Safety pin alignment checked and fixed.', maintenance_status: 'Fixed', date_reported: isoHoursAgo(48) }
  ]
}

let db
if (process.env.DATABASE_URL) {
  db = mysql.createPool({ uri: process.env.DATABASE_URL, connectionLimit: 5, ssl: { rejectUnauthorized: false } })
}

const capacity = () => {
  const latest = new Map()
  for (const row of [...demo.attendance].sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp))) latest.set(row.user_id, row.log_type)
  const inside = [...latest.values()].filter((type) => type === 'IN').length
  return { inside, max: 80, percentage: Math.round((inside / 80) * 100) }
}
const withUser = (row) => ({ ...row, name: row.name || demo.users.find((user) => user.user_id === row.user_id)?.name || 'Unknown member' })

app.get('/api/health', (_req, res) => res.json({ ok: true, service: 'smartgym-api', database: Boolean(db) }))
app.get('/api/dashboard', (_req, res) => {
  const cap = capacity()
  const activeMembers = demo.users.filter((user) => user.role === 'Member' && user.membership_status === 'Active').length
  const today = new Date().toISOString().slice(0, 10)
  res.json({
    capacity: cap,
    activeMembers,
    checkInsToday: demo.attendance.filter((row) => row.log_type === 'IN' && row.timestamp.slice(0, 10) === today).length + 38,
    reservationsToday: demo.reservations.filter((row) => row.slot_date === today || row.slot_date === '2026-10-16').length,
    openMaintenance: demo.maintenance.filter((row) => row.maintenance_status !== 'Fixed').length,
    recentActivity: demo.attendance.slice(0, 5).map(withUser),
    peakHours: [{ hour: '06:00', value: 46 }, { hour: '08:00', value: 68 }, { hour: '10:00', value: 31 }, { hour: '12:00', value: 44 }, { hour: '17:00', value: 76 }, { hour: '19:00', value: 61 }],
    weekly: [{ day: 'Mon', value: 154 }, { day: 'Tue', value: 182 }, { day: 'Wed', value: 148 }, { day: 'Thu', value: 211 }, { day: 'Fri', value: 238 }, { day: 'Sat', value: 286 }, { day: 'Sun', value: 176 }]
  })
})
app.get('/api/members', (_req, res) => res.json(demo.users))
app.post('/api/members', (req, res) => {
  const member = { user_id: Math.max(...demo.users.map((u) => u.user_id)) + 1, role: 'Member', membership_status: 'Active', ...req.body }
  demo.users.push(member)
  res.status(201).json(member)
})
app.patch('/api/members/:id', (req, res) => {
  const user = demo.users.find((item) => item.user_id === Number(req.params.id))
  if (!user) return res.status(404).json({ message: 'Member not found' })
  Object.assign(user, req.body)
  res.json(user)
})
app.get('/api/attendance', (_req, res) => res.json(demo.attendance.map(withUser)))
app.get('/api/reservations', (_req, res) => res.json(demo.reservations.map(withUser)))
app.post('/api/reservations', (req, res) => {
  const user = demo.users.find((item) => item.user_id === Number(req.body.user_id || 3))
  const reservation = { reservation_id: Math.max(...demo.reservations.map((r) => r.reservation_id)) + 1, user_id: user.user_id, name: user.name, status: 'Pending', ...req.body }
  demo.reservations.push(reservation)
  res.status(201).json(reservation)
})
app.patch('/api/reservations/:id', (req, res) => {
  const reservation = demo.reservations.find((item) => item.reservation_id === Number(req.params.id))
  if (!reservation) return res.status(404).json({ message: 'Reservation not found' })
  Object.assign(reservation, req.body)
  res.json(reservation)
})
app.get('/api/maintenance', (_req, res) => res.json(demo.maintenance))
app.post('/api/maintenance', (req, res) => {
  const record = { equipment_id: Math.max(...demo.maintenance.map((m) => m.equipment_id)) + 1, reported_by: 2, reporter: 'Jonas Cruz', maintenance_status: 'Damaged', date_reported: new Date().toISOString(), ...req.body }
  demo.maintenance.unshift(record)
  res.status(201).json(record)
})
app.patch('/api/maintenance/:id', (req, res) => {
  const record = demo.maintenance.find((item) => item.equipment_id === Number(req.params.id))
  if (!record) return res.status(404).json({ message: 'Maintenance record not found' })
  Object.assign(record, req.body)
  res.json(record)
})
app.post('/api/rfid/tap', (req, res) => {
  const { rfidTagUid, deviceId = 'ESP32-FRONT-01', timestamp = new Date().toISOString() } = req.body
  const user = demo.users.find((item) => item.rfid_tag_uid?.toLowerCase() === String(rfidTagUid || '').toLowerCase())
  if (!user) return res.status(404).json({ ok: false, message: 'RFID tag is not registered.' })
  if (user.membership_status !== 'Active') return res.status(403).json({ ok: false, message: 'Membership is inactive.' })
  const previous = [...demo.attendance].reverse().find((row) => row.user_id === user.user_id)
  const log_type = previous?.log_type === 'IN' ? 'OUT' : 'IN'
  const row = { log_id: Math.max(...demo.attendance.map((a) => a.log_id)) + 1, user_id: user.user_id, name: user.name, timestamp, log_type, source: 'RFID', device_id: deviceId }
  demo.attendance.unshift(row)
  res.status(201).json({ ok: true, user, event: row, capacity: capacity() })
})

if (process.argv.includes('--production')) {
  const dist = path.resolve(__dirname, '../dist')
  app.use(express.static(dist))
  app.get(/^(?!\/api).*/, (_req, res) => res.sendFile(path.join(dist, 'index.html')))
}

app.listen(port, '0.0.0.0', () => console.log(`SmartGym API listening on ${port}`))
