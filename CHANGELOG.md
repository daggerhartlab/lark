# Changelog

All notable changes to this module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this module adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Export media, files, and Entity Embed entities embedded in formatted text as
  dependencies (#60).

## [2.1.5] - 2026-10-22

### Fixed

- Remove incorrect menu links (#59).

## [2.1.4] - 2026-10-08

### Changed

- Release tags are created by a workflow. No changes to the module.

## [2.1.3] - 2026-09-23

### Added

- Support for config pages (#58).
- Export and import pathauto settings (#57).

## [2.1.2] - 2026-09-04

### Fixed

- Include the module version in tagged releases.

## [2.1.1] - 2026-09-04

### Added

- Provide a module version when the repo is tagged (#56).

### Fixed

- PHP deprecations (#55).

## [2.1.0] - 2026-09-03

### Added

- Menu handling (#50).
- Show path alias details when exporting links (#54).

### Fixed

- Permissions for the Lark tabs (#51).

## [2.0.8] - 2026-07-23

### Fixed

- Exporting non-translatable content.

## [2.0.7] - 2025-11-18

### Fixed

- Exporting file assets.

## [2.0.6] - 2025-11-14

### Fixed

- Exporting file assets along with file entities.

## [2.0.5] - 2025-07-24

### Fixed

- Exporting file assets of translated media.

## [2.0.4] - 2025-06-25

### Fixed

- Clear field values when importing an existing entity, in case the imported
  values are fewer than the existing values.

## [2.0.3] - 2025-06-25

### Fixed

- Recursion during export.

## [2.0.2] - 2025-04-29

### Fixed

- Import form.

## [2.0.1] - 2025-04-25

### Fixed

- Exporting.
- Pruning sources.

## [2.0.0] - 2025-03-04

### Added

- Upload an archive to a source, and import an archive with Drush.
- Prune exports, individually or all at once from the source page.

### Changed

- Refactored sources, exportables, and rendering into services. Code that
  used the 1.x utility classes must be updated.

## [1.22] - 2025-01-26

### Fixed

- Set meta options when creating an exportable.

## [1.2.1] - 2025-01-26

### Fixed

- Attempting to load a file that was not exported.

## [1.2.0] - 2025-01-26

### Added

- Settings that control how file assets are handled during export and import.
- Show image file assets on the export form.

### Changed

- Export status summary is more descriptive.

### Removed

- The option to rename on export.

## [1.1.4] - 2025-01-24

### Fixed

- File copies on remote filesystems.

## [1.1.3] - 2025-01-06

### Changed

- Cache export discovery to speed up operations.

### Fixed

- Prevent exporting circular references.

## [1.1.2] - 2025-01-03

### Changed

- Prefix exported file assets with the UUID of their file entity.

## [1.1.1] - 2024-12-31

### Fixed

- Line endings in settings.

## [1.1.0] - 2024-12-31

### Added

- Handle path aliases by UUID.

## [1.0.2] - 2024-12-30

### Added

- `hook_lark_should_export_entity()`, so modules can skip exporting entities.

## [1.0.1] - 2024-12-17

### Changed

- License is GPL-2.0-or-later.

## [1.0.0] - 2024-12-17

Initial release with YAML export and import.

[Unreleased]: https://github.com/daggerhartlab/lark/compare/2.1.5...HEAD
[2.1.5]: https://github.com/daggerhartlab/lark/compare/2.1.4...2.1.5
[2.1.4]: https://github.com/daggerhartlab/lark/compare/2.1.3...2.1.4
[2.1.3]: https://github.com/daggerhartlab/lark/compare/2.1.2...2.1.3
[2.1.2]: https://github.com/daggerhartlab/lark/compare/2.1.1...2.1.2
[2.1.1]: https://github.com/daggerhartlab/lark/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/daggerhartlab/lark/compare/2.0.8...2.1.0
[2.0.8]: https://github.com/daggerhartlab/lark/compare/2.0.7...2.0.8
[2.0.7]: https://github.com/daggerhartlab/lark/compare/2.0.6...2.0.7
[2.0.6]: https://github.com/daggerhartlab/lark/compare/2.0.5...2.0.6
[2.0.5]: https://github.com/daggerhartlab/lark/compare/2.0.4...2.0.5
[2.0.4]: https://github.com/daggerhartlab/lark/compare/2.0.3...2.0.4
[2.0.3]: https://github.com/daggerhartlab/lark/compare/2.0.2...2.0.3
[2.0.2]: https://github.com/daggerhartlab/lark/compare/2.0.1...2.0.2
[2.0.1]: https://github.com/daggerhartlab/lark/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/daggerhartlab/lark/compare/1.22...2.0.0
[1.22]: https://github.com/daggerhartlab/lark/compare/1.2.1...1.22
[1.2.1]: https://github.com/daggerhartlab/lark/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/daggerhartlab/lark/compare/1.1.4...1.2.0
[1.1.4]: https://github.com/daggerhartlab/lark/compare/1.1.3...1.1.4
[1.1.3]: https://github.com/daggerhartlab/lark/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/daggerhartlab/lark/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/daggerhartlab/lark/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/daggerhartlab/lark/compare/1.0.2...1.1.0
[1.0.2]: https://github.com/daggerhartlab/lark/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/daggerhartlab/lark/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/daggerhartlab/lark/releases/tag/1.0.0
