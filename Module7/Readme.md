# CPSC 5210: Auction Project

This file explains the use of the auction site.  This is a simple auction site for administering auctions for small to medium size auctions. 

## 📋 Table of Contents
- [About the Project](#about-the-project)
- [Directory Structure](#directory-structure)
- [Getting Started](#getting-started)
- [Prerequisites](#prerequisites)
- [Technologies Used](#technologies-used)

## 📖 About the Project
This project does not require any software other than a browser and a free copy of XAMPP. This software will run the website locally and allow multiple users access to the system.  It also must be setup for this local server.  The file db.php must be changed to the ip address of the computer hosting the XAMMP program. After the initial setup, this system works very well.  

## 🗂️ Directory Structure
An overview of how your files are organized:
```text
├── Module7/
│   └── archive.zip
├── .gitignore
└── README.md
```
ALl of the files that are outside of the archive.zip file are the site and they are all in the archive file.

## 🚀 Getting Started
You will need to be accustomed to github.  There are gui installations or you can run from the command line. If you are not accustomed to github, Google will be your friend. 

You will also need to download XAMPP from the link below. It will be installed on the computer that will host the site.  It will run continuously while you are using the site.  MySQL and Apache should be started before trying to run the site.  XAMPP is well known and Google will once again be your friend. You will need to initalize the sql database in XAMPP. You will run schema.sql and it will setup the tables. Once the tables are setup in XAMPP, you can start using the site. 



### Prerequisites
List the software or tools needed to run this project:
*   [XAMPP](https://apachefriends.org) (for Apache and MySQL execution)
*   Web Browser (Chrome, Firefox, Safari)
*   VS Code or a text editor of choice

### Local Installation
1. Clone the repository to your local machine:
   ```bash
   git clone https://github.com/jza0147/CPSC5210.git
   ```
2. Navigate to the Module7 folder.  
3. Open the Archive.zip file and extract.
4. Move the project folder into your XAMPP local host directory (e.g., `htdocs`).
5. Open your browser and navigate to `http://localhost/src`.

## 🛠️ Technologies Used
*   **HTML5 / CSS3** — Frontend layout and Flexbox columns
*   **PHP** — Backend database connections and scripting
*   **MySQL** — Database storage and structured queries
*   **Git / GitHub** — Version control and repository hosting
