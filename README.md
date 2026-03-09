# FAQ – GLPI Plugin

A GLPI plugin that provides an enhanced FAQ interface powered by the GLPI Knowledge Base system. Browse and search knowledge base articles organized by categories with custom category icons. Appears as a top-level item in both Central (technician) and Helpdesk (self-service) interfaces.

---

## Features

- ✅ **Clean FAQ interface** — dedicated page displaying knowledge base categories and articles
- ✅ **Category icons/images** — upload custom icons for root-level KB categories (JPG, PNG, GIF, WebP, SVG supported)
- ✅ **Customizable hero section** — set a custom title and subtitle for the FAQ home page
- ✅ **Full-text search** — search knowledge base articles across all categories
- ✅ **Multi-interface support** — works in both Central (technician) and Helpdesk (self-service) interfaces
- ✅ **Hierarchical categories** — browse root categories and subcategories
- ✅ **Visibility targeting** — respects GLPI KB article visibility targets (entity, profile, group, user)
- ✅ **GLPI integration** — uses native GLPI Knowledge Base system (no separate DB table)
- ✅ **i18n** — English and French translations included
- ✅ **No external dependencies** — pure GLPI APIs

---

## Requirements

- GLPI **11.0.0** or later
- Knowledge Base categories and articles already set up in GLPI

---

## Installation

1. Copy the `betterfaq/` folder into your GLPI `plugins/` directory
2. Log in as GLPI superadmin
3. Go to **Setup → Plugins**
4. Find **"FAQ"** and click **Install**, then **Enable**

---

## Configuration

Navigate to **Setup → FAQ** (or **Administration → Setup → FAQ**).

### Hero Section

Set a custom title and subtitle displayed at the top of the FAQ home page:
- **Hero Title** — Main heading (e.g., "Foire Aux Questions")
- **Hero Subtitle** — Subheading (e.g., "Comment pouvons-nous vous aider ?")

### Category Icons

Upload images for each root-level knowledge base category:

| Field | Description |
|---|---|
| **Category** | Root-level KB category name |
| **Current Image** | Preview of the uploaded icon; click "Remove" to delete |
| **Upload New Image** | Select a JPG, PNG, GIF, WebP, or SVG file |
| **Sort Order** | Numeric sort order for display (lower = higher priority) |

Click **Save** to apply changes.

---

## Usage

### For End Users

**Central (Technician) Interface:**
1. Go to **Tools → FAQ** in GLPI
2. Browse categories by clicking on category cards
3. View articles within each category
4. Use the search box to find articles by keyword

**Helpdesk (Self-Service) Interface:**
1. Click **FAQ** in the top navigation bar
2. Browse and search the same way as the central interface

### For Admins

1. **Set up Knowledge Base**: Create categories and articles in **Tools → Knowledge Base**
2. **Configure FAQ**: Go to **Setup → FAQ** and upload icons for each category
3. **Manage permissions**: Grant access via **Administration → Profiles → FAQ**

---

## Permissions

The plugin registers two rights:

| Right | Effect |
|---|---|
| `plugin_betterfaq_config` | Grants access to the category icon configuration page |
| `plugin_betterfaq_faq` | Grants access to browse the FAQ (read-only) |

| Role | Default Access |
|---|---|
| **Super-Admin** | Full access to config and FAQ browsing |
| **Technicians** | FAQ browsing allowed |
| **Self-Service / Helpdesk** | FAQ browsing allowed |

Permissions are managed in **Administration → Profiles**.

---

## KB Article Visibility Targeting

The plugin respects GLPI's native Knowledge Base visibility targeting system. Articles are only shown to users whose profiles, groups, entities, or user ID match the article's visibility targets.

### How It Works

**Articles with visibility targets:**
- Only visible to users matching at least one target (entity, profile, group, or user)
- Admins in the root entity (0) see all entity-targeted articles

**Articles without targets:**
- Treated as unpublished/draft — hidden from all users

**Super-Admins:**
- See all FAQ articles regardless of targets (bypass via `config` right)

### Setting Up Targets in GLPI

In **Tools → Knowledge Base**, select an article and set visibility targets:

| Target Type | Setting | Effect |
|---|---|---|
| **Entity** | `glpi_entities_knowbaseitems` table | Visible to users in that entity |
| **Profile** | `glpi_knowbaseitems_profiles` table | Visible to users with that profile |
| **Group** | `glpi_groups_knowbaseitems` table | Visible to group members |
| **User** | `glpi_knowbaseitems_users` table | Visible to specific users |

---

## Database

The plugin stores category configuration in a single table:

| Table | Purpose |
|---|---|
| `glpi_plugin_betterfaq_config` | Category icons, titles, sort orders |

Knowledge base data (categories and articles) is stored in standard GLPI KB tables:
- `glpi_knowbaseitemcategories` — KB categories
- `glpi_knowbaseitems` — KB articles

---

## Project Structure

```
betterfaq/
├── setup.php                    # Plugin metadata, hook registration
├── hook.php                     # Install / uninstall, DB migration
├── inc/
│   ├── config.class.php         # Configuration admin UI
│   ├── faq.class.php            # Menu registration
│   ├── category.class.php       # Category loading and caching
│   ├── profile.class.php        # Permission management
│   └── validation.php           # Input validation
├── front/
│   ├── index.php                # FAQ home page
│   ├── category.php             # Category articles page
│   ├── search.php               # Search results page
│   └── config.form.php          # Admin configuration form
├── ajax/
│   ├── get_image.php            # Category icon endpoint (cached)
│   └── search.php               # Search autocomplete endpoint
├── uploads/
│   └── categories/              # Uploaded category images
└── locales/
    ├── en_GB.po / en_GB.mo
    └── fr_FR.po / fr_FR.mo
```

---

## Localization

Translation files are in `locales/`. After editing a `.po` file, recompile:

```bash
msgfmt locales/en_GB.po -o locales/en_GB.mo
msgfmt locales/fr_FR.po -o locales/fr_FR.mo
```

---

## Image Upload Security

- **File type validation** — only image MIME types (JPG, PNG, GIF, WebP, SVG) accepted via `finfo`
- **Extension whitelist** — validated against allowed list
- **Path traversal protection** — `realpath()` check to prevent directory escape
- **is_uploaded_file() check** — ensures file came from POST upload
- **Secure serving** — images served via `ajax/get_image.php` with cache headers and MIME type validation

---

## Uninstall

Uninstalling the plugin:
- Removes profile rights
- **Preserves** all configuration data in the database
- Reinstalling restores full functionality with existing settings intact

---

## Troubleshooting

### "FAQ" menu doesn't appear
- Verify the user has `plugin_betterfaq_faq` READ permission or is in Helpdesk interface
- Check plugin is enabled: **Setup → Plugins → FAQ → Enable**

### Category images not showing
- Verify images are uploaded: **Setup → Better FAQ**
- Check file formats are supported (JPG, PNG, GIF, WebP, SVG)
- Ensure `betterfaq/uploads/categories/` directory exists and is writable

### Search not working
- Verify Knowledge Base articles exist in GLPI
- Check KB articles are set to "Visible" (not hidden)
- Try searching with simpler keywords

