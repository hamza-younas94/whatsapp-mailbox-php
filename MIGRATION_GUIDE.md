# Migration System - Quick Guide

## 🎯 Use ONLY This Command (Future)

```bash
# This is the ONLY migration command you need:
php migrate.php
```

Or better yet: **Just refresh your browser** - migrations run automatically! ✨

---

## 🚫 DO NOT Use This (Old System)

```bash
# ❌ IGNORE THIS - OLD SYSTEM
php database/migrate.php
```

This old system tries to re-create tables that already exist, causing errors.

---

## 🔧 One-Time Setup (Run Once)

Since you already have tables in the database, run this **once** to tell the system they're already done:

```bash
php mark_existing_migrations.php
```

This marks your existing tables as "already migrated" so the system won't try to create them again.

---

## 📋 Summary

**Before:** Two confusing migration systems
**Now:** One automatic system

**What to do:**
1. Run once: `php mark_existing_migrations.php`
2. In future: Just refresh browser or run `php migrate.php`

**New migrations** (like deals table) will run automatically when you refresh the page!

---

## 🎉 Benefits

✅ No more "table already exists" errors
✅ One system instead of two
✅ Automatic on page load
✅ Safe and tracked

---

## 📂 File Locations

- **New system:** `/migrate.php` + `/migrations/` ✅ Use this
- **Old system:** `/database/migrate.php` ❌ Ignore this
