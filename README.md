# PredMock - Engineering Mock Tests & College Predictor Platform

PredMock is a comprehensive web-based platform designed to help students prepare for various engineering entrance exams across India. It offers full-length mock tests, performance analytics, and a robust, data-driven college predictor based on historical cutoff trends.

## Core Features

- **Interactive Mock Tests**: Simulated test environments with JSON-based question banks for major engineering entrance exams:
  - JEE Main (`jee_mock_test.json`)
  - AP EAPCET / TS EAMCET (`ap_eapcet_mock_test.json`, `ts_eamcet_mock_test.json`)
  - BITSAT, VITEEE, SRMJEEE, Amrita AEEE, GITAM GAT
- **College Rank Predictors**: Predicts potential college admissions based on expected scores/ranks. Includes category-wise and branch-wise filtering (e.g., OC, BC, SC, ST, EWS). 
- **User Authentication**: Secure OTP-based signup and login system, complete with a password reset flow.
- **Payment & Subscription Management**: Tracks user payments, plan subscriptions, discount coupons, and processes refund requests via QR codes.
- **Student Support & Ticket System**: Integrated support system where students can raise tickets.
- **Admin & Agent Portals**: Specialized portals (`agent-login.html`, `admin.php`) for administrators and customer care agents (cc012) to manage user tickets and issue resolutions.

## Data Processing & Pipelines

The repository includes Python scripts to automate the extraction of real-world data:
- `parse_pdfs.py`: Uses `pdfplumber` to extract complex historical cutoff ranks (category and branch-wise) directly from official AP EAPCET and TS EAMCET PDFs. It formats this data into JavaScript arrays (`ts_cutoff_data.js`, `ap_cutoff_data.js`) for the frontend predictor engines.
- `fetch_jee_pyqs.py` & `fetch_real_data.py`: Utilities designed to scrape or fetch Previous Year Questions (PYQs) to populate the mock test JSON databases.

## Tech Stack

- **Frontend**: HTML5, CSS3, Vanilla JavaScript (with specialized JS files like `auth.js` and `customer-care-bot.js`).
- **Backend API**: PHP (`api.php` for handling endpoints, `db.php` for PDO connections, `config.php` for environment variables).
- **Database**: MySQL.
- **Data Engineering**: Python 3 (`pdfplumber`, `requests`).

## Database Schema Highlights

The included `database.sql` provides the complete schema, featuring:
- `users`: OTP-based user authentication.
- `payments`, `coupons`, `coupon_usage`, `refund_requests`: Full e-commerce suite for mock test subscriptions.
- `support_tickets`, `ticket_resolutions`: Two-way messaging system between students and support agents.
- `agents`: Roles and approval tracking for platform customer service representatives.

## Installation & Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/vudhayagirihaneesh-a11y/predmock.git
   cd predmock
   ```

2. **Database Setup**
   - Create a MySQL database for the project.
   - Import the provided schema: `mysql -u your_username -p your_database_name < database.sql`
   - Update your database credentials in `config.php` and `db.php`.

3. **Backend Server Setup**
   - Host the directory on a local PHP server (e.g., XAMPP, MAMP, or standard LAMP stack).
   - Ensure the server supports PHP 7.4+ and has the PDO MySQL extension enabled.

4. **Python Dependencies (Optional: for data scripts)**
   - Set up a virtual environment and install the parsing libraries:
   ```bash
   python3 -m venv .venv
   source .venv/bin/activate
   pip install pdfplumber requests
   ```
   - Run `python parse_pdfs.py` to regenerate the cutoff data JS files from the PDFs.

## Directory Structure Overview

- `*-mock-tests.html` - Frontend interfaces for the examination simulator.
- `*-predictor.html` - Frontend interfaces for the rank predictors.
- `*.php` - Backend logic, APIs, and database connections.
- `*.json` - Data files containing mock test structures and questions.
- `*.js` - Client-side logic, cutoff data configurations, and chatbot scripts.
- `*.py` - Python utility scripts for data mining.

## License

This project is proprietary and intended for internal use unless stated otherwise.
