# SmartGym Operations — Gleah's Fitness Gym

Web-based gym management system with **RFID access control (ESP32 + RC522)**, real-time capacity monitoring, workout slot reservations, attendance logs, analytics, and equipment maintenance.

**Stack:** HTML, CSS, JavaScript, Bootstrap 5 · PHP 8 (PDO) · MySQL / MariaDB · ESP32 + RFID RC522

## Modules

| Module | Users |
|---|---|
| Authentication & Security (role-based: admin / staff / member) | All |
| Member Management (CRUD, plans, renewals, RFID assignment, CSV export) | Admin, Staff |
| RFID Access Control (IN/OUT toggle, deny unknown/expired/full, duplicate-tap guard) | Members, Staff |
| Real-Time Gym Capacity (live gauge, public display screen `display.php`) | All |
| Workout Slot Reservation (book / cancel, capacity per slot, auto-attend on RFID entry) | Members |
| Attendance / Access Logs (filters, CSV export) | Admin, Staff |
| Gym Analytics (peak hours, busy days, heatmap, daily visitors, plans, utilization) | Admin |
| Equipment Maintenance (inventory, issue tickets, priority, cost, resolution) | Admin, Staff |
| Member Dashboard / Admin Dashboard | Members / Admin |
| RFID Live Monitor + web scan simulator (test without hardware) | Admin, Staff |

## Installation (XAMPP / WAMP / LAMP)

1. Copy the `smartgym` folder into your web root (e.g. `C:\xampp\htdocs\smartgym`).
2. Start **Apache** and **MySQL**.
3. Edit `config/config.php` if your MySQL user/password is not `root` / empty
   (or set env vars `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).
4. Open `http://localhost/smartgym/install.php`, tick **Generate demo data** (optional) and click **Install**.
   - CLI alternative: `php install.php --demo` (add `--force` to reinstall).
   - Manual alternative: import `database/smartgym.sql` in phpMyAdmin.
5. Log in at `http://localhost/smartgym/`. **Delete or rename `install.php` after installing.**

Quick run without XAMPP: `php -S 0.0.0.0:8080` inside the folder, then open `http://localhost:8080`.

### Default accounts (change after first login)

| Role | Email | Password |
|---|---|---|
| Admin | admin@smartgym.local | admin123 |
| Staff | staff@smartgym.local | staff123 |
| Member | member@smartgym.local | member123 (RFID `A1B2C3D4`) |

## RFID device API

```
POST /api/rfid.php
Header: X-API-KEY: <Device API key from Settings>
Body (JSON or form): {"uid":"A1B2C3D4","device_id":"ESP32-01"}
```

Response `action` is one of `IN`, `OUT`, `DENIED`, `IGNORED`, `ERROR`, plus `message`, `member`, `inside`, `capacity`.
Health check: `GET /api/rfid.php?ping=1` with the same header. Public aggregate capacity: `GET /api/capacity.php`.

Rules: registered card toggles IN/OUT · unknown card → DENIED (shown in Access Logs, can be registered to a member) · inactive/suspended/expired → DENIED · gym full → DENIED · repeated tap within cooldown → IGNORED · entering during a booked slot (up to 30 min early) marks the reservation **attended**.

## ESP32 + RC522 hardware

Firmware: `firmware/esp32_rfid/esp32_rfid.ino`

| RC522 | ESP32 |
|---|---|
| SDA (SS) | GPIO 5 |
| SCK | GPIO 18 |
| MOSI | GPIO 23 |
| MISO | GPIO 19 |
| RST | GPIO 4 |
| 3.3V | 3V3 (**not 5V**) |
| GND | GND |

Optional: green LED GPIO 25, red LED GPIO 26, buzzer GPIO 27, relay (door lock) GPIO 14, 16x2 I2C LCD on SDA 21 / SCL 22.

1. Arduino IDE → install the **ESP32** board package and libraries **MFRC522** and **ArduinoJson** (and **LiquidCrystal I2C** if using an LCD).
2. Edit `WIFI_SSID`, `WIFI_PASS`, `SERVER_URL` (use the PC's LAN IP, e.g. `http://192.168.1.10/smartgym/api/rfid.php`) and `API_KEY` (from **Settings**).
3. Select board **ESP32 Dev Module**, upload, open Serial Monitor at 115200 baud and tap a card.
4. Allow Apache through the Windows firewall so the ESP32 can reach the PC.
5. To register a new card: tap it once (it appears as an unregistered card), then pick it in **Members → Edit → RFID UID**.

No hardware yet? Use **RFID Monitor → Simulate scan**.

## Security

Passwords hashed with `password_hash`, PDO prepared statements, CSRF tokens on all forms, role guards on every page, session hardening, login throttling, and device API key for the ESP32 endpoint.

## Project structure

```
api/            capacity, rfid (device), simulate, recent_scans
assets/         css, js, img
config/         config.php
database/       smartgym.sql
firmware/       esp32_rfid/esp32_rfid.ino
includes/       bootstrap, helpers, auth, rfid logic, seed, layout
*.php           pages (dashboard, members, reservations, slots, equipment, analytics, ...)
```
