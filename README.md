# Personal Finance Platform

A production-oriented **Personal Finance Platform** built with Laravel + React (Inertia).  
This project is intentionally designed as a **financial ecosystem foundation**, not just a transaction logger.

The current platform already handles daily personal finance operations (wallets, transactions, budgets, recurring flows) and is being prepared to support future modules such as:
- Stock portfolio tracker
- Mortgage calculator (KPR)
- Tax calculator

## Why This Project Exists

Most finance apps stop at recording income and expenses.  
This platform is built to evolve into a **modular financial operating system** where cashflow, assets, projections, and advisory insights live in one integrated architecture.

The long-term direction is:
- one identity and permission model
- one shared wallet/cash layer
- standardized API contracts for cross-module integration
- AI-assisted financial insight generation

## Core Concepts

These concepts are the backbone of the data model and integration strategy:

### Wallet
Represents a cash container (e.g., bank account, e-wallet, cash-on-hand).  
All transactional cash movement happens through wallets.

### Transaction
A financial event affecting a wallet. Supported types:
- `income`: cash enters a wallet
- `expense`: cash leaves a wallet
- `transfer_out`: cash leaves source wallet
- `transfer_in`: cash enters destination wallet

Transfers are stored as paired records (`transfer_out` + `transfer_in`) for clear auditability.

### Category
A classification layer primarily used for expense analysis and budget control  
(e.g., food, transport, utilities).

### Budget
A spending allocation per category and period, used to monitor usage, warnings, and overrun states.

### Recurring Transaction
A transaction template that auto-generates real transactions on schedule (e.g., monthly rent, salary).

### AI Insight
Generated financial analysis persisted per user and period (rule-based and LLM-generated narrative).

### Asset (Future)
A non-cash financial holding (stocks, crypto, mutual funds, etc.).  
Assets will become first-class entities in future modules while staying linked to the same wallet/user system.

## Financial Modeling Principles

To keep financial reporting correct and scalable, the system follows strict accounting semantics:

1. **Transfers do not change net worth**  
   A transfer only moves cash between wallets owned by the same user.

2. **Investment is not an expense**  
   Buying an asset should not be treated as consumption spending.

3. **Buying stocks converts cash into assets**  
   Cash decreases, asset position increases; net worth remains equivalent (ignoring fees/market moves).

4. **Selling stocks converts assets into cash**  
   Asset position decreases, cash increases; realized profit/loss is computed at portfolio layer.

These principles make future portfolio and tax modules consistent with current transaction logic.

## System Architecture

### Backend
- **Laravel 12** (PHP 8.2+)
- Domain logic implemented through service classes (service-layer pattern)
- Queue-based jobs for async workloads
- Scheduled commands for recurring processing and monthly insight generation
- Event-driven side effects for wallet balance sync and dashboard cache invalidation

### Frontend
- **React 18** + **Inertia.js**
- **Vite** for build/dev pipeline
- Tailwind-based UI stack

### Architectural Patterns Used
- **Service Layer Pattern**: business logic centralized in `app/Services`
- **Event-Driven Updates**: transaction events trigger listeners for dependent updates
- **Asynchronous Processing**: queue jobs for non-blocking AI and background tasks
- **Progressive AI Pipeline**: deterministic rules + optional LLM narrative generation

## AI Insight Architecture

The AI subsystem is built as a layered pipeline to remain deterministic, testable, and extensible:

1. **Data Aggregation Layer**  
   `InsightDataService` aggregates period-based financial metrics (income, expense, categories, budget usage, recurring occurrences, etc.).

2. **Rule-Based Insight Layer**  
   `RuleBasedInsightService` generates deterministic alerts (budget warning/exceeded, spending increase, unusually large transactions, top category, highest spending day).

3. **LLM Narrative Layer**  
   `LLMInsightService` builds prompts via `InsightPromptBuilder` and calls an LLM provider (`LLMProviderInterface` -> `OpenAIProvider`) to produce human-friendly narrative summaries.

4. **Persistence Layer**  
   `AIInsightPersistenceService` stores/replaces insights by `(user_id, period_key, type)` for idempotent updates.

5. **Job & Schedule Layer**  
   - `GenerateMonthlyInsightJob`
   - `GenerateBudgetInsightJob`
   - `insights:generate-monthly` scheduler command

6. **Forecast Layer (In Progress)**  
   `ForecastInsightService` provides beta projection endpoints and placeholder-ready contracts for future ML-grade forecasting.

## API Design Philosophy

This project treats routes as stable contracts for future integration:

