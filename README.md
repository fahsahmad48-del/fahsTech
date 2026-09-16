miniStore

miniStore is a responsive product catalog built with Laravel, MySQL, and JavaScript. Visitors can browse products, search by name or brand, filter by category, and contact the store for more information. A dedicated admin dashboard lets the store owner manage products, categories, and website settings.

Features

Storefront

Home page with featured sections, product categories, and the latest products.

Product search with category filters and pagination.

Dynamic search results using JavaScript Fetch and JSON responses.

Loading indicators while search results are being fetched.

Individual product pages with images, prices, descriptions, and specifications.

Phone and WhatsApp contact links.

Responsive layouts for desktop, tablet, and mobile screens.

Scroll reveal animations.

Admin dashboard

Admin login and logout.

Add, edit, and delete products.

Manage product images, prices, categories, and specifications.

Create and manage categories with product counts.

Prevent deletion of categories that still contain products.

Update store information and website content.

Form validation and feedback for administrative actions.

Technology Stack

Area

Technology

Backend

PHP and Laravel

Database

MySQL / MariaDB

Templates

Laravel Blade and reusable components

Frontend

HTML, CSS, and JavaScript

Dynamic requests

Fetch API and JSON responses

Data access

Eloquent ORM

Caching

Laravel Cache

Dependency management

Composer

How It Works

Laravel handles routing, validation, database queries, and admin operations. Blade templates render the pages, while JavaScript updates search results without reloading the whole page.

Products belong to categories and use readable slugs for their detail-page URLs. Store settings, categories, and latest-product data are cached to reduce repeated database queries. Search results are filtered and paginated on the server.

Project Organization

app/Models/ — Product, category, and setting models.

app/Http/Controllers/ — Storefront, search, and admin logic.

resources/views/ — Storefront pages and admin views.

resources/views/components/ — Shared layout, navbar, and footer components.

public/css/style.css — Website styling.

routes/web.php — Web routes and JSON endpoints.

database/migrations/ — Database schema definitions.

Page-specific JavaScript is included in the relevant Blade views.

Running Locally

The project requires PHP, Composer, and MySQL or MariaDB. Check composer.json for the exact PHP and extension requirements.

The local setup includes installing Composer dependencies, creating an .env file from .env.example, configuring the database, generating an application key, and running the database migrations. Publicly stored product images also require Laravel's storage link.

Use the repository's actual seeders or account-creation process to provision an admin account. If the checked-out version uses queued jobs or a frontend build, start its queue worker or build its assets as required by that version.

Author

Ahmad Fahs

GitHub

LinkedInder the [MIT license](https://opensource.org/licenses/MIT).
