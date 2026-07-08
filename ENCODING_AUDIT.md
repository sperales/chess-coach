# Encoding Audit

## Scope

This audit belongs to the v1.1.6 hardening release.

It checks repository text files that can contain user-facing UI text, documentation, SQL seed data or JavaScript-rendered labels:

- Markdown
- PHP
- JavaScript
- CSS
- SQL

## Checks Performed

The final audit scanned 76 files for common mojibake markers and invalid UTF-8.

Patterns checked included the usual UTF-8-as-Latin-1 mojibake markers:

- leading Latin capital A with tilde sequences
- stray currency/sign characters commonly produced by double-encoded punctuation
- replacement characters
- common corrupted Spanish accented characters and inverted question/exclamation marks

The audit also decoded the same files using strict UTF-8 decoding.

## Result

No real mojibake markers were found in repository file contents.

No invalid UTF-8 files were found.

Some PowerShell terminal output may still display UTF-8 text incorrectly depending on console encoding, but the underlying repository files are valid UTF-8.

## Database Note

This audit only covers repository files.

If mojibake appears in production data imported from PGN, Chess.com or older database rows, handle it as a separate database-data cleanup task. Do not rewrite already-applied migrations for that scenario.
