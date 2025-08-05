# Changelog

All notable changes to `laravel-apiforge` will be documented in this file.

## v1.0.0 - 2024-12-06

### Added
- Initial release
- Advanced API filtering with 15+ operators (eq, like, gte, between, in, etc.)
- Smart pagination with metadata
- Field selection for performance optimization
- Relationship filtering and field selection
- Security features (SQL injection protection, input sanitization)
- Auto-documentation with metadata and examples endpoints
- Comprehensive configuration system
- BaseApiController for quick setup
- HasAdvancedFilters trait for existing controllers
- Middleware for request validation and sanitization
- Support for Laravel 10.x and 11.x
- Support for PHP 8.1, 8.2, and 8.3
- Comprehensive test suite
- Detailed documentation with examples
- GitHub Actions CI/CD pipeline

### Features
- **Advanced Filtering**: Support for 15+ operators including equals, like with wildcards, greater than, between, in array, null checks, and more
- **Smart Pagination**: Automatic pagination with comprehensive metadata including page info, totals, and navigation URLs
- **Field Selection**: Optimize API responses by selecting only needed fields, including support for relationships
- **Relationship Support**: Filter and select fields from model relationships with dot notation
- **Security First**: Built-in SQL injection protection, input sanitization, and validation
- **Auto Documentation**: Automatically generate API documentation with /metadata and /examples endpoints
- **Performance Focused**: Query optimization, caching support, and efficient field selection
- **Developer Friendly**: Simple configuration, extensive examples, and comprehensive error handling
- **Flexible Architecture**: Use with BaseApiController or add HasAdvancedFilters trait to existing controllers
- **Comprehensive Middleware**: Request validation, sanitization, and security checks
- **Configurable**: Extensive configuration options for pagination, security, caching, and more

### Configuration
- Pagination settings (default/max items per page)
- Field selection settings (max fields, blocked fields, aliases)
- Filter settings (available operators, validation rules)
- Security settings (input sanitization, blocked keywords)
- Caching settings (TTL, cache keys, stores)
- Response settings (metadata, timestamps, debugging)

### Supported Laravel Versions
- Laravel 10.x
- Laravel 11.x

### Supported PHP Versions
- PHP 8.1
- PHP 8.2
- PHP 8.3

### Examples Included
- Complete UserController example with BaseApiController
- UserController example with HasAdvancedFilters trait only
- Route configuration examples
- Comprehensive API usage examples
- Testing examples and fixtures