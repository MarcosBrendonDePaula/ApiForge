# Technology Stack & Build System

## Framework & Language
- **PHP**: 8.1+ (supports 8.1, 8.2, 8.3)
- **Laravel**: 10.x, 11.x, 12.x
- **Package Type**: Laravel Service Provider Package

## Dependencies
- **Core**: Laravel Framework only (minimal dependencies)
- **Dev Dependencies**: PHPUnit, Orchestra Testbench, Laravel Pint

## Build & Development Commands

### Testing
```bash
# Run all tests
composer test

# Run tests with coverage report
composer test-coverage

# Coverage output: build/coverage/ (HTML format)
```

### Code Quality
```bash
# Format code using Laravel Pint
composer format

# Pint follows Laravel coding standards
```

### Package Development
```bash
# Install dependencies
composer install

# Run in development mode with Orchestra Testbench
# Tests use SQLite in-memory database
```

## Architecture Patterns

### Service Provider Pattern
- Main entry point: `ApiForgeServiceProvider`
- Registers services, middleware, commands
- Publishes config and migrations

### Service Layer Architecture
- `ApiFilterService`: Core filtering logic
- `FilterConfigService`: Configuration management
- `DocumentationGeneratorService`: AI-powered docs
- `CacheService`: Query result caching
- `QueryOptimizationService`: Performance optimization

### Trait-Based Integration
- `HasAdvancedFilters` trait for existing controllers
- `BaseApiController` for new implementations
- Flexible integration approach

### Configuration-Driven Design
- Extensive config file (`config/apiforge.php`)
- Runtime configuration via controller methods
- Environment-based settings

## Testing Strategy
- **Framework**: PHPUnit with Orchestra Testbench
- **Database**: SQLite in-memory for tests
- **Coverage**: HTML reports in `build/coverage/`
- **CI/CD**: GitHub Actions for automated testing
- **Test Structure**: Feature, Unit, and Performance tests