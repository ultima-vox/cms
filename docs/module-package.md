# Module package format

## Install-time trust boundary

A module package must be inspectable before any PHP code from that package is executed.

New packages therefore declare metadata in a static UTF-8 JSON file:

```text
module.json
```

`module.php` remains a compatibility fallback for already installed legacy modules, but package installation and validation never require or execute it.

## Manifest

Example:

```json
{
  "code": "reviews",
  "name": "Reviews",
  "version": "1.2.0",
  "extension_api": "^1.0",
  "default_enabled": false,
  "requires": {
    "core": ">=0.1.0",
    "infosystem": "^1.0"
  },
  "provider": "Vendor\\Reviews\\ReviewsModule"
}
```

Fields:

- `code` — stable package/module identifier;
- `name` — human-readable name;
- `version` — semantic version;
- `extension_api` — compatible stable Extension API range;
- `default_enabled` — installable packages must use `false`; bundled first-party modules may use `true` as a build-time bootstrap hint;
- `requires` — explicit Core/module version constraints;
- `provider` — runtime provider class instantiated only after installation and activation.

## Discovery

Installed module discovery follows this order:

1. `module.json` if present;
2. legacy `module.php` only if no JSON manifest exists.

When `module.json` is present, discovery does not execute `module.php`.

At actual module load time, Core may load the installed module's `autoload.php` and instantiate the declared provider.

## ZIP package layout

Installable packages are ZIP archives with `module.json` at the archive root. The installer intentionally does not guess or strip an arbitrary top-level directory.

Example:

```text
reviews.zip
├── module.json
├── autoload.php
├── src/
│   └── ReviewsModule.php
├── migrations/
│   └── 001_initial.sql
└── templates/
    └── admin/
```

ZIP installation requires the PHP `zip` extension. The normal CMS runtime does not require that extension unless package installation is used.

## Installation

```bash
php bin/console modules:install /path/to/reviews.zip
```

The installer performs the following sequence:

1. opens the archive read-only;
2. validates every archive path and filesystem entry;
3. enforces package file-count and uncompressed-size limits;
4. reads and validates root `module.json` without executing package PHP;
5. checks Core, Extension API and declared module version dependencies;
6. requires `default_enabled=false`;
7. extracts files one-by-one into a hidden staging directory inside `modules/`;
8. re-reads the staged static manifest;
9. atomically renames the staging directory to `modules/<code>` when the filesystem permits it.

The installer rejects an existing target directory. Package updates are a separate lifecycle and are not implemented by overwriting installed code.

## Archive security policy

The installer rejects:

- packages without root `module.json`;
- absolute paths;
- `.` / `..` traversal segments;
- backslash-based archive paths;
- duplicate paths and case-conflicting paths;
- symlinks and special Unix filesystem entries;
- more than 5,000 archive entries;
- individual files above 32 MiB;
- total uncompressed package size above 128 MiB;
- manifests above 64 KiB;
- incompatible Core or Extension API versions;
- missing/incompatible declared module dependencies;
- `default_enabled=true` packages;
- overwrite attempts against an already installed module.

Commercial package signature verification is intentionally a later layer; the archive/install boundary is designed so signature verification can be inserted before extraction without changing module runtime APIs.

## Activation lifecycle

Installation only places verified package code on disk. It does not migrate, synchronize, or enable the module automatically.

The explicit lifecycle is:

```bash
php bin/console modules:install reviews.zip
php bin/console extensions:sync
php bin/console modules:migrate reviews
php bin/console extensions:enable reviews
```

This separation prevents a copied/uploaded package from becoming executable merely because it appeared under `modules/`.

Disabling a module never deletes its data. Uninstall/data cleanup and package update/rollback remain separate lifecycle operations.

## Composer policy

Installing an ordinary module must not run Composer against the CMS root dependency graph. A module must not be able to mutate Core dependencies during installation.

Shared capabilities belong in the stable Extension API or explicit module dependencies. If a module needs private third-party PHP code, its distribution strategy must keep that dependency isolated from the Core Composer graph.
