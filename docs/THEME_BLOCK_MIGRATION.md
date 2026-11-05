# Native homepage blocks (theme 1.1.0)

The homepage now displays the page content instead of assembling content from
Customizer options. Appearance controls retain design, branding, header/footer,
profile and map settings used by other templates. Homepage controls are removed
from both Customizer and Parish Appearance. Saved options are not deleted.
All five palettes remain selectable; the old automatic hero/sidebar/card layouts
are replaced by native block composition, not reproduced pixel for pixel.

New sites: choose a static homepage in Settings → Reading, edit that page and
insert the Homepage pattern or ordinary core blocks. Query Loop controls news;
the Parish plugin's existing schedule blocks control live services.

## Existing sites

Keep the old theme ZIP for rollback. Upload `scripts/migrate_home_blocks.php`
and the new theme ZIP into `wp-content/prihod-packages/`. Run through WP-CLI
while the current Georg-Kloster Prihod theme is active:

```bash
export PATH="/opt/plesk/php/8.4/bin:$PATH" && \
php /usr/local/bin/wp eval-file wp-content/prihod-packages/migrate_home_blocks.php
```

This creates one draft and leaves the live page untouched. Review that draft in
the standard page editor. Section order and visibility, saved headings, text,
images, links, cards, news query, contacts and original enabled page content are
copied into blocks. The existing map is retained as a standard Custom HTML block.
Live services use the existing `gkpsch/next-services` block when registered;
without it only the configured schedule link is copied. Card layout and image
placement can be adjusted with native Group/Columns controls. Disabled content
remains in the original options/page backup. Review multilingual pages in their
own language; this tool handles the selected static homepage only and does not
create WPML/Polylang translation relationships or convert other pages.

After reviewing the draft:

```bash
export PATH="/opt/plesk/php/8.4/bin:$PATH" && \
php /usr/local/bin/wp eval-file wp-content/prihod-packages/migrate_home_blocks.php apply && \
php /usr/local/bin/wp theme install wp-content/prihod-packages/georg-kloster-prihod-1.1.0.zip --force
```

Apply copies the reviewed blocks to the original homepage, retaining its ID,
URL, status and language metadata. Original content is backed up in protected
post metadata and a revision; original options and the review draft remain.
Repeated draft generation/application does not duplicate migrated pages.

Rollback restores the original page content; reinstall the previous theme ZIP
to restore the old automatic sections:

```bash
export PATH="/opt/plesk/php/8.4/bin:$PATH" && \
php /usr/local/bin/wp eval-file wp-content/prihod-packages/migrate_home_blocks.php rollback
```

## Verification

`php tests/theme-review-unit.php` covers preview URL boundaries. The disposable
WordPress integration checks draft-only generation, reuse, apply with stable page
ID, preservation of options and original blocks, all five palettes, and rollback:

```powershell
npx.cmd --yes @wp-playground/cli@3.1.57 run-blueprint --mount-dir ./georg-kloster-prihod /wordpress/wp-content/themes/georg-kloster-prihod --mount-dir ./tests /wordpress/wp-content/plugins/prihod-tests --mount-dir ./scripts /wordpress/wp-content/plugins/prihod-scripts --blueprint tests/theme-native-home-blueprint.json
```

No production update or WordPress.org resubmission is performed by these checks.
