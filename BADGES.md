# Badges for README.md

Add these badges to the top of your README.md file to showcase the package status and quality metrics:

## Recommended Badges

```markdown
# Laravel Action Engine

[![Latest Version](https://img.shields.io/packagist/v/dhruvilnagar/laravel-action-engine)](https://packagist.org/packages/dhruvilnagar/laravel-action-engine)
[![Total Downloads](https://img.shields.io/packagist/dt/dhruvilnagar/laravel-action-engine)](https://packagist.org/packages/dhruvilnagar/laravel-action-engine)
[![License](https://img.shields.io/packagist/l/dhruvilnagar/laravel-action-engine)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/dhruvilnagar/laravel-action-engine)](composer.json)

[![Tests](https://github.com/dhruvilnagar/laravel-action-engine/workflows/Tests/badge.svg)](https://github.com/dhruvilnagar/laravel-action-engine/actions)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](phpstan.neon)
[![Code Style](https://img.shields.io/badge/code%20style-Pint-orange)](https://laravel.com/docs/pint)
[![Codecov](https://codecov.io/gh/dhruvilnagar/laravel-action-engine/branch/main/graph/badge.svg)](https://codecov.io/gh/dhruvilnagar/laravel-action-engine)

[![Laravel 10.x](https://img.shields.io/badge/Laravel-10.x-red)](https://laravel.com)
[![Laravel 11.x](https://img.shields.io/badge/Laravel-11.x-red)](https://laravel.com)
```

## Status Badges

Add a status section to indicate package maturity:

```markdown
## Status

⚠️ **Early Development** - This package is actively developed and has implemented comprehensive testing, safety features, and monitoring. However, it is still new and lacks extensive real-world usage. Use with caution in production environments.

### Quality Metrics

- ✅ **CI/CD Pipeline** - Automated testing across multiple PHP and Laravel versions
- ✅ **Static Analysis** - PHPStan level 8 (maximum strictness)
- ✅ **Safety Features** - Confirmation prompts, soft deletes, rollback support
- ✅ **Comprehensive Tests** - Memory, error handling, broadcasting, undo functionality
- ✅ **Monitoring Ready** - Integrations for Telescope, Sentry, Prometheus, Datadog
- ✅ **Documentation** - Architecture, troubleshooting, optimization guides
- ⏳ **Community Validation** - Awaiting adoption and feedback
```

## Feature Badges

```markdown
## Features

[![Queue Support](https://img.shields.io/badge/Queue-Supported-green)]()
[![Progress Tracking](https://img.shields.io/badge/Progress-Tracking-blue)]()
[![Undo Support](https://img.shields.io/badge/Undo-Supported-yellow)]()
[![Broadcasting](https://img.shields.io/badge/Broadcasting-WebSocket-purple)]()
[![Audit Trail](https://img.shields.io/badge/Audit-Trail-orange)]()
[![Safety Features](https://img.shields.io/badge/Safety-Features-red)]()
```

## Framework Badges

```markdown
## Frontend Support

[![Livewire](https://img.shields.io/badge/Livewire-3.x-pink)]()
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-green)]()
[![React](https://img.shields.io/badge/React-18.x-blue)]()
[![Alpine.js](https://img.shields.io/badge/Alpine.js-3.x-teal)]()
[![Filament](https://img.shields.io/badge/Filament-3.x-yellow)]()
[![Blade](https://img.shields.io/badge/Blade-Templates-red)]()
```

## Documentation Badges

```markdown
## Documentation

[![Architecture Docs](https://img.shields.io/badge/docs-architecture-blue)](docs/architecture.md)
[![Troubleshooting](https://img.shields.io/badge/docs-troubleshooting-orange)](docs/troubleshooting.md)
[![Performance](https://img.shields.io/badge/docs-performance-green)](docs/performance-tuning.md)
[![Monitoring](https://img.shields.io/badge/docs-monitoring-purple)](docs/monitoring.md)
[![Database Optimization](https://img.shields.io/badge/docs-database-red)](docs/database-optimization.md)
```

