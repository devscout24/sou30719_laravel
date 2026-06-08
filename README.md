# Project Name
# Admin Template

## Requirements
- PHP >= 8.3
- Composer
- Laravel >= 12
- MySQL (Laragon)



2. Configure the `.env` file:
    ```env
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=nickfroehnelt
    DB_USERNAME=root
    DB_PASSWORD=
    ```

3. Install dependencies:
    ```bash
    composer install
    ```

4. Generate the application key:
    ```bash
    php artisan key:generate
    ```

5. Run database migrations:
    ```bash
    php artisan migrate
    ```

6. Start the development server:
    ```bash
    php artisan serve
    ```

7. Access the application in your browser at:
    ```
    http://127.0.0.1:8000
    ```



