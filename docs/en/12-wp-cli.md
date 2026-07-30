# WP-CLI Commands

## Overview

The plugin provides WP-CLI commands for server administration tasks. These commands are available when WP-CLI is installed and the plugin is active.

All commands are under the `wp mpay` namespace.

## Available Commands

### cert-status

Displays current certificate information and validity.

```bash
wp mpay cert-status
```

Output includes certificate subject, issuer, serial number, validity dates, days until expiry, signature algorithm, and key size.

Flags:

- `--format=json` - Output as JSON for scripting
- `--quiet` - Exit code only (0 = valid, 1 = expiring soon, 2 = expired)

### event-log

Displays recent plugin events from the log.

```bash
wp mpay event-log
```

Options:

- `--lines=N` - Number of recent entries to show (default: 50)
- `--level=LEVEL` - Filter by log level (error, warning, info, debug)
- `--since=DATE` - Show entries from this date onward (format: YYYY-MM-DD)
- `--order-key=KEY` - Filter entries for a specific order key

Examples:

```bash
# Show last 20 errors
wp mpay event-log --lines=20 --level=error

# Show all activity for a specific order
wp mpay event-log --order-key=wc_order_abc123

# Show entries from the last week
wp mpay event-log --since=2025-06-01
```

### cleanup

Removes expired transients, old log entries, and temporary files created by the plugin.

```bash
wp mpay cleanup
```

What it cleans:

- Expired idempotency transients
- Diagnostic snapshots older than 24 hours
- Temporary certificate extraction files
- Log entries older than the configured retention period

Options:

- `--dry-run` - Show what would be cleaned without actually deleting
- `--force` - Skip confirmation prompt
- `--older-than=DAYS` - Override log retention period (default: 30)

Examples:

```bash
# Preview cleanup
wp mpay cleanup --dry-run

# Clean everything older than 7 days
wp mpay cleanup --older-than=7 --force
```

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success / OK |
| 1 | Warning (e.g., certificate expiring soon) |
| 2 | Error (e.g., certificate expired, command failed) |

## Cron Usage

Commands work well in system cron jobs. Use `--quiet` for exit-code-only output and `--path` to specify the WordPress installation path.

## Multisite

On WordPress multisite, use the `--url` flag to target a specific site:

```bash
wp mpay cert-status --url=shop.example.md
```

## Permissions

WP-CLI commands run with the permissions of the system user executing them. Ensure read access to the WordPress installation and certificate files, plus write access to temporary directories for cleanup operations.