## Monitoring Integration Badges

```markdown
## Monitoring Integrations

[![Telescope](https://img.shields.io/badge/Laravel-Telescope-purple)]()
[![Sentry](https://img.shields.io/badge/Error%20Tracking-Sentry-orange)]()
[![Prometheus](https://img.shields.io/badge/Metrics-Prometheus-red)]()
[![Datadog](https://img.shields.io/badge/APM-Datadog-blue)]()
[![New Relic](https://img.shields.io/badge/APM-New%20Relic-green)]()
```

## Complete Example

Here's a complete example of how your README could start:

```markdown
# Laravel Action Engine

[![Latest Version](https://img.shields.io/packagist/v/dhruvilnagar/laravel-action-engine)](https://packagist.org/packages/dhruvilnagar/laravel-action-engine)
[![Total Downloads](https://img.shields.io/packagist/dt/dhruvilnagar/laravel-action-engine)](https://packagist.org/packages/dhruvilnagar/laravel-action-engine)
[![License](https://img.shields.io/packagist/l/dhruvilnagar/laravel-action-engine)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/dhruvilnagar/laravel-action-engine)](composer.json)

[![Tests](https://github.com/dhruvilnagar/laravel-action-engine/workflows/Tests/badge.svg)](https://github.com/dhruvilnagar/laravel-action-engine/actions)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](phpstan.neon)
[![Code Style](https://img.shields.io/badge/code%20style-Pint-orange)](https://laravel.com/docs/pint)
[![Codecov](https://codecov.io/gh/dhruvilnagar/laravel-action-engine/branch/main/graph/badge.svg)](https://codecov.io/gh/dhruvilnagar/laravel-action-engine)

A powerful Laravel package for managing bulk operations with queue support, progress tracking, undo functionality, scheduled execution, and comprehensive audit trails.

## ⚠️ Status

**Early Development** - Comprehensive testing, safety features, and monitoring implemented. Awaiting real-world usage validation. Use with caution in production.

### Quality Assurance

✅ CI/CD Pipeline | ✅ PHPStan Level 8 | ✅ Safety Features | ✅ Comprehensive Tests | ✅ Monitoring Ready

## 🚀 Features

- **Batch Processing** - Efficient queue-based processing with configurable batch sizes
- **Progress Tracking** - Real-time progress updates via polling or WebSocket
- **Undo Support** - Time-limited undo with full record snapshots
- **Safety Features** - Confirmation prompts, soft deletes, automatic rollback
- **Scheduled Execution** - Schedule bulk actions for future execution
- **Audit Trail** - Complete audit logging for compliance
- **Monitoring** - Integrations for Telescope, Sentry, Prometheus, Datadog
- **Multi-Frontend** - Support for Livewire, Vue, React, Alpine, Filament

## 📖 Documentation

- [Architecture & Design](docs/architecture.md)
- [Troubleshooting Guide](docs/troubleshooting.md)
- [Performance Tuning](docs/performance-tuning.md)
- [Database Optimization](docs/database-optimization.md)
- [Monitoring & Observability](docs/monitoring.md)
- [Improvement Summary](docs/improvement-summary.md)

## 📦 Installation

```bash
composer require dhruvilnagar/laravel-action-engine
```

## 🎯 Quick Start

```php
use DhruvilNagar\ActionEngine\Facades\BulkAction;

BulkAction::on(User::class)
    ->query(fn($q) => $q->where('active', false))
    ->update(['active' => true])
    ->withProgress()
    ->withUndo()
    ->requireConfirmation()
    ->execute();
```

## 🧪 Testing

```bash
composer test              # Run all tests
composer test:unit         # Run unit tests
composer test:feature      # Run feature tests
composer analyse           # Run PHPStan
composer format            # Format code with Pint
```

## 🤝 Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 📝 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
```
