# E-Voting WordPress Plugin - Agent Coding Guide

This document provides essential information for AI coding agents working on the E-Voting WordPress plugin codebase.

## Project Overview

This is a WordPress plugin for electronic voting with the following components:
1. PHP backend (models, controllers, REST API)
2. Gutenberg block frontend (React.js)
3. Database schema with polls, questions, answers, and votes
4. Admin interface for managing polls

## Build Commands

```bash
# Build the Gutenberg block for production
npm run build

# Start development server with hot reloading for block development
npm run start
```

### Package Management

```bash
# Install dependencies
npm install

# Update dependencies (use with caution)
npm update
```

## Testing

Currently, there are no automated tests configured in this project. Any new functionality should be manually tested in a WordPress development environment.

To test the Gutenberg block functionality in isolation:
1. Use Storybook or similar component testing tools
2. Manually test in WordPress admin editor
3. Test frontend rendering on published pages/posts

To test REST API endpoints:
1. Use browser developer tools network tab
2. Use curl or Postman to make direct API calls
3. Check browser console for JavaScript errors

WordPress testing environments:
- Local by Flywheel/XAMPP/MAMP
- WordPress Playground/Codepen
- Docker-based setups

## Code Style Guidelines

### PHP Code Standards

#### Imports and Namespaces
- No namespaces used (WordPress convention)
- All classes must be prefixed with `Evoting_`
- Files should only contain one primary class
- Use `defined( 'ABSPATH' ) || exit;` at the top of all PHP files

#### Formatting
- Use spaces, not tabs
- Indentation: 4 spaces
- Line length: No strict limit but aim for readability
- Opening braces on same line as declaration

#### Types and Naming Conventions
- Use PHP 8.1 type declarations where possible
- Class names: PascalCase (`Evoting_Poll`)
- Method names: snake_case (`get_questions`)
- Variable names: snake_case (`$poll_id`, `$user_data`)
- Constants: UPPER_SNAKE_CASE (`DB_VERSION`)

#### Error Handling
- Return `WP_Error` objects for recoverable errors
- Use WordPress internationalization functions (`__()`, `_e()`, etc.)
- Validate input parameters with sanitization functions
- Handle edge cases gracefully

#### Database Interactions
- Use WordPress `$wpdb` class for all database operations
- Always prepare SQL queries with `$wpdb->prepare()`
- Use table prefixes properly (`$wpdb->prefix . 'evoting_polls'`)
- Follow WordPress database naming conventions

#### Documentation
- Document all public methods with PHPDoc blocks
- Include parameter types and return types
- Add descriptions for complex logic

### JavaScript/React Code Standards

#### Imports and Exports
- Use ES6 module syntax
- Import only necessary functions from WordPress packages
- Prefer named exports over default exports

#### Formatting
- Use Prettier defaults
- Indentation: 4 spaces
- Semicolons required
- Quotes: Single quotes for JSX attributes, double for JavaScript strings

#### Component Structure
- Use functional components with hooks
- Separate presentational and container components when beneficial
- Keep components small and focused
- Use proper prop-types validation

#### State Management
- Use React hooks (`useState`, `useEffect`, etc.)
- Prefer local state for simple components
- Consider context API for shared state

#### API Integration
- Use `@wordpress/api-fetch` for REST API calls
- Handle loading and error states appropriately
- Use proper error handling and user feedback

### CSS/Sass Guidelines

#### Naming Convention
- Use BEM methodology
- Prefix classes with `evoting-`
- Use meaningful class names reflecting purpose

#### Organization
- Separate layout, component, and utility styles
- Use variables for consistent values
- Comment complex layout decisions

## WordPress Specifics

### Hooks System
- Follow WordPress hook naming conventions
- Prefix all hooks with `evoting_`
- Use proper priority and argument counts

### Internationalization
- Wrap all user-facing strings with translation functions
- Use text domain `evoting`
- Generate .pot files with WP-CLI for translations

### Security Practices
- Always validate and sanitize user input
- Use proper capability checks
- Nonces for form submissions and AJAX requests
- Proper escaping for output (`esc_html()`, `esc_attr()`, etc.)

### Performance Considerations
- Minimize database queries
- Use caching when appropriate
- Optimize assets for web delivery
- Lazy load non-critical resources

## File Structure Explanation

```
evoting/
├── admin/           # Admin UI and functionality
├── blocks/          # Gutenberg blocks
├── includes/        # Core plugin classes
├── models/          # Data models and database interactions
├── rest-api/        # REST API controllers
├── public/          # Public facing styles and scripts
└── languages/       # Translation files
```

## Database Schema

- `evoting_polls`: Main poll information
- `evoting_questions`: Questions within polls
- `evoting_answers`: Possible answers to questions
- `evoting_votes`: Individual user votes

Primary keys are BIGINT UNSIGNED AUTO_INCREMENT.
Foreign key relationships are enforced through application logic, not database constraints.

## Development Environment Requirements

- WordPress 6.4+
- PHP 8.1+
- Node.js for building Gutenberg blocks
- MySQL 5.7+/MariaDB 10.3+

## Code Quality Tools

While not currently implemented, consider:
- PHP_CodeSniffer for PHP standards
- ESLint for JavaScript
- Stylelint for CSS
- Prettier for code formatting consistency

Run these tools before submitting any code changes.

## Common Patterns in This Codebase

1. Static model methods for database operations
2. REST API controllers extending `WP_REST_Controller`
3. Custom post type and taxonomy registration in main plugin file
4. Gutenberg block registration in index.js files
5. Server-side rendering with render.php templates
6. Admin menu and page registration through dedicated classes

When adding new features, follow these established patterns for consistency.

## Deployment Process

1. Update version numbers in main plugin file
2. Build production assets with `npm run build`
3. Commit and tag release
4. Update changelog
5. Deploy via WordPress plugin directory or direct installation

Always test thoroughly in a staging environment before deploying to production.