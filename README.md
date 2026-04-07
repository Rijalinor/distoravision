# 🌌 DistoraVision - Executive Business Intelligence Platform

[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP 8.2](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**DistoraVision** is a high-performance, real-time analytics platform designed for Sales Distribution Management. It transforms raw secondary sales data into actionable executive insights through advanced algorithms like RFM Segmentation, Pareto Analysis, and Proportional Target Calculation.

---

## 🚀 Key Modules & Features

### 📊 Executive Dashboard (C-Suite Intelligence)
- **KPI Command Center**: Real-time monitoring of Revenue, Growth (MoM), and Margin Pulse.
- **Narrative AI**: Natural language synthesis that explains "The Why" behind the numbers, providing instant executive summaries.
- **Pareto 80/20 Analysis**: Identify the top 20% of products and outlets driving 80% of your business.

### 🎯 Target Management & Run Rate
- **Proportional Calculator**: Automatically distribute global corporate targets to salesmen based on their 3-month weighted historical contribution.
- **Persistence Layer**: Individual targets are saved per period (YYYY-MM) in the database for accurate performance tracking.
- **Run Rate Tracking**: Real-time calculation of "Required Daily Sales" to ensure targets are hit by month-end.

### 👤 Salesman 360° Appraisal
- **Holistic Profiles**: Complete visibility into individual salesman performance, including achievement vs target, coverage frequency, and return rates.
- **Shortfall Tracking**: Automated gap analysis to identify exactly which team members need management support.

### 🏪 Outlet & Principal Intelligence
- **RFM Segmentation**: Categorize outlets into "Champions", "Loyal", "At Risk", and "Hibernating" based on Recency, Frequency, and Monetary value.
- **Sleeper Outlet Detection**: Identify previously active outlets that have stopped ordering in the current period.
- **Affinity Mapping**: Visualize brand-to-outlet relationships to find cross-selling opportunities.

### 📑 One-Click Reporting (Buku Rapor)
- **Professional Exports**: Generate comprehensive, multi-sheet Excel reports or print-ready PDF "Report Cards" for the entire distribution network.
- **Automated Closing**: Handle monthly period transitions with data integrity checks.

---

## 🛠️ Technical Stack

- **Backend**: Laravel 12.x (PHP 8.2+)
- **Database**: MariaDB / MySQL
- **Frontend**: Blade, CSS Variables (Modern Glassmorphism Design), Vanilla JS
- **Visualizations**: ApexCharts (High-Performance Vector Charts)
- **Data Engine**: Maatwebsite Excel (Chunked Data Processing for large CSV/Excel imports)

---

## ⚙️ Installation & Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/Rijalinor/distoravision.git
   cd distoravision
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Migration**
   ```bash
   php artisan migrate
   ```

5. **Serve the Application**
   ```bash
   php artisan serve
   ```

---

## 📈 Workflow: From Data to Insight

1. **Import**: Upload standardized secondary sales CSV/Excel files.
2. **Process**: The system parses transactions, categorizes products, and links outlets/salesmen.
3. **Calibrate**: Use the **Target Tracker** to set global expectations and distribute them.
4. **Analyze**: Monitor the **Dashboard** and **Intelligence** modules for outliers and trends.
5. **Execute**: Export the **Buku Rapor** to guide the sales team's daily activities.

---

## ⚖️ License
Distributed under the MIT License. See `LICENSE` for more information.

---
*Developed with ❤️ for Advanced Sales Analytics.*
