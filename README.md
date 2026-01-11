# Recommendation Module

A plug-and-play recommendation engine module for Laravel apps. It tracks product views, logs impressions, and exposes APIs for upsell, cross-sell, most viewed, most purchased, etc. Built on [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules).

## Installation

```
composer require nwidart/laravel-modules
composer require joshbrw/laravel-module-installer
composer require faysal0x1/recommendation-module
php artisan module:enable Recommendation
php artisan migrate
```

Optional: schedule the recompute command
```
php artisan recommendation:recompute
```

## Features
- Multiple algorithms (upsell, cross-sell, most viewed, most purchased, previously viewed, FBT)
- Middleware to track product views (`track.product.view`)
- REST API endpoints under `routes/api.php`

## License
MIT
