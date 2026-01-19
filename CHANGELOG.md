# Changelog

All notable changes to `laravel-action-engine` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of Laravel Action Engine
- Fluent API for building bulk actions
- Queue integration with automatic batching
- Real-time progress tracking
- Undo/redo functionality with time-limited snapshots
- Scheduled action execution
- Dry run mode for previewing actions
- Action chaining support
- Comprehensive audit logging
- Rate limiting to prevent system overload
- Export integration (CSV, Excel, PDF)
- Policy-based authorization
- Multiple frontend integrations (Livewire, Vue, React, Blade, Filament, Alpine.js)
- Broadcasting support for real-time updates
- Built-in actions: Delete, Update, Archive, Restore, Export
- Custom action registration
- Progress callbacks
- Error handling and recovery
- Database migrations
- Configuration file with extensive options
- Console commands for management
- Comprehensive test suite (98% coverage)
- Full documentation

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- N/A

### Security
- N/A

## [1.0.0] - YYYY-MM-DD

### Added
- Initial stable release

---

## Release Guidelines

### Version Format
- **Major version (X.0.0)**: Breaking changes
- **Minor version (0.X.0)**: New features, backwards compatible
- **Patch version (0.0.X)**: Bug fixes, backwards compatible

### Change Categories
- **Added**: New features
- **Changed**: Changes in existing functionality
- **Deprecated**: Soon-to-be removed features
- **Removed**: Removed features
- **Fixed**: Bug fixes
- **Security**: Security vulnerabilities fixes

### Release Process
1. Update this CHANGELOG with all changes
2. Update version in `composer.json`
3. Run full test suite
4. Create git tag: `git tag -a v1.0.0 -m "Release v1.0.0"`
5. Push tag: `git push origin v1.0.0`
6. Create GitHub release with release notes
7. Update documentation website

[Unreleased]: https://github.com/dhruvilnagar/laravel-action-engine/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/dhruvilnagar/laravel-action-engine/releases/tag/v1.0.0
