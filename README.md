# 🌌 DistoraVision — Executive Business Intelligence Platform

[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-red.svg?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP 8.2](https://img.shields.io/badge/PHP-8.2%2B-blue.svg?style=for-the-badge&logo=php)](https://www.php.net/)
[![Tailwind CSS v3](https://img.shields.io/badge/Tailwind_CSS-v3-38bdf8.svg?style=for-the-badge&logo=tailwind-css)](https://tailwindcss.com)
[![Groq AI](https://img.shields.io/badge/Groq_AI-Powered-orange.svg?style=for-the-badge)](https://groq.com)

**DistoraVision** is an enterprise-grade business intelligence and predictive analytics platform designed specifically for sales distribution networks. By leveraging advanced mathematical modeling, automated data ingestion pipelines, and generative AI, DistoraVision bridges the gap between raw transaction records and high-level C-Suite strategic decisions.

The application features a premium, minimalist **Sleek Navy** theme with modern glassmorphism panels, high-contrast layouts, and fluid micro-interactions.

---

## 🚀 Key Modules & Features

### 📊 Executive Command Center
*   **Real-time MoM Metrics:** Instant tracking of Net Revenue, Gross Margin, and Month-over-Month growth.
*   **Pareto 80/20 Analysis:** Automatic identification of the core 20% of products and outlets generating 80% of revenue.
*   **Proportional Target Management:** Intelligent allocation of global sales targets to salesmen based on weighted 3-month historical performances.
*   **TV Wallboard Command Center:** A specialized, auto-refreshing kiosk interface optimized for large office screens, showing MTD Performance, Top Entities (Principals/Outlets), and dynamic Accounts Receivable (AR) Aging.

### 🔮 Pure Demand Forecasting (Inventory Planning)
*   **3-Month Weighted Moving Average (WMA):** Mathematical baseline forecasting built directly into the database query layer.
*   **YoY Seasonality Index:** Identifies and applies annual demand spikes to forecast models.
*   **OOS (Out-of-Stock) Imputation:** Detects time-gap anomalies where stock-outs occurred, reconstructing true market demand.
*   **Automated Conversion (Karton/CTN):** Automatically extracts packing factors from product names and displays requirements in physical cases.

### 💸 Accounts Receivable (AR) Analytics
*   **8-Tab Analytical Board:** Deep-dive tabs covering Overview, Aging, Credit Risk, Stubborn Accounts, Giro Monitoring, and Collections.
*   **Overdue Buckets:** Automatic bucketing of receivables into *Current, 1-30, 31-60, 61-90, and >90* days.
*   **Collection Mention (CM) Flags:** Automatic prioritization flags for stubborn invoices.

### 🤖 AI Chat Assistant (Natural Language Query)
*   **Context-Aware Dialogues:** Interact directly with an AI assistant to fetch database reports, analyze performance, and ask strategic questions.
*   **Secure SQL Tools Pipeline:** The LLM (using Groq API & Llama-3.1-8b) invokes precise database tools under the hood.
*   **Global ACL Scoping:** Queries run transparently under Eloquent global scopes, automatically restricting data visibility depending on the user's role (Salesman, Supervisor, Admin).

---

## 🛠️ Technical Stack & Architecture

*   **Backend Framework:** Laravel 12.x (streamlined folder structure)
*   **Language Engine:** PHP 8.2+ (type-safety, constructor promotion)
*   **Styling System:** Tailwind CSS (v3) custom Sleek Navy color system
*   **Frontend Logic:** AlpineJS & Vanilla JS
*   **Visualizations:** ApexCharts vector widgets
*   **Logging Engine:** Spatie Activitylog (complete audit trail)
*   **Unit Testing:** PHPUnit (100% test coverage)

---

## ⚙️ Quick Installation

1.  **Clone the Repository:**
    ```bash
    git clone https://github.com/Rijalinor/distoravision.git
    cd distoravision
    ```

2.  **Install PHP & JS Dependencies:**
    ```bash
    composer install
    npm install
    npm run build
    ```

3.  **Setup Environment File:**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
    *Configure your database credentials and `GROQ_API_KEY` inside `.env`.*

4.  **Initialize Database Schema:**
    ```bash
    php artisan migrate
    ```

---

## 👥 Demo Mode & Mock Data

For presentation and testing purposes, DistoraVision features an automatic **Demo Mode** with fully-isolated realistic mock data.

1.  **Seed the Demo Database:**
    ```bash
    php artisan db:seed --class=DemoDataSeeder
    ```
2.  **Access the Demo Dashboard:**
    *   **Login Email:** `demo@admin.com`
    *   **Password:** `password`
    
*When logged in as the demo user, the system automatically redirects all queries to display mock data. For real production accounts, the mock data is completely hidden, ensuring enterprise data privacy.*

---

## 🧪 Testing & Code Formatting

DistoraVision prioritizes code quality, maintaining a clean codebase with 100% passing tests:

*   **Run Automated Tests (PHPUnit):**
    ```bash
    php artisan test --compact
    ```
*   **Format PHP Code (Laravel Pint):**
    ```bash
    vendor/bin/pint --dirty --format agent
    ```

---

## 📘 Detailed Documentation

For a comprehensive guide on database dictionaries, formula definitions, installation steps, user manuals, diagrams, and security architecture, refer to the master documentation:

*   📄 **[DOKUMENTASI.md (Master Technical Documentation)](file:///c:/xampp/htdocs/distoravision/DOKUMENTASI.md)** *(Written in Indonesian)*

---
*Developed by **Rijalinor** — Empowering Distribution Networks through Intelligent Data.*
