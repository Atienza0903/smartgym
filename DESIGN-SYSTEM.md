# SmartGym Figma-style Design System

Ang current UI ay nirecreate mula sa ipinadalang dark operations-dashboard reference. Walang direct Figma connector sa project session, kaya ang design ay naka-implement bilang reusable CSS tokens at components na maaaring ilipat sa Figma Variables.

## Visual direction

**Athletic command center:** dark graphite workspace, electric lime live state, monospaced operational data, offset content canvas, thin technical borders, at restrained motion.

## Figma Variables

| Collection | Variable | Value | Use |
| --- | --- | --- | --- |
| Color | `color/bg/base` | `#0D1110` | Main background |
| Color | `color/bg/panel` | `#171C1A` | Cards and panels |
| Color | `color/bg/elevated` | `#202921` | Popovers and forms |
| Color | `color/text/primary` | `#E9EEE8` | Main text |
| Color | `color/text/muted` | `#7C8A80` | Supporting copy |
| Color | `color/brand/lime` | `#C8F169` | Brand and live state |
| Color | `color/status/coral` | `#FF7D6C` | Alerts and damaged equipment |
| Color | `color/status/amber` | `#F5BF63` | Pending and attention |
| Color | `color/status/blue` | `#8AC2FF` | Informational state |
| Effect | `effect/grid` | 42px | Background grid size |
| Radius | `radius/sm` | 6px | Buttons and inputs |
| Radius | `radius/md` | 8px | Panels |
| Space | `space/4` | 16px | Standard gap |
| Space | `space/6` | 24px | Panel padding |

## Background implementation

The background is layered in `src/styles.css`:

1. Graphite-to-forest gradient for depth.
2. Soft green radial light at the upper-right to echo the live capacity accent.
3. Low-opacity 42px technical grid.
4. Fine noise texture using CSS radial dots.
5. A mask that fades the grid lower on the page so the content stays readable.

To change the visual direction, modify the CSS variables first instead of changing individual components. This keeps cards, charts, buttons, and status states visually consistent.

## Component rules

- Primary action uses lime fill with dark text.
- Secondary action uses elevated graphite fill with a thin border.
- Success state uses lime on a dark green surface.
- Warning uses amber; danger uses coral.
- Data IDs, device IDs, timestamps, and metrics use `DM Mono`.
- Headings use `Space Grotesk`; interface text uses `Inter`.
- Buttons should show a visible state change: toast, modal, downloaded file, API refresh, or navigation.

## Updated interactions

- Bell icon opens the notification popover.
- System settings opens a settings modal and saves the selected toggles locally for the current session.
- Attendance **Export CSV** downloads `attendance-logs.csv`.
- Analytics **Export report** downloads `weekly-attendance.csv`.
- Refresh data calls the dashboard REST endpoints again.
- Add member, Report issue, Reserve slot, and Simulate tap call their respective REST endpoints.
- Maintenance status select sends a PATCH request and refreshes the queue.
- Role selector changes the visible workspace and navigation rules.
