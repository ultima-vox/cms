# Module package format

## Install-time trust boundary

A module package must be inspectable before any PHP code from that package is executed.

New packages therefore declare metadata in a static UTF-8 JSON file:

```text
module.json
```

`module.php` remains a compatibility fallback for already installed legacy modules, but package installation and validation must never require or execute it.

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
- `default_enabled` — initial state hint for bundled modules; marketplace/third-party packages should normally use `false`;
- `requires` — explicit Core/module version constraints;
- `provider` — trusted runtime provider class instantiated only after installation and activation.

## Discovery

Installed module discovery follows this order:

1. `module.json` if present;
2. legacy `module.php` only if no JSON manifest exists.

When `module.json` is present, discovery does not execute `module.php`.

At actual module load time, Core may load the installed module's `autoload.php` and instantiate the declared provider.

## Package archive policy

The future installable archive uses the same static manifest and must be verified before extraction into `modules/`.

The installer must reject:

- packages without `module.json`;
- absolute paths or path traversal (`..`);
- ambiguous backslash paths;
- duplicate/conflicting entries;
- archive bombs via file-count/uncompressed-size limits;
- package code/version that is incompatible with Core/Extension API;
- unsigned commercial packages once signature verification is enabled.

Extraction must happen into a staging directory first. Moving a verified module into `modules/<code>` must be atomic where the filesystem permits it.

## Composer policy

Installing an ordinary module must not run Composer against the CMS root dependency graph. A module must not be able to mutate Core dependencies during installation.

Shared capabilities belong in the stable Extension API or explicit module dependencies. If a module needs private third-party PHP code, its distribution strategy must keep that dependency isolated from the Core Composer graph.
