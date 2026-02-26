# WPMU WhatsApp

> Fixed WhatsApp chat button for WordPress — schedule-aware, visibility controls per post type and page, pre-filled message. Zero third-party dependencies, zero theme conflicts.

---

## Features

### Button
- **Fixed position** — floats in the bottom-right or bottom-left corner of the screen at `z-index: 99999`; configurable from the admin
- **WhatsApp green** — official `#25d366` background with white SVG icon matching the WhatsApp brand
- **Pulse indicator** — two-element ping animation (static dot + expanding ring that fades out); respects `prefers-reduced-motion`
- **Optional label** — text shown next to the icon; leave empty for a compact icon-only button
- **Pre-filled message** — clicking the button opens WhatsApp with a customisable message already typed in
- **No phone = no button** — the button is never rendered unless a valid phone number is saved

### Schedule
- **Active days** — choose any combination of Monday–Sunday; leave all unchecked to show the button every day (schedule disabled)
- **Active hours** — set a From / To time window; uses the WordPress site timezone
- Outside the configured days or hours the button is not rendered — no hidden markup left behind

### Visibility controls
- **Special pages** — independently hide on: Front page / Homepage, Blog index, Archive pages (categories, tags, dates, authors), Search results, 404 page
- **Post types** — hide on any singular page of a registered post type; automatically detects all custom post types registered via code, ACF, CPT UI, Pods, or any other method
- **Specific pages** — multi-select list of all published pages; hide the button on individual pages regardless of post type settings

### Admin interface
- **Tabbed settings page** — four URL-based tabs (General / Messages / Schedule / Visibility); standard WordPress nav-tab pattern, no JavaScript required for navigation
- **Always-visible toggle** — the Enable / Disable switch and an Active / Inactive badge are shown above the tabs so you never have to hunt for the on/off control

### Internationalisation
- All hardcoded strings use standard WordPress i18n functions (`__()`, `esc_html_e()`, …) with the `wpmu-whatsapp` text domain — translatable via Loco Translate, PoEdit, or any compatible tool

---

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 |
| Browser (visitors) | Any modern browser |

No external libraries, no Composer dependencies, no npm build step.

---

## Installation

1. Upload the `wpmu-whatsapp` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins → Installed Plugins**
3. Go to **Settings → WhatsApp Button** to configure

---

## Settings

### General tab
| Field | Description |
|---|---|
| **Phone Number** | Full international number, digits only — e.g. `420123456789`. Required: the button will not appear without a valid number. |
| **Button Position** | Bottom right (default) or bottom left. |
| **Button Label** | Optional text shown next to the icon. Leave empty for a compact icon-only button. |

### Messages tab
| Field | Description |
|---|---|
| **Default Message** | Pre-filled text that opens in WhatsApp when the visitor clicks the button. Leave empty to open WhatsApp with no pre-filled message. |

### Schedule tab
| Field | Description |
|---|---|
| **Active Days** | Checkboxes for Monday–Sunday. Only checked days will show the button. Leave all unchecked to disable the schedule entirely. |
| **Active Hours** | From / To time range. Uses the WordPress site timezone shown below the field. |

### Visibility tab
| Field | Description |
|---|---|
| **Hide on Special Pages** | Front page, Blog index, Archives, Search results, 404 — each controlled independently. |
| **Hide on Post Types** | Hides the button on singular pages of the selected post type. Lists all post types with an admin UI, including custom ones. |
| **Hide on Specific Posts / Pages** | Multi-select of all published pages. Hold Ctrl (Windows) or Cmd (Mac) to select multiple. |

---

## How the schedule works

1. On every front-end request the plugin reads the current date and time using the **WordPress site timezone** (`wp_timezone_string()`)
2. If **Active Days** are configured, the current day of the week (1 = Monday … 7 = Sunday) must be in the checked list
3. If the day matches, the current time (`H:i`) must fall within the **Active Hours** window
4. If either check fails the button is not rendered at all
5. Leave **Active Days** completely unchecked to skip scheduling and always show the button

---

## How visibility rules are evaluated

Rules are checked in order; the first match hides the button:

1. **Special pages** — `is_front_page()`, `is_home()`, `is_archive()`, `is_search()`, `is_404()`
2. **Post type** — applies only on singular pages (`is_singular()`); compares `get_post_type()` against the exclusion list
3. **Specific page ID** — applies only on singular pages; compares `get_the_ID()` against the exclusion list

If none of the rules match, the button is shown.

---

## File structure

```
wpmu-whatsapp/
├── wpmu-whatsapp.php                      # Plugin bootstrap, activation hook
├── includes/
│   ├── class-wpmu-whatsapp-admin.php      # Settings page, Settings API
│   └── class-wpmu-whatsapp-frontend.php   # wp_footer hook, schedule & visibility checks, button render
└── languages/                             # Translation files (.po / .mo)
```

---

## Technical notes

- The button is injected via `wp_footer` — it fires after all theme output and is therefore immune to theme conflicts or PHP errors in templates
- All CSS is either inline on the element or inside a `<style>` block output alongside the button HTML; no `wp_enqueue_style()` call is made, so no extra HTTP request is added
- Every frontend class uses the `.wpmu-wa-*` prefix, making it conflict-free with themes and CSS frameworks (Bootstrap, Tailwind, Bulma, etc.)
- The ping animation uses two stacked `<span>` elements: one static (always-visible dot) and one animated (ring that scales up and fades to opacity 0) — matching the Tailwind `animate-ping` visual behaviour
- Phone numbers are sanitised by stripping everything except digits; the number is passed to `https://wa.me/{phone}` and the message is `rawurlencode()`d

---

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) © [Martin Ulč](https://martinulc.cz)
