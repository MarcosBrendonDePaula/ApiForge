# Contributing

We love your input! We want to make contributing to ApiForge as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features
- Becoming a maintainer

## Development Process

We use GitHub to host code, to track issues and feature requests, as well as accept pull requests.

## Pull Requests

1. Fork the repo and create your branch from `main`.
2. If you've added code that should be tested, add tests.
3. If you've changed APIs, update the documentation.
4. Ensure the test suite passes.
5. Make sure your code lints.
6. Issue that pull request!

## Any contributions you make will be under the MIT Software License

In short, when you submit code changes, your submissions are understood to be under the same [MIT License](http://choosealicense.com/licenses/mit/) that covers the project. Feel free to contact the maintainers if that's a concern.

## Report bugs using GitHub's [issue tracker](../../issues)

We use GitHub issues to track public bugs. Report a bug by [opening a new issue](../../issues/new); it's that easy!

## Write bug reports with detail, background, and sample code

**Great Bug Reports** tend to have:

- A quick summary and/or background
- Steps to reproduce
  - Be specific!
  - Give sample code if you can
- What you expected would happen
- What actually happens
- Notes (possibly including why you think this might be happening, or stuff you tried that didn't work)

People *love* thorough bug reports. I'm not even kidding.

## Development Setup

1. Clone the repository
```bash
git clone https://github.com/MarcosBrendonDePaula/ApiForge.git
cd laravel-apiforge
```

2. Install dependencies
```bash
composer install
```

3. Run tests
```bash
composer test
```

4. Run code style fixer
```bash
composer format
```

5. Run static analysis
```bash
composer analyse
```

## Testing

We use PHPUnit for testing. Please ensure all tests pass before submitting a PR.

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test
vendor/bin/phpunit tests/Unit/ApiFilterServiceTest.php
```

## Code Style

We use Laravel Pint for code formatting. Please run the formatter before submitting:

```bash
composer format
```

## Static Analysis

We use Larastan for static analysis. Please ensure your code passes analysis:

```bash
composer analyse
```

## Documentation

- Update the README.md if you change functionality
- Add/update docblocks for new/changed methods
- Update examples if API changes
- Add/update configuration documentation

## Feature Requests

We welcome feature requests! Please open an issue and:

1. Explain the problem you're trying to solve
2. Provide examples of how you'd like to use the feature
3. Consider if this is something that would benefit the majority of users

## Code of Conduct

### Our Pledge

We pledge to make participation in our project and our community a harassment-free experience for everyone.

### Our Standards

Examples of behavior that contributes to creating a positive environment include:

* Using welcoming and inclusive language
* Being respectful of differing viewpoints and experiences
* Gracefully accepting constructive criticism
* Focusing on what is best for the community
* Showing empathy towards other community members

### Enforcement

Instances of abusive, harassing, or otherwise unacceptable behavior may be reported by contacting the project team. All complaints will be reviewed and investigated and will result in a response that is deemed necessary and appropriate to the circumstances.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.