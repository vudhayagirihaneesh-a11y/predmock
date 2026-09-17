# PredMock - Engineering Mock Tests & College Predictor Platform

PredMock is a comprehensive web-based platform designed to help students prepare for various engineering entrance exams across India. It offers full-length mock tests, performance analytics, and a data-driven college predictor based on historical cutoff trends.

## Features

- **Interactive Mock Tests**: Simulated test environments for major engineering entrance exams:
  - JEE Main
  - AP EAPCET
  - TS EAMCET
  - BITSAT
  - VITEEE
  - SRMJEEE
  - Amrita AEEE
  - GITAM GAT
- **College Rank Predictors**: Predicts potential college admissions based on expected scores/ranks using past year cutoff data. Available for AP EAPCET, TS EAMCET, VITEEE, BITSAT, SRMJEEE, and Amrita.
- **User Authentication**: Secure signup, login, and password reset functionalities.
- **User Profiles**: Track test history, performance metrics, and manage subscriptions.
- **Admin & Agent Portals**: Specialized portals for administrators and agents to manage users, mock tests, and platform operations.
- **Data Pipelines**: Python scripts to automatically parse cutoff PDFs and fetch previous year questions (PYQs).

## Tech Stack

- **Frontend**: HTML5, CSS3, Vanilla JavaScript.
- **Backend API**: PHP (`api.php`, `db.php`, `config.php`).
- **Database**: MySQL (`database.sql` provided for schema setup).
- **Data Processing**: Python (used for PDF parsing and data fetching scripts).

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

4. **Python Dependencies (for data scripts)**
   - To use the PDF parsers or PYQ fetchers, you will need Python 3.
   - Set up a virtual environment and install necessary packages (e.g., `PyPDF2`, `requests`):
   ```bash
   python3 -m venv .venv
   source .venv/bin/activate
   pip install -r requirements.txt # (If added later)
   ```

## Directory Structure

- `*.html` - Frontend interfaces (mock tests, predictors, auth pages).
- `*.php` - Backend logic, APIs, and database connections.
- `*.json` - Data files containing mock test questions and structures.
- `*.js` - JavaScript logic, cutoff data configurations, and customer support bots.
- `*.py` - Python utility scripts for data mining and PDF extraction.

## Usage

- Start your local web server and navigate to `index.html` to view the homepage.
- Use `signup.html` to create a new user account.
- Take a mock test by navigating to any of the `<exam>-mock-tests.html` pages.
- Access the admin portal via `admin.php` or `agent-login.html`.

## License

This project is proprietary and intended for internal use unless stated otherwise.
