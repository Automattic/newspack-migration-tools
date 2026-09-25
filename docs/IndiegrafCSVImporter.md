# Indiegraf CSV importer

Imports Indiegraf (WP All Export) CSVs of users, posts and pages into a Newspack site. Code: `src/Command/IndiegrafCSVImporter.php`.

## How it all works

```
 Indiegraf CSVs + live site REST API
   │
   ├─ 1. wp newspack-migration-tools indiegraf import-1-of-2 --users-csv=… --posts-csv=… [--pages-csv=…] --live-rest-url=https://{host}
   │     users -> guest contributors (Newspack) + avatars (Simple Local Avatars); posts/pages, categories, tags,
   │     co-authors (Co-Authors Plus), Yoast meta, featured images; Indiegraf blocks -> core/Newspack blocks;
   │     image URLs -> live URLs. Prints steps 2 and 3.
   ├─ 2. printed newspack-post-image-downloader commands: download content images/PDFs to local attachments
   └─ 3. wp newspack-migration-tools indiegraf import-2-of-2 --live-rest-url=https://{host}
         block media IDs, links to the source site, permalink mismatch report
```

- **Requirements** (checked; the run exits with what to fix):
  - active: Newspack, Newspack Blocks, Co-Authors Plus, Simple Local Avatars, Yoast SEO, Safe SVG (delete after step 3);
  - `newspack-post-image-downloader` installed but inactive;
  - confirmed on prompt: the source timezone, and `/%postname%/` permalinks.
- **Logs.** Every row's outcome is in `indiegraf-import-{1,2}-of-2.csv`. Rows that need review (conflict, missing, unresolved, dropped, error) also go to the CLI and `.log`.

### Extending it for other publications

Other Indiegraf exports will differ in columns, blocks and hosts. Most differences are one-line config changes in the class constants:

| Constant | Purpose |
|---|---|
| `POST_COLUMNS`, `USER_COLUMNS` | CSV column => post / user field |
| `POST_META_COLUMNS` | CSV column => post meta, copied verbatim |
| `POST_HANDLED_COLUMNS`, `USER_HANDLED_COLUMNS` | columns consumed by handler code |
| `IGNORED_COLUMNS` | reviewed, intentionally not migrated (`fnmatch()` patterns) |
| `BLOCK_TRANSFORMS` | block pattern => `drop`, `unwrap`, `keep` (logged) or a handler; unknown `indiegraf*/` blocks are unwrapped |

**Workflow (AI-assisted or by hand):**
1. **Audit.** Run `import-1-of-2` and answer `n` at the unmapped-columns prompt. Every non-empty column no constant covers is listed in `indiegraf_unmapped_columns_{csv}.csv`. Nothing has been written to the site yet; only the CSV header was fixed (BOM, repeated names), with a backup.
2. **Classify each flagged column or block** with one constant line. Write a handler only for real logic.
3. **Re-run and verify** with SQL against the CSV and the live site, not with the importer's counters.

