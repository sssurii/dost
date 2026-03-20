# INF-02: Code Quality Toolchain

**Phase:** 1 — Infrastructure  
**Complexity:** 1 | **Estimate:** 2h  
**Depends on:** INF-01 (Laravel installed)  
**Blocks:** All feature tickets (standards must be set before code is written)

---

## 1. Objective

Install and configure a strict, automated code quality suite from Day 1:  
- **Laravel Pint** — opinionated PSR-12 / Laravel-style fixer  
- **Larastan** — PHPStan static analysis at Level 5+  
- **PHPMD** — PHP Mess Detector  
- **Pest** — modern test runner

All tools are wired to `composer scripts` so a single `composer check` command enforces all standards.

---

## 2. Tool Matrix

| Tool | Package | Purpose |
|------|---------|---------|
| Laravel Pint | `laravel/pint` | Code style fixer (zero-config, Laravel preset) |
| Larastan | `larastan/larastan` | PHPStan wrapper with Laravel-aware stubs |
| PHPMD | `phpmd/phpmd` | Complexity, duplication, code smells |
| Pest | `pestphp/pest` | Testing framework (BDD-style assertions) |
| Pest Laravel plugin | `pestphp/pest-plugin-laravel` | Artisan helpers for Pest |

---

## 3. Step-by-Step Implementation

### Step 1 — Install Packages

```bash
# Run inside the laravel.test container
docker compose exec laravel.test bash

# Pest (dev dependency)
composer require pestphp/pest --dev --with-all-dependencies
composer require pestphp/pest-plugin-laravel --dev

# Static analysis
composer require larastan/larastan --dev
composer require phpmd/phpmd --dev

# Pint (ships with Laravel 12+ but pin it explicitly)
composer require laravel/pint --dev
```

### Step 2 — Initialize Pest

```bash
php artisan pest:install
# This converts existing PHPUnit tests and creates tests/Pest.php
```

Verify `tests/Pest.php` bootstraps correctly:

```php
<?php
// tests/Pest.php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');
uses(Tests\TestCase::class)->in('Unit');
```

### Step 3 — Configure Laravel Pint

> **Q11 Resolved:** The `"preset": "laravel"` is a **strict superset of PSR-12** — it includes every PSR-12 rule plus Laravel-specific conventions (trailing commas, ordered imports, blank line placement, etc.). No separate PSR-12 preset is needed.

Create `pint.json` at project root:

```json
{
    "preset": "laravel",
    "rules": {
        "array_syntax": {"syntax": "short"},
        "ordered_imports": {"sort_algorithm": "alpha"},
        "no_unused_imports": true,
        "not_operator_with_successor_space": true,
        "trailing_comma_in_multiline": {"elements": ["arrays", "arguments", "parameters"]},
        "php_unit_method_casing": {"case": "snake_case"},
        "phpdoc_scalar": true,
        "unary_operator_spaces": true,
        "binary_operator_spaces": true,
        "blank_line_before_statement": {
            "statements": ["break", "continue", "declare", "return", "throw", "try"]
        },
        "class_attributes_separation": {
            "elements": {"method": "one", "property": "one"}
        },
        "multiline_whitespace_before_semicolons": {"strategy": "no_multi_line"},
        "single_trait_insert_per_statement": true
    }
}
```

### Step 4 — Configure Larastan (PHPStan)

Create `phpstan.neon` at project root:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    # Level 5 — as specified in blueprint ("Level 5+"). ✅ Q12 Resolved.
    level: 5

    paths:
        - app
        - config
        - database
        - routes

    excludePaths:
        - app/Http/Middleware/TrustProxies.php

    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false

    # Ignore known Laravel magic patterns
    ignoreErrors:
        - '#Unsafe usage of new static#'
        - '#Call to an undefined method Illuminate\\Database\\Eloquent\\Builder#'
```

> **Level Guide (for future tightening):**
> - Level 5: strict null safety, type inference, generic types ← **we are here**
> - Level 6: union type narrowing
> - Level 8+: strict mixed type handling — target before first public release

### Step 5 — Configure PHPMD

Create `phpmd.xml` at project root:

```xml
<?xml version="1.0"?>
<ruleset name="Dost PHPMD Rules"
         xmlns="http://pmd.sf.net/ruleset/1.0.0"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://pmd.sf.net/ruleset/1.0.0 http://pmd.sf.net/ruleset_xml_schema.xsd"
         xsi:noNamespaceSchemaLocation="http://pmd.sf.net/ruleset_xml_schema.xsd">

    <description>Dost project mess detector rules</description>

    <!-- Complexity -->
    <rule ref="rulesets/codesize.xml">
        <!-- Allow slightly larger classes for Livewire components -->
        <exclude name="TooManyPublicMethods"/>
    </rule>
    <rule ref="rulesets/codesize.xml/CyclomaticComplexity">
        <properties>
            <property name="reportLevel" value="10"/>
        </properties>
    </rule>

    <!-- Naming -->
    <rule ref="rulesets/naming.xml">
        <exclude name="ShortVariable"/>
        <exclude name="LongVariable"/>
    </rule>

    <!-- Unused code -->
    <rule ref="rulesets/unusedcode.xml"/>

    <!-- Clean code -->
    <rule ref="rulesets/cleancode.xml">
        <!-- Static calls are valid in Laravel facades -->
        <exclude name="StaticAccess"/>
    </rule>

    <!-- Design -->
    <rule ref="rulesets/design.xml"/>
