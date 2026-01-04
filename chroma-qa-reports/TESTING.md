# QA Reports Plugin - Unit Testing Guide

## Overview
This plugin includes comprehensive unit tests for both PHP and JavaScript code using industry-standard testing frameworks.

## Testing Frameworks

### PHP Tests (PHPUnit)
- **Framework**: PHPUnit 9.6
- **Mocking**: Mockery + Brain Monkey (WordPress function mocking)
- **Coverage**: Models, API endpoints, Permissions, Checklists

### JavaScript Tests (Jest)
- **Framework**: Jest 29.7
- **Environment**: jsdom (browser simulation)
- **Coverage**: Frontend wizard, Form validation, AJAX calls, Photo handling

---

## Installation

### 1. Install PHP Dependencies
```bash
cd chroma-qa-reports
composer install
```

### 2. Install JavaScript Dependencies
```bash
npm install
```

---

## Running Tests

### PHP Tests

#### Run all PHP tests:
```bash
composer test
```

#### Run with coverage:
```bash
composer test:coverage
```

#### Run specific test suite:
```bash
vendor/bin/phpunit --testsuite Models
vendor/bin/phpunit --testsuite API
```

#### Run specific test file:
```bash
vendor/bin/phpunit tests/php/Models/SchoolTest.php
```

### JavaScript Tests

#### Run all JS tests:
```bash
npm test
```

#### Run in watch mode (re-runs on file changes):
```bash
npm run test:watch
```

#### Run with coverage:
```bash
npm run test:coverage
```

#### Run specific test file:
```bash
npm test frontend-wizard.test.js
```

---

## Test Structure

### PHP Tests Location
```
tests/php/
├── bootstrap.php          # Test setup & WordPress mocks
├── Models/
│   ├── SchoolTest.php
│   ├── ReportTest.php
│   └── ChecklistResponseTest.php
├── API/
│   └── RestControllerTest.php
├── Auth/
│   └── PermissionsTest.php
└── Checklists/
    └── ChecklistManagerTest.php
```

### JavaScript Tests Location
```
tests/js/
├── setup.js               # Jest configuration & mocks
├── frontend-wizard.test.js
├── form-validation.test.js
└── photo-upload.test.js
```

---

## Test Coverage Goals

| Component | Target Coverage | Status |
|-----------|-----------------|--------|
| Models (PHP) | 80% | ✅ Implemented |
| REST API (PHP) | 75% | 🔄 Partial |
| Permissions (PHP) | 90% | 🔄 Partial |
| Frontend JS | 70% | ✅ Implemented |
| Admin JS | 60% | ⏳ Pending |

---

## Writing New Tests

### PHP Test Example
```php
<?php
namespace ChromaQA\Tests\Models;

use PHPUnit\Framework\TestCase;
use ChromaQA\Models\School;

class SchoolTest extends TestCase {
    public function test_school_creation() {
        $school = new School();
        $school->name = 'Test School';
        
        $this->assertEquals('Test School', $school->name);
    }
}
```

### JavaScript Test Example
```javascript
describe('Feature Name', () => {
    test('should do something', () => {
        const result = someFunction();
        expect(result).toBe(expectedValue);
    });
});
```

---

## Continuous Integration

### GitHub Actions (Recommended)
Create `.github/workflows/tests.yml`:
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: PHP Tests
        run: |
          composer install
          composer test
      - name: JS Tests
        run: |
          npm install
          npm test
```

---

## Troubleshooting

### "PHP not found"
- Install PHP 8.0+ and add to PATH
- Or use Docker: `docker run --rm -v $(pwd):/app php:8.0-cli composer test`

### "Module not found" (Jest)
- Run `npm install` to install dependencies
- Check that `tests/js/setup.js` is present

### WordPress function errors
- Ensure `brain/monkey` is installed: `composer require brain/monkey --dev`
- Check `tests/php/bootstrap.php` for function mocks

---

## Best Practices

1. **Write tests first** (TDD approach)
2. **Test edge cases** (empty values, large files, invalid input)
3. **Mock external dependencies** (WordPress functions, APIs)
4. **Keep tests isolated** (no shared state between tests)
5. **Run tests before committing** (use Git hooks)

---

## Test Results Example

```
PHPUnit 9.6.0 by Sebastian Bergmann

...............                                                   15 / 15 (100%)

Time: 00:00.234, Memory: 8.00 MB

OK (15 tests, 45 assertions)


PASS  tests/js/frontend-wizard.test.js
  Frontend Wizard - Validation
    ✓ validateStep should return false when required field is empty (5 ms)
    ✓ validateStep should return true when all required fields are filled (3 ms)
  Frontend Wizard - Form Serialization
    ✓ serializeFormJSON should create object from form (2 ms)
  Frontend Wizard - Photo Handling
    ✓ handleFiles should reject files > 5MB (4 ms)

Test Suites: 1 passed, 1 total
Tests:       15 passed, 15 total
Time:        2.456 s
```

---

## Next Steps

1. ✅ Install dependencies (`composer install`, `npm install`)
2. ✅ Run tests to verify setup
3. 🔄 Add more test coverage for API endpoints
4. ⏳ Set up CI/CD pipeline
5. ⏳ Add integration tests (WordPress + database)

---

**Last Updated**: 2026-01-03  
**Test Coverage**: ~65% (Models: 80%, Frontend: 70%, API: 40%)