**Rules for changes:**
- **Keep Yountville Sun's results unchanged,** unless changing them is the goal. Its verification queries are in the migration plan.
- **Prefer data-driven behavior** (a no-op when the data is absent) over flags. Add a flag only when a behavior can hurt some publications, and default it to the safe choice.
- **Honor the data rules:** an absent column is never touched; an empty value clears the field on update; a failed lookup skips only its own field and is logged.
- **Save posts only via `Posts::update_post_without_modified_date()`** (see [Re-runs](#re-runs-and-conflicts)).
- **Add a section below for each new feature.**

## Features

### `--live-rest-url` (required)

The source site's public REST API (`/wp-json/wp/v2/`) fills in what the CSVs lack or have outdated:

| Use | Request | Why |
|---|---|---|
| exact post categories + live image URLs | `posts\|pages?include=<100 IDs>&_fields=id,categories,content` | the CSV omits assigned parent categories and has pre-CDN image URLs |
| category tree | `categories?per_page=100&page=N` | parent chains, live slugs, Yoast primary (a source term ID) |
| avatars | `media/{id}` | the CSV has only the media ID |
| bylines/page authors missing from the Users CSV | `users/{id}` | see [Bylines](#bylines-and-authors) |
| links | (host only) | see [Links](#links-to-the-source-site) |

- **Efficient:** batched (about 8 requests for 750 posts) and cached per run.
- **Failures** skip only their field: one warning, then per-field log rows.
- **Drafts** aren't in public REST, so they fall back to the CSV (logged).
- **Categories:** live terms are keyed `indiegraf-category-{ID}` with their live slug, so same-name siblings stay separate (Yountville Sun has 2 top-level "Food & Wine").

### Re-runs and conflicts

Every `import-1-of-2` run processes only what changed, so a launch-day refresh is the same 3 steps on fresh CSVs.
- **Tracking.** On save, the source `Post Modified Date` is written to `post_modified` and stored as `_nmt_original_modified`.
- **Next run:**
  - post not found -> create;
  - source date newer -> update in place;
  - otherwise skip;
  - the post's `post_modified` differs from the stored value -> it was edited on this site: a **conflict**, skipped unless `--update-already-imported-posts`.
- **Hence** every later save (import-2-of-2, fixes) must keep `post_modified`, and the downloader's direct DB update does.
- **Missing posts** (in the site, gone from the CSV) are reported, never deleted.
- **Scope.** Created and updated IDs go to `indiegraf_touched_post_ids.txt`, which scopes steps 2 and 3.

### Bylines and authors

Everyone becomes a guest contributor. Each byline name (or a page's `Author ID`) resolves in this order:
1. **The `Author ID`'s imported user**, if the name matches.
2. **A guest contributor with that display name.**
3. **A Users CSV row skipped by `--import-roles`** (e.g. a subscriber) with that name, imported now.
4. **A new byline user.** It gets bio, job title and avatar from REST `users/{Author ID}` only when the REST name matches: `Author ID` is the posting account, often not the byline.
5. **No name and no user anywhere:** an `AuthorID_{id}` placeholder user (logged).

Co-author order follows the CSV.

### Live image URLs

The CSV has the source's local image URLs, but the live site renders images from a CDN, and the local files may be gone:
- CSV: `https://yountvillesun.com/wp-content/uploads/2025/07/Fair-Farm-Set-Up-1-1024x768.jpg` (404)
- live: `https://d1qvdom7axrrra.cloudfront.net/wp-content/uploads/2025/07/31184942/Fair-Farm-Set-Up-1-1024x768.jpg`

The importer keeps the CSV content and uses the live HTML only to look up each image's current URL, matched by month and file name.
- **Why not use the live content:** public REST has only rendered HTML, without block markup.
- **Matching:** live `src`/`srcset` URLs are indexed by `YYYY/MM/file`. The CDN's `{timestamp}/` folder is allowed.
- **Replacement:** each matching CSV `<img src>` is replaced before the block transforms. The count goes in the audit row.
- **Equal URLs** (no CDN) mean no change, so there's no flag.

### Downloader hand-off

The importer downloads no content images. It prints `newspack-post-image-downloader` commands, which download the (already swapped) URLs and rewrite each `<img>` to a local attachment with a `wp-image-{ID}` class.
- **Host groups:** WordPress upload paths go in one `download-images` command. CDN timestamp paths go in another, with `--do-not-download-large-sizes`. PDFs come only from hosts that also serve images.
- **Plugin switching:** the downloader bundles an older NMT that overrides NCCM's while both are active, so the printed commands deactivate NCCM, activate the downloader, and switch back after.
- **SVGs:** Safe SVG allows them only for users who can upload files, and WP-CLI runs as no user, so the commands use `wp --user=<first administrator>`.

### Block media-ID sync

`import-2-of-2` sets `core/image` `id` and `core/media-text` `mediaId` from the `wp-image-{ID}` class in the block's own `<img>`, recursively (gallery images too), and `core/file` `id` via `attachment_url_to_postid( href )`.
- **Why not look up by URL:** resized URLs (`-1024x683.jpg`, `-scaled`) don't resolve to an attachment.
- **Only local files.** An ID is set only when the `<img>` is a local upload and the attachment exists. A remote `<img>` still has the source site's ID in its class, so it is logged, not copied.
- **Idempotent.**

### Links to the source site

In `import-2-of-2`, after all posts exist, links on the source host (with or without `www.`) are rewritten:
- an imported post's source permalink -> its local permalink;
- the source home page -> the local home page;
- a media-text `mediaLink` -> the local attachment page;
- other source links stay.

### Slugs and permalinks

- **Slug taken by an existing post** (e.g. WP's default Privacy Policy page): WP adds a suffix (`privacy-policy-2`), and a warning names the holder. No `_wp_old_slug` is added, since it can't redirect while the other post holds the slug.
- **Permalink report.** `import-2-of-2` lists published posts whose source path differs from the local one and that no `_wp_old_slug` redirects.
