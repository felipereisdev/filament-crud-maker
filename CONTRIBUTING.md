# Contributing to FilamentCrudGenerator

Thank you for your interest in contributing to the FilamentCrudGenerator package! This document provides some guidelines to help with the contribution process.

## Reporting bugs

If you found a bug, please create an issue on the GitHub repository with the following information:

- Clear and descriptive title
- Detailed steps to reproduce the bug
- Expected behavior versus actual behavior
- Screenshots, if applicable
- PHP, Laravel, and Filament versions in use
- Any other information that may be useful

## Requesting features

We love receiving suggestions for new features! To request a new feature, please:

- Check if the feature has not already been requested in the open issues
- Open a new issue with a clear title
- Describe the feature in detail, including use cases
- Explain why the feature would be useful for most users

## Pull request process

1. Fork the repository
2. Clone your fork locally
3. Create a branch for your changes: `git checkout -b feature/feature-name` or `fix/bug-name`
4. Make your changes
5. Run the tests, if available
6. Format the code using PHP CS Fixer: `composer cs-fix`
7. Commit your changes: `git commit -m 'Clear description of the change'`
8. Push to your branch: `git push origin feature/feature-name`
9. Open a pull request on the original repository

## Code standards

- Follow PSR-12 for code formatting
- Write clear comments for classes and methods
- Maintain adequate test coverage
- Use scalar types and return types in PHP

## Local development

1. Clone the repository
2. Install dependencies: `composer install`
3. Create a test Laravel application to test your changes

## Tests

Before submitting your changes, make sure to run all tests:

```bash
composer test
```

## License

By contributing to this project, you agree that your contributions will be licensed under the same MIT license that covers the project.

Thank you for your contribution!