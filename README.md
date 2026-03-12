# Finance Tracker

A personal finance tracking application built with Laravel. This app helps users manage their income, expenses, and financial transactions with ease.

## Features

- **Transaction Management**: Record and categorize income and expense transactions.
- **Categories**: Organize transactions into predefined categories (Income, Expenses).
- **Transaction Sources**: Track where transactions come from (e.g., salary, freelance, etc.).
- **User Roles and Genders**: Support for different user types and profiles.
- **Reports and Insights**: Generate financial reports and insights (planned for future phases).
- **Responsive UI**: Built with Laravel, Inertia.js, and Tailwind CSS for a modern web experience.

## Installation

1. Clone the repository:

    ```bash
    git clone https://github.com/yourusername/finance-tracker.git
    cd finance-tracker
    ```

2. Install PHP dependencies:

    ```bash
    composer install
    ```

3. Install Node.js dependencies:

    ```bash
    npm install
    ```

4. Copy the environment file and configure it:

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

5. Run database migrations:

    ```bash
    php artisan migrate
    ```

6. Build assets:

    ```bash
    npm run build
    ```

7. Start the development server:
    ```bash
    php artisan serve
    ```

## Usage

- Register a new account or log in.
- Add transactions by specifying type (income/expense), category, amount, and source.
- View your transaction history and summaries.

## Testing

Run the test suite with Pest:

```bash
./vendor/bin/pest
```

## Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository.
2. Create a new branch for your feature.
3. Make your changes and add tests.
4. Submit a pull request.

## License

This project is licensed under the MIT License.
