# Project Structure & Organization

## Root Directory Structure

```
├── config/           # Package configuration
├── src/             # Main source code
├── tests/           # Test suite
├── examples/        # Usage examples
├── docs/            # Documentation
├── build/           # Build artifacts (coverage, etc.)
└── vendor/          # Composer dependencies
```

## Source Code Organization (`src/`)

### Core Structure
```
src/
├── ApiForgeServiceProvider.php    # Main service provider
├── Config/                        # Configuration classes
├── Console/                       # Artisan commands
├── Exceptions/                    # Custom exceptions
├── Http/                          # HTTP layer
│   ├── Controllers/               # Base controllers
│   └── Middleware/                # Package middleware
├── Observers/                     # Model observers
├── Providers/                     # Additional providers
├── Services/                      # Business logic services
├── Support/                       # Helper classes
└── Traits/                        # Reusable traits
```

### Key Components

#### Services Layer
- **ApiFilterService**: Core filtering and query building
- **FilterConfigService**: Filter configuration management
- **DocumentationGeneratorService**: AI documentation generation
- **CacheService**: Query result caching
- **QueryOptimizationService**: Performance optimization

#### HTTP Layer
- **BaseApiController**: Full-featured base controller
- **ApiPaginationMiddleware**: Request validation middleware

#### Traits
- **HasAdvancedFilters**: Main integration trait for existing controllers

## Test Structure (`tests/`)

```
tests/
├── TestCase.php           # Base test class
├── Feature/               # Integration tests
├── Unit/                  # Unit tests
├── Performance/           # Performance benchmarks
└── Fixtures/              # Test data and mocks
```

## Configuration Structure

### Main Config (`config/apiforge.php`)
- **pagination**: Default pagination settings
- **field_selection**: Field selection rules and limits
- **filters**: Available operators and validation
- **cache**: Caching configuration
- **security**: Security settings and blocked keywords
- **documentation**: AI documentation settings
- **performance**: Query optimization settings

## Naming Conventions

### Classes
- **Controllers**: `*Controller` (PascalCase)
- **Services**: `*Service` (PascalCase)
- **Traits**: `Has*` or descriptive names (PascalCase)
- **Exceptions**: `*Exception` (PascalCase)

### Methods
- **Public API**: camelCase
- **Protected/Private**: camelCase
- **Configuration methods**: `configure*`, `setup*`, `get*`

### Configuration Keys
- **Snake_case** for config keys
- **Nested arrays** for grouped settings
- **Boolean flags** with clear naming (enabled, disabled)

## File Organization Principles

1. **Separation of Concerns**: Each service handles specific functionality
2. **Layered Architecture**: HTTP → Services → Models
3. **Configuration-Driven**: Extensive configuration options
4. **Trait-Based Integration**: Flexible controller integration
5. **Comprehensive Testing**: Feature, unit, and performance tests