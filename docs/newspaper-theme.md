# Newspaper Theme

There is a [Helper class](./src/Logic/NewspaperThemeHelper.php) and [commands for migrating data](./src/Command/NewspaperThemeCommand.php) from the [Newspaper Theme](https://tagdiv.com/newspaper/) into Newspack:

## Commands
### List theme settings set on post metadata
Handy for exploring the data saved on posts by the theme (in the `td_post_theme_settings`). You can see how many posts use each.
```bash
wp newspack-migration-tools newspaper-theme-list-post-settings
```

### Migrate theme settings set on post metadata
Migrates the fields to the relevant fields in Newspack.
```bash
wp newspack-migration-tools newspaper-theme-migrate-post-fields
```