# Twig Templating Engine Integration

## 🎨 What Changed

Your WhatsApp Mailbox now uses **Twig** templating engine for clean separation of logic and presentation!

## 📁 New Structure

```
whatsapp-mailbox/
├── templates/               # Twig templates
│   ├── base.html.twig      # Base layout
│   ├── login.html.twig     # Login page
│   └── dashboard.html.twig # Main mailbox
├── storage/
│   └── cache/
│       └── twig/           # Compiled templates cache
```

## ✨ Benefits

### Before (Mixed PHP/HTML):
```php
<h1><?php echo htmlspecialchars($user['username']); ?></h1>
```

### After (Clean Twig):
```twig
<h1>{{ user.username }}</h1>
```

### Features:
✅ **Auto-escaping** - XSS protection by default
✅ **Template inheritance** - DRY principle
✅ **Clean syntax** - Easy to read/maintain
✅ **Reusable layouts** - One base template
✅ **Production caching** - Fast performance

## 🚀 Usage

### Render a template:
```php
// From any PHP file
render('template.html.twig', [
    'user' => $user,
    'title' => 'My Page'
]);
```

### Or get template output:
```php
$html = view('template.html.twig', ['data' => $value]);
```

## 📝 Template Syntax

### Variables:
```twig
{{ user.username }}
{{ app_name }}
```

### Conditionals:
```twig
{% if error %}
    <div class="error">{{ error }}</div>
{% endif %}
```

### Loops:
```twig
{% for contact in contacts %}
    <div>{{ contact.name }}</div>
{% endfor %}
```

### Template Inheritance:
```twig
{% extends "base.html.twig" %}

{% block title %}My Page{% endblock %}

{% block content %}
    <h1>Hello World</h1>
{% endblock %}
```

## 🔧 Global Variables

Available in all templates:
- `{{ app_name }}` - Application name from .env
- `{{ app_url }}` - Application URL from .env

## 📦 Installation

Already configured! Just run:
```bash
composer install
```

## 🎯 File Changes

- ✅ [composer.json](composer.json) - Added Twig dependency
- ✅ [bootstrap.php](bootstrap.php) - Twig initialization
- ✅ [app/helpers.php](app/helpers.php) - Added `view()` and `render()` functions
- ✅ [login.php](login.php) - Now uses Twig template
- ✅ [index.php](index.php) - Now uses Twig template
- ✅ [templates/base.html.twig](templates/base.html.twig) - Base layout
- ✅ [templates/login.html.twig](templates/login.html.twig) - Login template
- ✅ [templates/dashboard.html.twig](templates/dashboard.html.twig) - Dashboard template

## 🔒 Security

- **Auto-escaping enabled** - All output is escaped by default
- **Production caching** - Templates compiled for performance
- **No raw PHP** - Templates can't execute arbitrary code

## 📚 Resources

- **Twig Documentation:** https://twig.symfony.com/doc/3.x/
- **Template Syntax:** https://twig.symfony.com/doc/3.x/templates.html

---

**Your application now has professional-grade templating! 🎉**
