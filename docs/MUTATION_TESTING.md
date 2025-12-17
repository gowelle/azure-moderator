# Mutation Testing Troubleshooting

## Issue: "No code coverage generator detected"

When running `composer mutate`, you may encounter this error:

```
Coverage needs to be generated but no code coverage generator (pcov, phpdbg or xdebug) has been detected.
```

## Solutions

### Option 1: Install Xdebug (Recommended for Windows)

1. Check your PHP version:
   ```bash
   php -v
   ```

2. Download Xdebug from https://xdebug.org/download
   - Choose the version matching your PHP version and architecture

3. Add to your `php.ini`:
   ```ini
   zend_extension=path/to/xdebug.dll
   xdebug.mode=coverage
   ```

4. Restart your terminal and run:
   ```bash
   composer mutate
   ```

### Option 2: Install PCOV (Faster Alternative)

1. Install PCOV via PECL:
   ```bash
   pecl install pcov
   ```

2. Add to your `php.ini`:
   ```ini
   extension=pcov
   pcov.enabled=1
   ```

3. Run mutation tests:
   ```bash
   composer mutate
   ```

### Option 3: Use phpdbg (Linux/Mac Only)

```bash
phpdbg -qrr vendor/bin/infection --min-msi=80 --min-covered-msi=85
```

**Note**: phpdbg is not available on Windows by default.

### Option 4: Skip Mutation Testing

Mutation testing is optional for development. You can run other quality checks:

```bash
# Run all quality checks except mutation testing
composer format
composer analyse  
composer test
```

## Verifying Coverage Tool Installation

Check if Xdebug or PCOV is installed:

```bash
php -m | findstr -i "xdebug pcov"
```

For Xdebug specifically:
```bash
php -i | findstr -i "xdebug"
```

## CI/CD Note

Mutation testing is typically run in CI/CD environments where Xdebug/PCOV can be easily configured. For local development, it's optional.
