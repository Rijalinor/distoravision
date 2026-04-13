# 🌌 DistoraVision - Executive Business Intelligence Platform

[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP 8.2](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**DistoraVision** is a high-performance, real-time analytics platform designed for modern Sales Distribution Management. It bridges the gap between raw secondary sales data and strategic executive decision-making using advanced segmentation, proportional forecasting, and AI-driven narrative synthesis.

---

## 🚀 Key Modules & Features

### 📊 KPI Command Center (C-Suite Intelligence)
- **Real-time Monitoring**: Instant visibility into Revenue, Gross Margin, and Month-over-Month (MoM) growth.
- **Narrative AI Insight**: Natural language summaries that explain the "Why" behind the data, identifying outliers and trends instantly.
- **Pareto 80/20 Analysis**: Automatically identifies the core 20% of products and outlets generating 80% of revenue.

### 🎯 Proportional Target Management
- **Intelligent Distribution**: Calculate and distribute global corporate targets to salesmen based on their weighted 3-month historical performance.
- **Performance Persistence**: Targets are locked per period (YYYY-MM), allowing for historical accuracy and achievement tracking.
- **Run Rate Tracking**: Dynamic calculation of the "Required Daily Sales" to ensure monthly objectives are met on time.

### 👤 Salesman 360° Appraisal
- **Shortfall Analysis**: Automated identification of performance gaps and recovery requirements.
- **Holistic KPIs**: Track coverage frequency, strike rates, and return-to-sales ratios per individual.

### 🏪 Outlet & Principal Intelligence
- **RFM Segmentation**: Advanced categorization into "Champions", "Loyal", "At Risk", and "Hibernating" outlets.
- **Brand Affinity Mapping**: Analyze product-to-outlet relationships to identify white-space and cross-selling opportunities.
- **Sleeper Detection**: Proactive identification of outlets that have ceased ordering within the current period.

---

## 🛠️ Technical Stack

- **Framework**: Laravel 12.x
- **Engine**: PHP 8.2+
- **Database**: MySQL / MariaDB / SQLite
- **Styling**: Tailwind CSS & Modern Glassmorphism Design
- **Frontend**: AlpineJS & Vanilla JavaScript
- **Reporting**: Maatwebsite Excel (Chunked large-scale data processing)
- **Charts**: ApexCharts (Scalable vector visualizations)

---

## ⚙️ Standard Installation

1. **Clone & Enter**
   ```bash
   git clone https://github.com/Rijalinor/distoravision.git
   cd distoravision
   ```

2. **Dependency Setup**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Configure Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Initialize Database**
   ```bash
   php artisan migrate
   ```

---

## 🧪 Demo Installation (Quick Setup)

DistoraVision includes a built-in demo engine to showcase the platform's capabilities without needing real sales data.

### 1. Generate Demo Data
Run the custom seeding command specifying the period you want to simulate (YYYY-MM format):
```bash
# Example: Generate 2000 fake transaction rows for March 2026
php artisan demo:seed 2026-03 --rows=2000
```
*This command will automatically create fake Branches, Principals, Salesmen, Outlets, and randomized Transactions.*

### 2. Activate Demo Mode
Once the data is generated, you can toggle the "Demo Mode" directly from the application's UI sidebar or header.
- **Session-Based**: Demo mode runs on your session, preventing interference with real data for other users.
- **Analytics Sync**: All dashboards, RFM segmentations, and Pareto charts will instantly switch to processing the generated demo data.

---

## 📅 Platform Workflow

1. **Import Layer**: standardizes inbound CSV/Excel secondary data.
2. **Analysis Engine**: Calculates RFM scores, Pareto weights, and Period-over-Period growth.
3. **Strategic Layer**: Managers set global targets; the system distributes them proportionally.
4. **Action Layer**: Salesmen receive target breakdowns; managers monitor real-time run rates.
5. **Closing**: Monthly "Tutup Buku" snapshots preserve the period's data for historical reporting.

---

## ⚖️ License
Distributed under the MIT License. See `LICENSE` for more information.

---
*Developed by **Rijalinor** - Empowering Distribution through Data.*
