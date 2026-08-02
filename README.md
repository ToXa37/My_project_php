# 📱 CyberPhone — Flagship Smartphone Store

Educational e-commerce store for flagship smartphones featuring user authentication, dynamic catalog, filtering, flexible product options, customer reviews system, and an admin panel.

---

## 🛠️ Requirements

* **PHP**: 7.4 or higher (PHP 8.x recommended)
* **Database**: MySQL / MariaDB
* **Web Server**: Open Server, XAMPP, MAMP, or standalone Nginx / Apache

---

## 🚀 Step-by-Step Setup Guide

### 1. Clone the Repository
Clone the repository to your local directory:
```bash
git clone [https://github.com/ToXa37/My_project_php.git](https://github.com/ToXa37/My_project_php.git)
cd My_project_php
2. Configure Web Server
Move or copy all project files into your local web server's root directory (e.g., htdocs for XAMPP or domains/tech_shop for Open Server).

3. Database Setup
Open phpMyAdmin (typically accessible at http://localhost/phpmyadmin).

Create a new database named tech_shop with collation set to utf8mb4_unicode_ci.

Import your project's SQL dump file (.sql) into the newly created database.

4. Verify Database Connection
Make sure your database credentials match the connection settings in the PHP files (e.g., index.php, product.php):

PHP
$db = new PDO('mysql:host=localhost;dbname=tech_shop;charset=utf8', 'root', '');
5. Run the Application
Open your web browser and navigate to:

http://localhost/tech_shop/
(or use your custom local domain setup)
