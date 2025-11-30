# GuideOS Advent Calendar Block

Custom Gutenberg block that renders a 24-door Advent calendar for guideos.de with animated doors, modal surprises, and date-gated unlocking.

## Features

- Editable headline/subline, colors, and per-door content directly within the block.
- Supported door types: image reveal (with full-resolution download), download button (door 24 defaults to the GuideOS 1.0 ISO), external link, or embedded YouTube video.
- MP3 audio support: Download links ending in .mp3 automatically embed an audio player.
- Server-side date guard: outside the 1.–24. December window doors remain locked.
- LocalStorage tracking remembers which doors a visitor already opened.
- Responsive grid, rich animations, and modal overlay tuned to the GuideOS look & feel.

## Usage

1. Activate the plugin from the WordPress admin (`GuideOS Advent Calendar`).
2. Add the “GuideOS Advent Calendar” block anywhere inside the Site Editor or post editor.
3. Configure the 24 doors in the sidebar panel. Each door offers:
   - Title and description
   - Content type specific options (media picker, download/link label, YouTube URL)
4. Publish the page. Visitors can open one door per day during Advent; previously opened doors stay unlocked via localStorage.

## Development

The block assets are bundled with `@wordpress/scripts`. Always run build tools through DDEV as requested:

```bash
cd /home/srueegger/Sites/guideos-wordpress-theme

ddev npm install --prefix wp-content/plugins/guideos-advent-calendar

ddev npm run build --prefix wp-content/plugins/guideos-advent-calendar
```

During development you can watch for changes via:

```bash
cd /home/srueegger/Sites/guideos-wordpress-theme

ddev npm run start --prefix wp-content/plugins/guideos-advent-calendar
```

The repository ignores `build/` and `node_modules/`, so remember to run `npm install` after checking out the project.

## Manual Test Checklist

- [ ] Doors after the current date remain locked (verify server response message).
- [ ] Opening a door stores the state in localStorage; reloading the page keeps it open.
- [ ] Each content type (image, download, link, video) renders the proper modal layout.
- [ ] Image doors show the full-resolution download button below the image.
- [ ] MP3 download links automatically embed an audio player.
- [ ] Door 24 shows the ISO download button by default if no custom URL is set.
- [ ] Mobile view (≤640px) still displays a readable grid and modal.
