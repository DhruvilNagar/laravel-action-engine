# Contributing to Laravel Action Engine

Thank you for considering contributing to Laravel Action Engine! This document outlines the standards and guidelines for contributing to this project.

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Focus on what is best for the community
- Show empathy towards other community members

## Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/dhruvilnagar/laravel-action-engine.git
   cd laravel-action-engine
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Run tests**
   ```bash
   composer test
   ```

## Coding Standards

### PHP Standards

- **PSR-12**: All PHP code must follow PSR-12 coding standards
- **Type Hints**: Always use type hints for parameters and return types
- **Strict Types**: Use `declare(strict_types=1)` when appropriate
- **PHP 8.1+**: Use modern PHP 8.1+ features (enums, readonly properties, etc.)

### Documentation Standards

#### Class Documentation

Every class must have a comprehensive docblock:

```php
/**
 * ClassName
 * 
 * Brief description of what this class does.
 * 
 * More detailed explanation of purpose, responsibilities,
 * and how it fits into the system.
 * 
 * Features:
 * - Feature 1
 * - Feature 2
 * 
 * @example
 * $instance = new ClassName();
 * $instance->doSomething();
 */
class ClassName
{
    // ...
}
```

#### Method Documentation

All public and protected methods must have docblocks:

```php
/**
 * Brief description of what the method does.
 * 
 * More detailed explanation if needed, including
 * implementation details, side effects, or caveats.
 *
 * @param Type $param Description of parameter
 * @return Type Description of return value
 * @throws ExceptionType When this exception is thrown
 */
public function methodName(Type $param): Type
{
    // ...
}
```

#### Property Documentation

Properties should have clear, descriptive docblocks:

```php
/**
 * Description of what this property stores.
 * 
 * Additional context about its purpose or usage.
 * 
 * @var Type
 */
protected Type $propertyName;
```

### Naming Conventions

- **Classes**: PascalCase (e.g., `BulkActionExecutor`)
- **Methods**: camelCase (e.g., `executeAction`)
- **Variables**: camelCase (e.g., `$recordCount`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `STATUS_PENDING`)
- **Database Tables**: snake_case (e.g., `bulk_action_executions`)
- **Database Columns**: snake_case (e.g., `created_at`)

### Code Organization

#### File Structure

```
src/
├── Actions/          # Core action execution logic
├── Console/          # Artisan commands
├── Contracts/        # Interfaces
├── Events/           # Event classes
├── Exceptions/       # Custom exceptions
├── Facades/          # Laravel facades
├── Http/             # Controllers, middleware, requests, resources
├── Jobs/             # Queue jobs
├── Models/           # Eloquent models
├── Support/          # Helper classes and services
└── Traits/           # Reusable traits
```

#### Method Order

Within a class, organize methods in this order:

1. Magic methods (`__construct`, `__call`, etc.)
2. Public static methods
3. Public methods
4. Protected methods
5. Private methods
6. Static helper methods

### Error Handling

#### Exceptions

- Create specific exception classes for different error scenarios
- Include helpful error messages with context
- Provide static factory methods for common scenarios

```php
public static function notFound(string $name): static
{
    return new static(
        "Resource '{$name}' was not found. Please check the name and try again."
    );
}
```

#### Validation

- Validate input early (fail fast)
- Provide clear validation error messages
- Use Laravel's validation features where appropriate

### Testing Standards

#### Test Coverage

- Aim for 90%+ code coverage
- All public methods must have tests
- Test both success and failure scenarios
- Test edge cases and boundary conditions

#### Test Organization

```php
/**
 * @test
 */
public function it_performs_expected_behavior(): void
{
    // Arrange: Set up test data and dependencies
    $model = TestModel::factory()->create();
    
    // Act: Execute the code being tested
    $result = $this->service->doSomething($model);
    
    // Assert: Verify the expected outcome
    $this->assertTrue($result);
    $this->assertDatabaseHas('table', ['id' => $model->id]);
}
```

#### Test Naming

- Use descriptive, readable test names
- Follow pattern: `it_[expected_behavior]_when_[condition]`
- Examples:
  - `it_creates_execution_record_when_action_starts`
  - `it_throws_exception_when_rate_limit_exceeded`

### Performance Considerations

- **Batch Processing**: Use chunking for large datasets
- **Eager Loading**: Avoid N+1 queries with `with()`
- **Caching**: Cache frequently accessed data
- **Database Queries**: Use proper indexing and query optimization
- **Memory Management**: Be mindful of memory usage in batch operations

### Security

- **Input Validation**: Always validate and sanitize user input
- **Authorization**: Check permissions before executing actions
- **SQL Injection**: Use parameter binding, never raw queries with user input
- **XSS Prevention**: Escape output in views
- **CSRF Protection**: Ensure CSRF tokens for state-changing operations

## Pull Request Process

1. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Write your code**
   - Follow coding standards
   - Add/update tests
   - Update documentation

3. **Run quality checks**
   ```bash
   composer test          # Run tests
   composer analyse       # Static analysis
   composer format        # Code formatting
   ```

4. **Commit your changes**
   - Write clear, descriptive commit messages
   - Follow conventional commits format:
     ```
     type(scope): subject
     
     body
     
     footer
     ```
   - Types: feat, fix, docs, style, refactor, test, chore

5. **Push and create PR**
   ```bash
   git push origin feature/your-feature-name
   ```
   - Create PR with clear description
   - Reference any related issues
   - Wait for code review

### Commit Message Examples

```
feat(actions): add support for custom action validation

Implement validation hooks that allow custom validation
logic before action execution.

Closes #123
```

```
fix(undo): resolve issue with soft delete restoration

Fixes bug where soft deleted records were not being
properly restored during undo operations.

Fixes #456
```

## Code Review Guidelines

### For Reviewers

- Be constructive and respectful
- Explain the "why" behind suggestions
- Approve only when code meets all standards
- Test the changes locally when possible

### For Contributors

- Address all review comments
- Don't take feedback personally
- Ask questions if feedback is unclear
- Update your PR based on feedback

## Documentation

### When to Update Documentation

- New features or functionality
- Changed behavior
- New configuration options
- Breaking changes

### Documentation Types

1. **README.md**: High-level overview and quick start
2. **Code Comments**: Inline explanation of complex logic
3. **DocBlocks**: API documentation
4. **CHANGELOG.md**: Version history and changes

## Release Process

1. Update CHANGELOG.md
2. Update version in composer.json
3. Create release tag
4. Publish release notes
5. Update documentation

## Getting Help

- **Issues**: Report bugs or request features via GitHub Issues
- **Discussions**: Ask questions in GitHub Discussions
- **Email**: Contact maintainers directly for sensitive matters

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

Thank you for contributing to Laravel Action Engine! 🚀
