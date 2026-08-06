# Project Rules & Guidelines

## Migration Guidelines (CRITICAL)
- **Production Safety**: The project is live in production.
- **NEVER modify or edit existing database migration files.**
- Any schema changes, new columns, index updates, or table modifications **MUST ALWAYS** be implemented by creating a **NEW timestamped migration file** (e.g. `php artisan make:migration ...`).
