# 2nd-Hand — Project Index

Self-hosted second-hand marketplace. Live at **2h.mgzllc.com**, open source as
`pixmix/own-bay` (GPL v3). PHP 8.2 + SQLite, no framework.

Mesh identity: contributor **c-025** (`2nd-Hand`). Project memory (journal,
decisions) lives outside this vault at
`~/.claude/projects/-home-pm-dev-www-2nd-hand/memory/`.

## Notes

- [[Architecture]] — how the application is put together, verified against code
- *Deploy SOP* — how a change reaches production, plus the pre-flight checks.
  Kept out of this repo: it names production accounts and a co-tenant service on
  the same host. Local-only note, gitignored.
- [[Currency feature]] — per-site → per-item currency (deployed)
- [[Location feature]] — per-item coordinates with seller-chosen precision (deployed)

## Source documents

- `README.md` — public GitHub readme. Brought current 2026-07-31 (SQLite, seller
  accounts, per-item currency and location, MCP server, `--with-db`).
- `LICENCE` — GNU GPL v3

## Conventions

- UK English throughout.
- Machinery excluded from this vault via `.obsidian/app.json` `userIgnoreFilters`:
  `tools/`, `.claude/`, `.git/`, `photos/`, `site/uploads/`, `site/data/`,
  archives.
