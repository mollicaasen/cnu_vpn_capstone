CNU SecureSailing VPN Project Set Up

Front-End and User Interface:
Index.php - It is the dashboard for the user to use containing tabs such as Home, About WireGuard, How it Works, Network Health, Security Policy.
Style.css - This designs the overall dashboard.

Authentication System:
Log_in.php - This allows for the users to log into the system where it checks the user’s credentials and verifies it to direct to the dashboard.
Log-out.php - It destroys the user’s session after logging out and redirects to the public dashboard.

Generating VPN:
Generate_vpn.php: This is a backend script where it generates a WireGuard configuration file allowing the users to connect to the VPN.
Wg_sync_from_db.php: It reads the data and pushes it to the WireGuard interface. 

Notifications System:
Fetch_notification.php: The database notifies when someone logs into the system.
Mark_read.php: It updates the database that someone has logged into the system.

Setting Up Instructions:
Download the ZIP Folder.
Import scheme.sql to MySQL.
Configure the database connections to the PHP Files.
WireGuard binary must be installed using wg_sync_from_db.php for the VPN to execute.




