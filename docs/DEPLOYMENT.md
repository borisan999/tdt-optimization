# Deployment & Setup Guide

## 1. System Requirements

### Server Environment
- **Operating System:** Linux (Ubuntu 22.04+ recommended)
- **Web Server:** Apache 2.4+ or Nginx
- **Database:** MySQL 8.0 or MariaDB 10.6+

### PHP Environment (8.1+)
Required Extensions:
- `php-mysql`
- `php-mbstring`
- `php-xml`
- `php-zip`
- `php-gd`
- `php-curl`

### Python Environment (3.10+)
Required Packages:
- `pulp` (Optimization framework)
- `pandas` (Data processing)
- `openpyxl` (Excel I/O)

## 2. Installation Steps

1.  **Clone the Repository:**
    ```bash
    git clone <repository-url> /var/www/html/tdt-optimization
    ```

2.  **Install PHP Dependencies:**
    ```bash
    composer install --no-dev
    ```

3.  **Configure Environment:**
    - Copy `.env.template` to `.env`.
    - Update `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` with your credentials.

4.  **Database Migration:**
    - Import the initial schema (if provided as `.sql`) or use the setup script.
    - Ensure the `derivadores` and `repartidores` tables are seeded with initial equipment data.

5.  **Install Python Dependencies:**
    ```bash
    pip3 install pulp pandas openpyxl
    ```

6.  **Set Permissions:**
    - The `storage/` and `app/python/10/output/` directories must be writable by the web server user (`www-data`).
    ```bash
    chmod -R 775 storage app/python/10/output
    chown -R www-data:www-data storage app/python/10/output
    ```

## 3. Apache Configuration

Ensure `AllowOverride All` is enabled for the project directory to allow `.htaccess` to handle routing.

```apache
<Directory /var/www/html/tdt-optimization/public>
    AllowOverride All
    Require all granted
</Directory>
```

## 4. Troubleshooting

- **"Optimization Process Failed":** Check that `python3` is in the system path and accessible by the `www-data` user.
- **"Database Connection Error":** Verify `.env` credentials and ensure the MySQL user has full permissions on the database.
- **Empty Visualization:** Check the browser console for JavaScript errors or failed `vis-network` CDN loads.
