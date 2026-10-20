# SmartGym Operations System — Implementation Plan

## Scope and approach

Bubuo ng responsive operations dashboard para sa Gym Owner/Administrator, Gym Staff, at Gym Member. Ang app ay gagamit ng React + Vite + Tailwind-style utility CSS sa frontend, Node.js + Express REST API sa backend, at MySQL-compatible managed database sa production. May demo seed data at local in-memory fallback para agad makita ang workflows kapag walang `DATABASE_URL`.

### Core modules
- Role-aware dashboard navigation para sa Admin, Staff, at Member.
- Overview cards: real-time capacity, active members, today's check-ins, reservations, at maintenance alerts.
- Attendance/access logs with IN/OUT filtering.
- Member management with add/update dialog and status/RFID fields.
- Reservation schedule with create/cancel workflow.
- Equipment maintenance reporting and status transitions.
- Analytics view with peak-hour chart and weekly attendance bars.
- RFID/ESP32 endpoint documentation and request simulator panel.
- SQL schema, migration/seed script, REST route map, at architecture documentation.

## Project structure

- `src/`: React app, pages, components, demo state, and styling.
- `server/index.js`: Express API, health endpoint, REST routes, and dev fallback data.
- `server/schema.sql`: MySQL-compatible tables, indexes, foreign keys, and seed records.
- `public/manus-routes.json`: complete page route manifest.
- `plan.md`, `TODO.md`, `README.md`: build decisions, outcome tracking, and setup/API docs.
- `Dockerfile`: production container for the combined app.

## Design direction

### Candidate styles
1. **Athletic command center** — dark graphite, electric lime accents, dense operational panels and live status chips. Probability 0.06.
2. **Soft wellness studio** — warm neutrals, rounded cards and calming gradients. Probability 0.03.
3. **Retro terminal gym** — black canvas, amber monospace type and technical readouts. Probability 0.08.

### Chosen style: Athletic command center

- **Design Movement:** Modern sports-performance control room with editorial dashboard composition.
- **Core Principles:** Fast scanning, confident contrast, progressive disclosure, and clear operational status.
- **Color Philosophy:** Graphite and ink create focus; electric lime marks live/healthy states; amber and coral are reserved for attention and maintenance.
- **Layout Paradigm:** Persistent left rail with an offset content canvas, oversized metric strip, and a split “operations now / needs attention” composition instead of a centered card grid.
- **Signature Elements:** Lime pulse indicator for live capacity, monospaced metric numerals, and slanted micro-labels/section markers.
- **Interaction Philosophy:** Every action should show a clear state transition; filters are immediate, destructive actions are confirmable, and role changes update the navigation context.
- **Animation:** 150–220ms slide/fade transitions, subtle capacity pulse, no decorative looping motion.
- **Typography System:** Inter for UI/body, Space Grotesk for display headings, and JetBrains Mono for IDs, RFID values, timestamps, and metrics.
- **Brand Essence:** “The operational pulse of a smarter gym” for owners and teams who need decisions at a glance; precise, energetic, trustworthy.
- **Brand Voice:** Direct, calm, action-oriented. Example lines: “Capacity is healthy. Keep the floor moving.” and “One tap. One verified access event.”
- **Wordmark & Logo:** Compact “SG” monogram inside a clipped hexagonal signal frame, paired with a high-contrast SmartGym wordmark.
- **Signature Brand Color:** Electric lime `#c8f169`.

## Data and API

The database schema models `users`, `reservations`, `attendance_logs`, and `equipment_maintenance` with one-to-many relationships. The API uses `/api/dashboard`, `/api/members`, `/api/attendance`, `/api/reservations`, `/api/maintenance`, and `/api/rfid/tap`. The RFID endpoint accepts `{ rfidTagUid, deviceId, timestamp }`, resolves the user, toggles IN/OUT based on the latest log, and returns capacity-aware status.

## Build workflow

- Install dependencies with the pinned pnpm package manager.
- Run Vite/Express together through `npm run dev`.
- Use `npm run db:seed` when `DATABASE_URL` is configured.
- Run `npm run build` for production frontend output.
- Run `npm run start` to serve the built app and API on `PORT` (default 3000).
- Validate route manifest, health endpoint, build output, and API behavior before checkpointing.
