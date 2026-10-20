# SmartGym Operations System — Outcomes

- [ ] **Role-aware access and navigation**
  - Authentication entry point is visible for Gym Owner/Administrator, Gym Staff, and Gym Member.
  - The navigation exposes Admin Dashboard and Gym Analytics to Admin, member/attendance/maintenance workflows to Admin and Staff, and reservations/member dashboard to Members.
  - All actors can see real-time gym capacity.

- [ ] **Operations dashboard and live capacity**
  - Dashboard shows capacity, occupancy percentage, active members, check-ins, upcoming reservations, and maintenance alerts.
  - Capacity and recent activity are refreshed from the REST API and have clear visual status.

- [ ] **Member management and RFID identity**
  - Admin and Staff can add/update a member with name, email, role, RFID tag UID, and Active/Inactive membership status.
  - Member records can be searched and filtered.

- [ ] **Attendance/access logs and ESP32 endpoint**
  - Attendance logs show user, timestamp, IN/OUT type, and source/device.
  - `POST /api/rfid/tap` accepts an RFID UID from the ESP32/RC522 flow, resolves the user, creates the next IN/OUT event, and returns updated capacity.

- [ ] **Workout slot reservations**
  - Members can view available workout slots, reserve a slot, and cancel a pending/confirmed reservation.
  - Reservations preserve slot date/time and Pending/Confirmed/Cancelled status.

- [ ] **Equipment maintenance workflow**
  - Admin and Staff can report damaged equipment with description.
  - Maintenance records display Damaged, Under Repair, and Fixed transitions with reporter and dates.

- [ ] **Analytics and system documentation**
  - Admin sees peak-hour and weekly attendance visualizations.
  - SQL schema, seed records, REST route map, and RFID architecture notes are included in project docs.

- [ ] **Production-ready project packaging**
  - `npm run dev`, `npm run build`, `npm run start`, and `npm run db:seed` are documented and usable.
  - `/manus-routes.json` is served and lists all UI routes.
  - Health endpoint responds without authentication and Dockerfile supports deployment.