</ruleset>
```

### Step 6 — Wire Everything to `composer.json` Scripts

Open `composer.json` and add the `scripts` section (merge with existing):

```json
{
    "scripts": {
        "test": [
            "php artisan config:clear",
            "php artisan test --parallel --coverage-text --min=80"
        ],
        "pint": [
            "./vendor/bin/pint"
        ],
        "pint:check": [
            "./vendor/bin/pint --test"
        ],
        "analyze": [
            "./vendor/bin/phpstan analyse --memory-limit=512M"
        ],
        "mess": [
            "./vendor/bin/phpmd app,config,database,routes text phpmd.xml"
        ],
        "check": [
            "@pint:check",
            "@analyze",
            "@mess",
            "@test"
        ],
        "fix": [
            "@pint"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
            "@php artisan migrate --graceful --ansi"
        ]
    },
    "scripts-descriptions": {
        "test": "Run all Pest tests with coverage",
        "pint": "Fix code style with Laravel Pint",
        "pint:check": "Check code style without fixing",
        "analyze": "Run Larastan static analysis",
        "mess": "Run PHPMD mess detection",
        "check": "Full quality check: style + analysis + mess + tests",
        "fix": "Fix all auto-fixable code style issues"
    }
}
```

### Step 7 — Configure CI-ready `.editorconfig`

Create `.editorconfig` at project root:

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
indent_style = space
indent_size = 4
trim_trailing_whitespace = true

[*.md]
trim_trailing_whitespace = false

[*.{yml,yaml,json}]
indent_size = 2

[*.blade.php]
indent_size = 4
```

### Step 8 — Pest Configuration

Create `phpunit.xml` (Pest uses it under the hood — update the existing one):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="1"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="pgsql"/>
        <env name="DB_DATABASE" value="dost_testing"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

### Step 9 — Baseline PHPStan (handles legacy code)

```bash
# Generate baseline for any existing violations (so CI starts clean)
./vendor/bin/phpstan analyse --generate-baseline

# Commit phpstan-baseline.neon
git add phpstan-baseline.neon
```

Then reference it in `phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - phpstan-baseline.neon
```

### Step 10 — Git Pre-commit Hook (Optional but recommended)

Create `.git/hooks/pre-commit` (or use `captainhook/captainhook`):

```bash
#!/bin/sh
echo "→ Running Pint style check..."
./vendor/bin/pint --test
if [ $? -ne 0 ]; then
    echo "✗ Pint found style issues. Run: composer fix"
    exit 1
fi

echo "→ Running Larastan..."
./vendor/bin/phpstan analyse --memory-limit=256M --no-progress
if [ $? -ne 0 ]; then
    echo "✗ PHPStan analysis failed."
    exit 1
fi

echo "✓ Quality checks passed."
exit 0
```

```bash
chmod +x .git/hooks/pre-commit
```

---

## 4. Usage Reference

```bash
# Run full quality suite
composer check

# Fix code style
composer fix

# Individual commands
composer pint           # fix style
composer pint:check     # check only
composer analyze        # PHPStan
composer mess           # PHPMD
composer test           # Pest tests

# Run tests with Pest directly
./vendor/bin/pest --parallel
./vendor/bin/pest --coverage --min=80
./vendor/bin/pest tests/Feature/SomeTest.php
```

---

## 5. File Structure After Ticket

```
dost/
├── pint.json
├── phpstan.neon
├── phpstan-baseline.neon
├── phpmd.xml
├── phpunit.xml
├── .editorconfig
├── composer.json          ← scripts section updated
└── tests/
    ├── Pest.php
    ├── Feature/
    └── Unit/
```

---

## 6. Verification Checklist

- [ ] `composer check` exits 0 on fresh install
- [ ] `composer pint:check` exits 0 (no style violations on clean project)
- [ ] `composer analyze` exits 0 at Level 5+
- [ ] `composer test` runs Pest, not PHPUnit, and shows BDD output
- [ ] Coverage threshold set to 80% minimum in CI
- [ ] `phpstan-baseline.neon` committed so CI starts green

---

## 7. Acceptance Criteria

1. `composer check` is the single command that runs all quality tools in sequence.
2. PHPStan Level is **≥ 5** (set to 6 in this plan).
3. Pint is configured with Laravel preset and project-specific overrides.
4. PHPMD ignores Laravel-specific false positives (facades, static calls).
5. Tests use Pest, parallel execution enabled, DB testing uses `dost_testing`.

---

## 8. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| PHPStan false positives on Laravel magic | Larastan stubs cover most; use `@phpstan-ignore` for edge cases |
| PHPMD too noisy on generated code | Exclude `bootstrap/`, `vendor/` in phpmd command paths |
| Coverage drops on AI/audio integration code | Use `@codeCoverageIgnore` on infrastructure glue code only |
| Pint reformats large chunks of committed code | Run `composer fix` and commit immediately after install |

