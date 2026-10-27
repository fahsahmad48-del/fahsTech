# Fahs Tech

Fahs Tech is an online store I built for a real client. The project gave me experience developing a Laravel application, managing store data, and working with client requirements throughout development.

**Live website:** https://fahstech.up.railway.app

## Features

- **Product management:** Add, edit, and delete phones and other devices.
- **Perfume catalogue:** Manage perfumes, their details, specifications, and images.
- **Categories:** Organize products and filter them by category.
- **Search:** Find products by name or brand and perfumes by name.
- **Admin dashboard:** Manage store content and settings.
- **AJAX interactions:** Update search results and perform admin actions without refreshing the entire page.
- **Image handling:** Validate, process, and manage uploaded product images.
- **Validation and security:** Validate incoming data and protect admin functionality.
- **Caching:** Cache frequently accessed data to reduce repeated database queries.
- **Responsive design:** Support mobile, tablet, and desktop screens.

## Technologies Used

- PHP
- Laravel
- MySQL
- Blade
- JavaScript
- HTML and CSS
- AJAX and JSON
- Git and GitHub
- Railway

## Run Locally

Make sure PHP, Composer, and MySQL are installed.

1. Clone the repository:

   ```bash
   git clone https://github.com/fahsahmad48-del/fahsTech.git
   ```

2. Enter the project directory:

   ```bash
   cd fahsTech
   ```

3. Install the PHP dependencies:

   ```bash
   composer install
   ```

4. Create your environment file:

   ```bash
   cp .env.example .env
   ```

   On Windows PowerShell, you can use:

   ```powershell
   Copy-Item .env.example .env
   ```

5. Generate the application key:

   ```bash
   php artisan key:generate
   ```

6. Configure your database credentials in `.env`, then run the migrations:

   ```bash
   php artisan migrate
   ```

7. Create the public storage link:

   ```bash
   php artisan storage:link
   ```

8. Start the development server:

   ```bash
   php artisan serve
   ```

   Open the local URL displayed in your terminal.

You may also need to configure an admin account according to the project's authentication setup.

## Testing

Run the automated tests with:

```bash
php artisan test
```

## Deployment

The application is deployed on Railway.

Live website: https://fahstech.up.railway.app

## What I Learned

Working on Fahs Tech helped me understand that building software for a real client involves more than implementing features. Requirements need to be clarified throughout development, progress needs to be shared, and feedback needs to be considered before making important decisions.

It also gave me practical experience with Laravel, database management, testing, Git workflows, and maintaining a deployed application.