- API shape is designed to be **module-friendly**, not tied to one UI page
- Insights, wallet, transaction, and budget data are structured for reuse by future tools
- Controllers validate input early and return predictable JSON payloads
- Queue-backed generation endpoints avoid blocking user requests
- The same core APIs can later serve mobile apps, bots, and external portfolio/tax services

## Future Integration Blueprint

The platform is designed for horizontal expansion:

### 1) Stock Portfolio System
- Reuses existing authentication and user profile
- Connects to shared wallet/cashflow ledger
- Introduces asset entities (positions, lots, market valuation)
- Publishes portfolio summaries back to the main dashboard

### 2) Tax Calculator Module
- Consumes categorized transaction and investment realization data
- Uses shared period engine for monthly/yearly tax projections
- Exposes tax estimate endpoints for dashboard and reports

### 3) Mortgage (KPR) Module
- Uses shared user + wallet context for affordability simulation
- Integrates with budgeting and recurring obligations
- Can surface recommendations through AI insight pipeline

### Integration Strategy
- Shared identity and authorization boundaries
- Shared financial primitives (`user`, `wallet`, `transaction`, `period_key`)
- API-based data exchange between modules
- Future-ready module namespace (`app/Modules/*`) without rewriting existing core

## Key Domain Features (Current)

- Authentication and profile management
- Multi-wallet cash management
- Transaction management: income, expense, transfer
- Category management (expense-focused)
- Budget planning and usage tracking
- Recurring transaction automation
- Dashboard analytics
- AI insight generation pipeline (rule-based + optional LLM + forecast beta)

## Project Structure

Core Laravel structure with domain-oriented organization:

```text
app/
├── Console/Commands/        # Scheduled and operational CLI commands
├── Contracts/               # Abstractions (e.g., LLM provider interface)
├── Enums/                   # Domain enums (transaction/insight types)
├── Events/                  # Domain events (transaction lifecycle)
├── Http/Controllers/        # Web/API controllers
├── Jobs/                    # Queue jobs (AI generation, budget alerts)
├── Listeners/               # Event listeners (balance sync, cache invalidation)
├── Models/                  # Eloquent domain models
├── Services/                # Business logic/services
│   └── LLM/                 # LLM provider implementations
└── Modules/                 # (Planned) bounded modules: Stocks, Tax, KPR

resources/js/
└── Pages/                   # Inertia React pages

routes/
├── web.php                 # Main app routes
└── console.php             # Scheduler definitions
```

> Note: `app/Modules` is a planned extension point for upcoming ecosystem modules.

## Development Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- npm
- SQLite/MySQL/PostgreSQL (project defaults to SQLite in `.env.example`)

### Installation

```bash
git clone https://github.com/yourusername/finance-tracker.git
cd finance-tracker
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Optional AI Configuration

Set these variables in `.env` to enable narrative LLM insights:

```env
OPENAI_API_KEY=your_key_here
INSIGHT_LLM_ENABLED=true
INSIGHT_LLM_MODEL=gpt-4o-mini
```

### Run in Development

You can run all core development processes in one command:

```bash
composer run dev
```

Or run individually:

```bash
php artisan serve
npm run dev
php artisan queue:work
php artisan schedule:work
```

### Telegram bot (local development)

Webhook must return `200 OK` quickly; AI replies are processed on the queue.

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
ngrok http 8000
php artisan telegram:set-webhook
php artisan queue:work --queue=telegram,default --tries=3 --timeout=90
```

Set `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, and `APP_URL` (ngrok HTTPS URL) in `.env`.

### Useful Commands

```bash
# Process recurring templates now
php artisan recurring:process

# Queue monthly insights (default: previous month)
php artisan insights:generate-monthly

# Queue monthly insights for a specific period
php artisan insights:generate-monthly --period=2026-03
```

### Testing

```bash
php artisan test
```

## Operational Notes

- Queue worker is required for AI insight and async jobs.
- Scheduler is required for recurring transaction processing and monthly insight automation.
- LLM insight generation is optional; system falls back to deterministic rule-based output when unavailable.

## Roadmap

### Near-Term
- Improve AI narrative quality and multilingual consistency
- Strengthen forecast confidence scoring and explainability
- Expand budget alerting coverage and notification channels

### Mid-Term
- In-app and external notification system
- Telegram bot integration for finance summaries and reminders
- Better API contracts for third-party integrations

### Long-Term Ecosystem
- Stock portfolio module with asset ledger
- Tax calculator module
- Mortgage (KPR) planning module
- Unified financial cockpit across cashflow, assets, liabilities, and insights

## Contributing

Contributions are welcome.  
Please open an issue or pull request with:
- clear problem statement
- proposed approach
- test coverage for business-critical behavior

## License

This project is licensed under the MIT License.
