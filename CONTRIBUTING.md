# Contributing to Harmonytics UCP Connector for WooCommerce

Thank you for your interest in contributing to Harmonytics UCP Connector for WooCommerce! This document provides guidelines for contributing to the project.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for everyone.

## How to Contribute

### Reporting Bugs

Before submitting a bug report:

1. Check the [existing issues](https://github.com/harmonytics/harmonytics-ucp-connector-for-woocommerce/issues) to avoid duplicates
2. Ensure you're using the latest version
3. Verify the issue is with this plugin and not WooCommerce or WordPress core

When submitting a bug report, include:

- WordPress version
- WooCommerce version
- PHP version
- Plugin version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Any error messages or logs

### Suggesting Features

Feature requests are welcome! Please:

1. Check existing issues for similar requests
2. Clearly describe the use case
3. Explain why this would benefit other users

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Make your changes
4. Run tests (`composer test`)
5. Run coding standards check (`composer phpcs`)
6. Commit with clear messages
7. Push to your fork
8. Open a Pull Request

## Development Setup

### Requirements

- PHP 8.0+
- WordPress 6.0+
- WooCommerce 8.0+
- Composer

### Installation

```bash
git clone https://github.com/harmonytics/harmonytics-ucp-connector-for-woocommerce.git
cd harmonytics-ucp-connector-for-woocommerce
composer install
```

### Running Tests

```bash
composer test
```

### Coding Standards

This project follows WordPress Coding Standards:

```bash
# Check standards
composer phpcs

# Auto-fix issues
composer phpcbf
```

## Coding Guidelines

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use meaningful variable and function names
- Add PHPDoc blocks for all functions and classes
- Write unit tests for new functionality
- Keep backward compatibility in mind

## File Structure

```
harmonytics-ucp-connector-for-woocommerce/
├── harmonytics-ucp-connector-for-woocommerce.php    # Main plugin file
├── includes/              # Core plugin classes
│   ├── rest/              # REST API controllers
│   ├── capabilities/      # UCP capability handlers
│   ├── mapping/           # Schema mappers
│   └── webhooks/          # Webhook functionality
├── admin/                 # Admin interface
└── tests/                 # PHPUnit tests
```

## Questions?

- Open an issue for general questions
- Email: support@harmonytics.com

## License

By contributing, you agree that your contributions will be licensed under the GPL-2.0-or-later license.
