# Guest Contributors Migration Tools

This document describes the WP-CLI commands available for managing guest contributors in Newspack.

## Prerequisites

- Newspack Plugin must be active with Guest Contributors feature enabled
- WP-CLI must be installed

## Available Commands

### Get Guest Contributors by Display Name

Find guest contributor(s) by display name. Returns a comma-separated list of user IDs.

```bash
wp newspack-content-migrator guest-contributors-get-by-display-name --display_name="John Smith"
```

#### Options

- `--display_name`: The display name to search for (required)

#### Example Output
```
1,2,3
```

### Create a Guest Contributor

Create a new guest contributor with the specified display name.

```bash
wp newspack-content-migrator guest-contributors-create --display_name="John Smith"
```

#### Options

- `--display_name`: The display name for the guest contributor (required)
- `--force`: Force creation even if a user with the same display name exists

#### Example Usage
```bash
# Create a guest contributor
wp newspack-content-migrator guest-contributors-create --display_name="John Smith"

# Force create even if one exists
wp newspack-content-migrator guest-contributors-create --display_name="John Smith" --force
```

## Notes

- Display names are case-sensitive and must match exactly
- Guest contributors are created with:
  - A randomly generated username
  - A randomly generated email in the format: `name-random@example.com`
  - The Newspack Guest Contributor role
- The `--force` option allows creating duplicate guest contributors with the same display name

This document describes the WP-CLI commands available for managing guest contributors in Newspack.

## Prerequisites

- Newspack Plugin must be active with Guest Contributors feature enabled
- WP-CLI must be installed

## Available Commands

### Create a Guest Contributor

Creates a new guest contributor with the specified display name.

```bash
wp newspack-content-migrator guest-contributors create <display_name>
```

#### Options

- `<display_name>`: The display name for the guest contributor
- `--force`: Force creation even if a user with the same display name exists

#### Examples

```bash
# Create a new guest contributor
wp newspack-content-migrator guest-contributors create "John Smith"

# Force create even if one exists
wp newspack-content-migrator guest-contributors create "John Smith" --force
```

### Get Guest Contributors

Find guest contributor(s) by display name.

```bash
wp newspack-content-migrator guest-contributors get <display_name>
```

#### Options

- `<display_name>`: The display name to search for

#### Examples

```bash
# Find guest contributors by display name
wp newspack-content-migrator guest-contributors get "John Smith"
```

## Notes

- Display names are case-sensitive and must match exactly
- Guest contributors are created with:
  - A randomly generated username
  - A randomly generated email in the format: `name-random@example.com`
  - The Newspack Guest Contributor role
- The `--force` option allows creating duplicate guest contributors with the same display name
