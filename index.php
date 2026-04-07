<?php
session_start();

// --- AUTHENTICATION (MySQL users table — same DB as generate_vpn.php / schema.sql) ---
$login_error = false;
$login_error_message = '';

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $input_id = trim((string) ($_POST['student_id'] ?? ''));
    $input_pass = (string) ($_POST['password'] ?? '');

    if ($input_id === '' || $input_pass === '') {
        $login_error = true;
        $login_error_message = 'Please enter your Student ID and password.';
    } else {
        $conn = new mysqli('localhost', 'root', 'root', 'cnu_vpn');
        if ($conn->connect_error) {
            $login_error = true;
            $login_error_message = 'Unable to reach the sign-in database. Try again later.';
        } else {
            $conn->set_charset('utf8mb4');
            $password_hash = md5($input_pass);
            $stmt = $conn->prepare('SELECT id FROM users WHERE student_id = ? AND password = ? LIMIT 1');
            if ($stmt === false) {
                $login_error = true;
                $login_error_message = 'Sign-in failed. Please try again.';
                $conn->close();
            } else {
                $stmt->bind_param('ss', $input_id, $password_hash);
                $stmt->execute();
                $stmt->bind_result($user_row_id);
                $authenticated = $stmt->fetch();
                $stmt->close();

                if ($authenticated) {
                    $msg = 'VPN session started for ' . $input_id;
                    $note = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
                    if ($note) {
                        $note->bind_param('ss', $input_id, $msg);
                        $note->execute();
                        $note->close();
                    }

                    $_SESSION['user_id'] = $input_id;
                    $_SESSION['user'] = $input_id;
                    $_SESSION['logged_in'] = true;

                    $conn->close();
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }

                $login_error = true;
                $login_error_message = 'Invalid Student ID or Password. Please try again.';
                $conn->close();
            }
        }
    }
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNU SecureSailing | VPN Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --cnu-blue: #003366;
            --cnu-silver: #a5acaf;
            --accent-blue: #0056b3;
            --bg-dark: #0a0f18;
            --card-bg: #111827;
            --text-light: #f8f9fa;
            --success: #00ff88;
            --error: #ff5f56;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: var(--text-light); line-height: 1.6; overflow-x: hidden; }

        /* Navigation */
        .navbar {
            display: flex; justify-content: space-between; padding: 1.5rem 10%; align-items: center;
            background: rgba(10, 15, 24, 0.8); backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .logo { font-size: 1.5rem; font-weight: 800; cursor: pointer; }
        .logo span { color: var(--cnu-silver); font-weight: 300; }
        .nav-links { list-style: none; display: flex; gap: 2rem; align-items: center; }
        .nav-links a { text-decoration: none; color: var(--text-light); font-weight: 500; transition: 0.3s; cursor: pointer; }
        .nav-links a:hover { color: var(--accent-blue); }

        /* Hero Section */
        .hero {
            display: flex; align-items: center; justify-content: space-between; padding: 5% 10%; min-height: 85vh;
            background: linear-gradient(rgba(10, 15, 24, 0.85), rgba(10, 15, 24, 0.85)), url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&q=80&w=2070');
            background-size: cover; background-position: center; border-bottom: 2px solid var(--accent-blue);
        }
        .hero h1 { font-size: 4rem; line-height: 1.1; margin-bottom: 1.5rem; }
        .hero h1 span { color: var(--accent-blue); display: block; }

        /* Buttons */
        .btn-main { background: var(--accent-blue); color: white; border: none; padding: 1rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; text-decoration: none; display: inline-block; }
        .btn-main:hover { background: #004494; transform: translateY(-2px); }
        .btn-secondary { background: transparent; border: 1px solid var(--cnu-silver); color: white; padding: 1rem 2rem; border-radius: 8px; cursor: pointer; margin-left: 10px; }
        .btn-logout { background: #333; font-size: 0.8rem; padding: 0.5rem 1rem; }
        .btn-logout:hover { background: var(--error); }

        /* Speed Test UI */
        #speed-test-ui { margin-top: 30px; background: rgba(0,0,0,0.4); padding: 25px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); width: 100%; max-width: 400px; }
        .speed-gauge { width: 100%; height: 100px; border: 4px solid var(--accent-blue); border-bottom: none; border-radius: 150px 150px 0 0; position: relative; overflow: hidden; }
        .gauge-needle { width: 4px; height: 90px; background: white; position: absolute; bottom: 0; left: 50%; transform-origin: bottom; transform: rotate(-90deg); transition: transform 2s cubic-bezier(0.4, 0, 0.2, 1); }

        /* Features Grid */
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; padding: 5rem 10%; }
        .feature-card { background: var(--card-bg); padding: 3rem 2rem; border-radius: 12px; text-align: center; transition: 0.3s; border: 1px solid rgba(255,255,255,0.05); }
        .feature-card:hover { transform: translateY(-10px); border-color: var(--accent-blue); }

        /* Tab Content Control */
        .tab-content { display: none; padding: 80px 10%; min-height: 80vh; text-align: center; }
        .active-tab { display: block; }

        /* Restriction Styling */
        .restricted-overlay { background: rgba(255,95,86,0.1); border: 1px dashed var(--error); padding: 40px; border-radius: 15px; max-width: 600px; margin: 0 auto; }

        /* Configuration Terminal */
        .config-terminal { background: #010409; border-radius: 10px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); overflow: hidden; font-family: 'Courier New', monospace; text-align: left; max-width: 800px; margin: 40px auto; }
        .terminal-header { background: #161b22; padding: 10px 15px; display: flex; gap: 8px; align-items: center; }
        .dot-red { width: 12px; height: 12px; background: #ff5f56; border-radius: 50%; }
        .dot-yellow { width: 12px; height: 12px; background: #ffbd2e; border-radius: 50%; }
        .dot-green { width: 12px; height: 12px; background: #27c93f; border-radius: 50%; }
        .terminal-body { padding: 25px; font-size: 0.9rem; color: #e6edf3; }
        .cmd { color: #f97583; }
        .output { color: #7ee787; opacity: 0.8; }

        /* Live Log Terminal */
        .log-terminal { background: #010409; color: var(--success); font-family: 'Courier New', monospace; padding: 20px; border-radius: 8px; height: 180px; overflow-y: hidden; font-size: 0.85rem; border: 1px solid #333; margin: 20px 0; text-align: left; }

        /* Login Modal */
        .modal-overlay { 
            display: <?php echo $login_error ? 'flex' : 'none'; ?>; 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.9); backdrop-filter: blur(8px); z-index: 1000; 
            align-items: center; justify-content: center; 
        }
        .modal-card { background: var(--card-bg); width: 100%; max-width: 420px; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .modal-header { padding: 25px; background: var(--cnu-blue); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 35px; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; font-size: 0.8rem; margin-bottom: 8px; color: var(--cnu-silver); }
        input, select { width: 100%; padding: 14px; background: #0d131f; border: 1px solid #333; color: white; border-radius: 6px; font-size: 1rem; }
        
        .error-msg { color: var(--error); font-size: 0.8rem; margin-bottom: 15px; text-align: center; }

        /* Footer */
        .cnu-footer { background: #05080f; padding: 60px 10% 30px; border-top: 1px solid #222; }

        /* About flow */
        .about-flow { max-width: 720px; margin: 2rem auto 0; text-align: left; }
        .about-flow ol { margin: 0; padding: 0; list-style: none; counter-reset: about-step; }
        .about-flow li {
            counter-increment: about-step;
            position: relative;
            padding: 1.25rem 1.25rem 1.25rem 3.25rem;
            margin-bottom: 1rem;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
            line-height: 1.65;
        }
        .about-flow li::before {
            content: counter(about-step);
            position: absolute;
            left: 1rem;
            top: 1.25rem;
            width: 1.75rem;
            height: 1.75rem;
            background: var(--accent-blue);
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .about-flow li strong { color: var(--text-light); }
        .about-flow li code { font-size: 0.9em; background: #0d131f; padding: 0.15rem 0.4rem; border-radius: 4px; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="logo" onclick="showTab('home')">CNU <span>SecureSailing</span></div>
        <ul class="nav-links">
            <li><a onclick="showTab('home')">Home</a></li>
            <li><a onclick="showTab('config')">WireGuard setup</a></li>
            <li><a onclick="showTab('about')">About</a></li>
            <li><a onclick="showTab('status')">Network Health</a></li>
            <li><a onclick="showTab('security')">Security Policy</a></li>
            <li>
                <?php if ($is_logged_in): ?>
                    <a href="?action=logout" class="btn-main btn-logout">Logout (<?php echo htmlspecialchars($_SESSION['user_id']); ?>)</a>
                <?php else: ?>
                    <a class="btn-main" style="padding: 0.6rem 1.2rem;" onclick="toggleModal()">Sign In</a>
                <?php endif; ?>
            </li>
        </ul>
    </nav>

    <div id="home-tab" class="tab-content active-tab" style="padding: 0; text-align: left;">
        <header class="hero">
            <div class="hero-content">
                <h1>The Lab, <span> is Everywhere.</span></h1>
                <p>Connect to the Christopher Newport University virtual private network with high-speed, encrypted tunneling. Perfect for SEC students.</p>
                
                <div class="hero-btns" style="margin: 2.5rem 0;">
                    <?php if (!$is_logged_in): ?>
                        <button class="btn-main" onclick="toggleModal()">Connect Now</button>
                    <?php else: ?>
                        <button class="btn-main" style="background: var(--success); color: #000;">Signed in to portal</button>
                        <button type="button" class="btn-main" style="margin-left: 10px;" onclick="connectVPN()">Connect VPN</button>
                    <?php endif; ?>
                    <button class="btn-secondary" onclick="runSpeedTest()">Test Latency</button>
                </div>

                <div id="speed-test-ui" style="display:none;">
                    <div class="speed-gauge"><div id="needle" class="gauge-needle"></div></div>
                    <p id="speed-result" style="text-align: center; margin-top: 15px; font-weight: 600;">Contacting Gateway...</p>
                </div>
            </div>

            <div class="hero-visual">
                <div style="background: rgba(255,255,255,0.05); padding: 2rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); width: 320px; text-align: center; backdrop-filter: blur(10px);">
                    <div style="width: 12px; height: 12px; background: var(--success); border-radius: 50%; margin: 0 auto 15px; box-shadow: 0 0 15px var(--success);"></div>
                    <p>Status: <strong style="color:var(--success)">SYSTEM ONLINE</strong></p>
                    <p style="font-size: 0.8rem; color: var(--cnu-silver); margin-top: 10px;">Primary Node: Newport News, VA</p>
                </div>
            </div>
        </header>

        <section class="features">
            <div class="feature-card">
                <h3>Virtual Access to the Server</h3>
                <p>Full access to the computer labs from anywhere.</p>
            </div>
            <div class="feature-card">
                <h3>High Security</h3>
                <p>Protected by WireGuard®.</p>
            </div>
            <div class="feature-card">
                <h3>Fiber Optimization</h3>
                <p>Direct peering with CNU’s fiber backbone for minimal lag.</p>
            </div>
        </section>
    </div>
    <style>
    /* Tooltip Container */
    .cnu-tooltip {
        position: relative;
        display: inline-block;
        border-bottom: 1px dashed var(--accent-blue);
        color: var(--accent-blue);
        cursor: help;
        font-weight: bold;
    }

    /* Tooltip Text (Hidden by default) */
    .cnu-tooltip .tooltiptext {
        visibility: hidden;
        width: 220px;
        background-color: #1a2234;
        color: #fff;
        text-align: center;
        border-radius: 8px;
        padding: 12px;
        position: absolute;
        z-index: 10;
        bottom: 135%; 
        left: 50%;
        margin-left: -110px;
        opacity: 0;
        transition: opacity 0.3s, transform 0.3s;
        font-size: 0.8rem;
        font-family: sans-serif;
        line-height: 1.4;
        border: 1px solid var(--accent-blue);
        box-shadow: 0 10px 20px rgba(0,0,0,0.5);
        transform: translateY(10px);
    }

    /* Tooltip Arrow */
    .cnu-tooltip .tooltiptext::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: var(--accent-blue) transparent transparent transparent;
    }

    /* Show Tooltip on Hover */
    .cnu-tooltip:hover .tooltiptext {
        visibility: visible;
        opacity: 1;
        transform: translateY(0);
    }
</style>
    <div id="config-tab" class="tab-content">
    <h2>The WireGuard® Protocol</h2>
    <p style="color:var(--cnu-silver); max-width: 700px; margin: 0 auto 3rem;">
        SecureSailing uses WireGuard, a  fast VPN protocol that ensures your data to remain private.
    </p>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; text-align: left; max-width: 900px; margin: 0 auto;">
        
        <div class="interactive-card" style="background: var(--card-bg); padding: 2.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); border-top: 4px solid var(--accent-blue); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); cursor: default;">
            <h3 style="color: var(--text-light); margin-bottom: 1.2rem;">Next Generation Cryptography</h3>
            <p style="font-size: 0.95rem; margin-bottom: 1rem; color: var(--cnu-silver);">
                Unlike other VPNs that rely on complex standards, WireGuard uses a simple method:
            </p>
            <ul style="font-size: 0.9rem; color: var(--text-light); list-style: none;">
    
                <li style="margin-bottom: 8px;"><strong style="color:var(--accent-blue);">Speed:</strong> Uses <span class="cnu-tooltip">ChaCha20<span class="tooltiptext">A high-speed stream cipher for encryption.</span></span>, 
                <span class="cnu-tooltip">Poly1305<span class="tooltiptext">An authenticator used to ensure data integrity.</span></span>,<span class="cnu-tooltip">Curve25519<span class="tooltiptext">An elliptic curve used for fast and secure key exchanges.</span></span>for high speed and secure tunneling which uses public key authentication like SSH.</li>
                <li style="margin-bottom: 8px;"><strong style="color:var(--accent-blue);">Simplicity:</strong> One click away from connecting to the VPN.</li>
                <li style="margin-bottom: 8px;"><strong style="color:var(--accent-blue);">Security:</strong> Data remains private.</li>
            </ul>
        </div>

        <div class="interactive-card" style="background: var(--card-bg); padding: 2.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); border-top: 4px solid var(--accent-blue); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); cursor: default;">
            <h3 style="color: var(--text-light); margin-bottom: 1.2rem;">Architecture</h3>
            <h3 style="color: var(--text-light); margin-bottom: 1.2rem;">Architecture</h3>
            <p style="font-size: 0.95rem; margin-bottom: 1rem; color: var(--cnu-silver);">
                WireGuard operates with a "Crypto-Key Routing" design, to a simple connecting process:
            </p>
            <p style="font-size: 0.9rem; color: var(--text-light); margin-bottom: 1rem;">
                <strong>Hacking Reduction:</strong> It is more secure than OpenVPN or any other VPNs.
            </p>
            <p style="font-size: 0.9rem; color: var(--text-light);">
                <strong>Private Operation:</strong> The server protects the network from any hacking activities.
            </p>
        </div>
    </div>
<div style="margin-top: 4rem; text-align: center;">
        <div style="margin-bottom: 2rem;">
            <a href="https://www.wireguard.com/install/" target="_blank" class="btn-main" style="text-decoration: none; display: inline-flex; align-items: center; gap: 10px;">
                Download WireGuard Client
            </a>
        </div>
     </div>
     <style>
        .interactive-card:hover {
            transform: translateY(-10px);
            border-color: var(--accent-blue);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            background: #1a2234; 
        }
        
        .interactive-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(0, 86, 179, 0.6);
            filter: brightness(1.1);
        }

        .interactive-btn:active {
            transform: scale(0.98);
        }
    </style>

</div>
</div>
        

    <div id="about-tab" class="tab-content">
        <h2>How this setup works</h2>
        <p style="color:var(--cnu-silver); max-width: 640px; margin: 0.75rem auto 0;">Use your portal-issued profile with the official WireGuard app. Client labels may vary slightly by platform, but the flow is the same.</p>
        <div class="about-flow">
            <ol>
                <li><strong>Download your configuration file</strong> — Save the <code>.conf</code> file from this portal after you sign in (for example through <strong>Connect VPN</strong> or your assigned download).</li>
                <li><strong>Install the WireGuard client</strong> — Get the app for your OS from the <a href="https://www.wireguard.com/install/" target="_blank" rel="noopener noreferrer" style="color: var(--accent-blue);">official WireGuard installation page</a>.</li>
                <li><strong>Add a tunnel</strong> — Open WireGuard and choose <strong>Add tunnel</strong> (on some clients this appears as <strong>Import tunnel(s)</strong> from file).</li>
                <li><strong>Paste your configuration</strong> — In the tunnel editor, paste the full contents of your <code>.conf</code> file. If the client supports importing the file directly, you can use that instead of pasting.</li>
                <li><strong>Activate</strong> — Save if prompted, then click <strong>Activate</strong> (or toggle the tunnel on) to connect.</li>
            </ol>
        </div>
    </div>

    <div id="status-tab" class="tab-content">
        <?php if ($is_logged_in): ?>
            <h2>Network Health Monitor</h2>
            <div style="max-width: 600px; margin: 40px auto; background: var(--card-bg); border-radius: 15px; overflow: hidden; text-align: left;">
                <div style="display: flex; justify-content: space-between; padding: 20px; border-bottom: 1px solid #222;">
                    <span>Main Gateway (vpn.cnu.edu)</span>
                    <span style="color: var(--success); font-weight: bold;">● 99.9% UP</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 20px; border-bottom: 1px solid #222;">
                    <span>PCSE Engineering Subnet</span>
                    <span style="color: var(--success); font-weight: bold;">● ACTIVE</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 20px;">
                    <span>Duo Auth Cluster</span>
                    <span style="color: #ffbd2e; font-weight: bold;">● BUSY</span>
                </div>
            </div>
        <?php else: ?>
            <div class="restricted-overlay">
                <h2 style="color: var(--error);">Infrastructure Access Restricted</h2>
                <p>Live health monitoring is only available during active sessions.</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="security-tab" class="tab-content">
        <h2>Security Standards</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 40px;">
            <div style="background:var(--card-bg); padding: 30px; border-radius: 12px; border-left: 4px solid var(--accent-blue); text-align: left;">
                <h4 style="margin-bottom: 15px;">Encapsulation</h4>
                <p>Data is wrapped in UDP packets using the WireGuard protocol, ensuring your IP address is never exposed.</p>
            </div>
            <div style="background:var(--card-bg); padding: 30px; border-radius: 12px; border-left: 4px solid var(--accent-blue); text-align: left;">
                <h4 style="margin-bottom: 15px;">Compliance</h4>
                <p>This portal meets all University Data Privacy standards for remote academic research.</p>
            </div>
        </div>
    </div>

    <div id="loginModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 style="color:white;">CNU Central Auth</h3>
                <button onclick="toggleModal()" style="background:none; border:none; color:white; font-size:1.8rem; cursor:pointer;">&times;</button>
            </div>
            <div class="modal-body">
                <?php if ($login_error && $login_error_message !== ''): ?>
                    <div class="error-msg"><?php echo htmlspecialchars($login_error_message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="action" value="login">
                    <div class="input-group">
                        <label>Target Subnet</label>
                        <select name="subnet">
                            <option>Engineering (PCSE) Subnet</option>
                            <option>Business (Luter) Subnet</option>
                            <option>General Campus Access</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Student/Faculty ID</label>
                        <input type="text" name="student_id" placeholder="e.g. 00923451" required>
                    </div>
                    <div class="input-group">
                        <label>University Password</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn-main" style="width:100%; margin-top: 10px;">Request Tunnel Access</button>
                </form>
                <div style="margin-top: 25px; text-align: center; color: var(--cnu-silver); font-size: 0.8rem;">
                    Multi-factor push required after sign-in.
                </div>
            </div>
        </div>
    </div>

    <footer class="cnu-footer">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
            <div>
                <h3 style="color:var(--accent-blue);">Live Handshake Logs</h3>
                <p style="font-size: 0.8rem; color: var(--cnu-silver);">Real-time traffic visualization for tunnel diagnostic.</p>
            </div>
            <p style="font-size: 0.8rem; color: var(--cnu-silver);">ID: PCSE-VPN-NODE-01</p>
        </div>

        <div class="log-terminal" id="log-terminal">
            <div>> Initializing SecureSailing handshake...</div>
            <div>> Awaiting client request on UDP 51820...</div>
        </div>

        <div style="text-align: center; margin-top: 40px; border-top: 1px solid #222; padding-top: 20px; font-size: 0.8rem; color: var(--cnu-silver);">
            &copy; 2026 Christopher Newport University | SEC Capstone Project
        </div>
    </footer>

    <script>
        // Log Stream Data
        const possibleLogs = [
            "Handshake completed with peer [128.172.x.x]",
            "Data encapsulation: AES-256-GCM verified",
            "Packet integrity check: 100% success",
            "Duo Push sent to registered device...",
            "Routing traffic to Engineering VLAN 104",
            "Session keep-alive: 20ms RTT",
            "Rekeying internal tunnel ciphers...",
            "Protocol: WireGuard v1.0.2 established"
        ];

        setInterval(() => {
            const terminal = document.getElementById('log-terminal');
            if(!terminal) return;
            const entry = document.createElement('div');
            entry.innerText = "> " + possibleLogs[Math.floor(Math.random() * possibleLogs.length)];
            terminal.appendChild(entry);
            if (terminal.childNodes.length > 8) terminal.removeChild(terminal.firstChild);
        }, 3500);

        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active-tab'));
            const target = document.getElementById(tabName + '-tab');
            if(target) target.classList.add('active-tab');
            window.scrollTo(0, 0);
        }

        function toggleModal() {
            const modal = document.getElementById('loginModal');
            modal.style.display = (modal.style.display === 'flex') ? 'none' : 'flex';
        }

        function connectVPN() {
            window.location.href = "generate_vpn.php";
        }

        function runSpeedTest() {
            const ui = document.getElementById('speed-test-ui');
            const needle = document.getElementById('needle');
            const text = document.getElementById('speed-result');
            
            ui.style.display = 'block';
            text.innerText = "Pinging CNU Backbone...";
            needle.style.transform = 'rotate(-90deg)';

            setTimeout(() => {
                needle.style.transform = 'rotate(72deg)';
                text.innerText = "Optimized Path Found: 412 Mbps (Latency: 9ms)";
            }, 2000);
        }
    </script>
</body>
</html>