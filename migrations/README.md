# Database Migrations

## Automatic Migrations

Migrations now run automatically when you load any page! No manual commands needed.

## How It Works

1. **On every page load**, the system checks for pending migrations
2. **Runs them silently** in the background
3. **Tracks completion** in the `migrations` table
4. **Never runs twice** - each migration runs only once

## Manual Migration (Optional)

If you want to run migrations manually:

```bash
php migrate.php
```

## Creating New Migrations

1. Create a file in `/migrations/` folder
2. Use format: `###_description.php` (e.g., `003_add_users_table.php`)
3. Number should be next in sequence

Example migration:
```php
<?php
require_once __DIR__ . '/../bootstrap.php';
use Illuminate\Database\Capsule\Manager as DB;

echo "Creating example table...\n";

DB::schema()->create('example', function ($table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});

echo "✅ Table created!\n";
```

## Migration Files

Migrations run in numerical order:
- `001_create_migrations_table.php` - Tracks migrations
- `002_add_deals_table.php` - Deals/transactions tracking

## Benefits

✅ **Automatic** - No commands to remember
✅ **Safe** - Never runs same migration twice
✅ **Fast** - Checks happen in milliseconds
✅ **Tracked** - See history in `migrations` table
✅ **Reliable** - Stops on errors to prevent corruption

## Checking Migration Status

Run this SQL to see what's been migrated:

```sql
SELECT * FROM migrations ORDER BY id DESC;
```

## Troubleshooting

If migrations fail:
1. Check `/storage/logs/error.log`
2. Run `php migrate.php` manually to see errors
3. Fix the issue in the migration file
4. Delete the failed entry from `migrations` table
5. Try again

## Production Deployment

Just deploy and refresh the page - migrations run automatically!

No SSH commands needed. 🚀
