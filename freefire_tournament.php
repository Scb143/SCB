<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'freefire_tournament');

// Create a database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist
function initializeDatabase($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS participants (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        team_name VARCHAR(50) NOT NULL,
        leader_name VARCHAR(50) NOT NULL,
        leader_phone VARCHAR(15) NOT NULL,
        leader_email VARCHAR(50) NOT NULL,
        player2_name VARCHAR(50) NOT NULL,
        player3_name VARCHAR(50) NOT NULL,
        player4_name VARCHAR(50) NOT NULL,
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'confirmed') DEFAULT 'pending',
        payment_id VARCHAR(50),
        payment_status ENUM('pending', 'completed') DEFAULT 'pending'
    )";
    
    $sql2 = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(30) NOT NULL,
        password VARCHAR(255) NOT NULL
    )";
    
   // $conn->multi_query($sql . ";" . $sql2);
    
    // Initialize admin credentials if not exists
    $result = $conn->query("SELECT * FROM admin_users LIMIT 1");
    if ($result->num_rows == 0) {
        $admin_user = "admin";
        $admin_pass = password_hash("admin123", PASSWORD_DEFAULT);
        $conn->query("INSERT INTO admin_users (username, password) VALUES ('$admin_user', '$admin_pass')");
    }
}

initializeDatabase($conn);

// Handle admin login
session_start();
if (isset($_POST['admin_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            header("Location: freefire_tournament.php?section=admin");
            exit();
        }
    }
}

// Handle registration form submission
if (isset($_POST['register'])) {
    $team_name = $_POST['team_name'];
    $leader_name = $_POST['leader_name'];
    $leader_phone = $_POST['leader_phone'];
    $leader_email = $_POST['leader_email'];
    $player2_name = $_POST['player2_name'];
    $player3_name = $_POST['player3_name'];
    $player4_name = $_POST['player4_name'];
    
    $stmt = $conn->prepare("INSERT INTO participants (team_name, leader_name, leader_phone, leader_email, player2_name, player3_name, player4_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $team_name, $leader_name, $leader_phone, $leader_email, $player2_name, $player3_name, $player4_name);
    $stmt->execute();
    
    $last_id = $conn->insert_id;
    
    // Generate UPI payment link
    $upi_id = "9398189767@axl";
    $amount = "1"; // Tournament fee
    $note = "FreeFire Tournament Fee for Team: ".$team_name;
    $payment_link = "upi://pay?pa=".$upi_id."&am=".$amount."&tn=".urlencode($note)."&cu=INR";
    
    $_SESSION['payment_data'] = [
        'team_id' => $last_id,
        'team_name' => $team_name,
        'amount' => $amount,
        'upi_id' => $upi_id,
        'note' => $note,
        'link'=>$payment_link
    ];
    
    header("Location: freefire_tournament.php?payment=1");
    exit();
}

// Handle payment verification
if (isset($_POST['verify_payment'])) {
    $team_id = $_POST['team_id'];
    $transaction_id = $_POST['transaction_id'];
    
    // In a real scenario, you would verify the transaction with your payment gateway
    // For this example, we'll just update the database
    $stmt = $conn->prepare("UPDATE participants SET payment_status='completed', payment_id=?, status='confirmed' WHERE id=?");
    $stmt->bind_param("si", $transaction_id, $team_id);
    $stmt->execute();
    
    header("Location: freefire_tournament.php?payment_success=1");
    exit();
}

// Determine current section
$section = isset($_GET['section']) ? $_GET['section'] : 'home';
$show_payment = isset($_GET['payment']) && isset($_SESSION['payment_data']);
$payment_success = isset($_GET['payment_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Free Fire Tournament Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: #ff4d00;
            --secondary: #ffb300;
            --dark: #1a1a2e;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/507547ad-5835-4311-b90e-fa9730e6c78e.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: var(--light);
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }
        
        .hero-overlay {
            background: linear-gradient(135deg, rgba(255,77,0,0.7) 0%, rgba(255,179,0,0.7) 100%);
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 179, 0, 0.4);
        }
        
        .game-card {
            background: rgba(26, 26, 46, 0.8);
            border: 2px solid var(--primary);
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .game-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(255, 77, 0, 0.3);
        }
        
        .form-control {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(255, 77, 0, 0.25);
            color: white;
        }
        
        .nav-tabs .nav-link {
            color: white;
            border: none;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            background: transparent;
            border-bottom: 2px solid var(--primary);
        }
        
        .payment-modal {
            background: rgba(26, 26, 46, 0.95);
            border: 2px solid var(--primary);
            border-radius: 10px;
        }
        
        .upi-btn {
            background: #1f2937;
            border: 1px solid #4b5563;
            transition: all 0.3s;
        }
        
        .upi-btn:hover {
            background: #374151;
            border-color: var(--primary);
        }
        
        .admin-table th {
            background-color: var(--dark);
            color: var(--secondary);
        }
        
        .admin-table tr:nth-child(even) {
            background-color: rgba(26, 26, 46, 0.6);
        }
        
        .admin-table tr:nth-child(odd) {
            background-color: rgba(26, 26, 46, 0.4);
        }
        
        .admin-table tr:hover {
            background-color: rgba(255, 77, 0, 0.2);
        }
    </style>
</head>
<body>
    <?php if ($section === 'admin'): ?>
        <?php if (!isset($_SESSION['admin_logged_in'])): ?>
            <!-- Admin Login Form -->
            <div class="container mx-auto px-4 py-20 max-w-md">
                <div class="game-card p-8">
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-yellow-400 mb-2">Admin Panel</h2>
                        <p class="text-gray-300">Please login to access the dashboard</p>
                    </div>
                    <form method="post">
                        <div class="mb-4">
                            <label class="block text-gray-300 mb-2">Username</label>
                            <input type="text" name="username" class="form-control w-full p-2 rounded" required>
                        </div>
                        <div class="mb-6">
                            <label class="block text-gray-300 mb-2">Password</label>
                            <input type="password" name="password" class="form-control w-full p-2 rounded" required>
                        </div>
                        <button type="submit" name="admin_login" class="btn-primary w-full py-2 rounded-lg font-bold">Login</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Admin Dashboard -->
            <nav class="bg-gray-900 bg-opacity-80 px-4 py-3 flex justify-between items-center">
                <div class="flex items-center">
                    <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/fcd69d95-d134-496a-8bfa-71584fc924fe.png" alt="Free Fire tournament logo with flaming effect" class="mr-3">
                    <h1 class="text-xl font-bold text-yellow-400">Tournament Admin Panel</h1>
                </div>
                <div>
                    <a href="freefire_tournament.php" class="text-white hover:text-yellow-400 mr-4"><i class="fas fa-home mr-1"></i> View Site</a>
                    <a href="freefire_tournament.php?section=admin&logout=1" class="text-white hover:text-red-400"><i class="fas fa-sign-out-alt mr-1"></i> Logout</a>
                </div>
            </nav>
            
            <div class="container mx-auto px-4 py-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="game-card p-6 text-center">
                        <h3 class="text-lg font-semibold mb-2">Total Registrations</h3>
                        <p class="text-3xl font-bold text-yellow-400">
                            <?php 
                                $result = $conn->query("SELECT COUNT(*) as total FROM participants");
                                echo $result->fetch_assoc()['total'];
                            ?>
                        </p>
                    </div>
                    <div class="game-card p-6 text-center">
                        <h3 class="text-lg font-semibold mb-2">Confirmed Payments</h3>
                        <p class="text-3xl font-bold text-green-400">
                            <?php 
                                $result = $conn->query("SELECT COUNT(*) as total FROM participants WHERE payment_status='completed'");
                                echo $result->fetch_assoc()['total'];
                            ?>
                        </p>
                    </div>
                    <div class="game-card p-6 text-center">
                        <h3 class="text-lg font-semibold mb-2">Pending Payments</h3>
                        <p class="text-3xl font-bold text-red-400">
                            <?php 
                                $result = $conn->query("SELECT COUNT(*) as total FROM participants WHERE payment_status='pending'");
                                echo $result->fetch_assoc()['total'];
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="game-card p-6 mb-8">
                    <h2 class="text-2xl font-bold mb-4 text-yellow-400">Registered Teams</h2>
                    <div class="overflow-x-auto">
                        <table class="admin-table w-full">
                            <thead>
                                <tr>
                                    <th class="p-3 text-left">Team ID</th>
                                    <th class="p-3 text-left">Team Name</th>
                                    <th class="p-3 text-left">Leader</th>
                                    <th class="p-3 text-left">Phone</th>
                                    <th class="p-3 text-left">Status</th>
                                    <th class="p-3 text-left">Payment</th>
                                    <th class="p-3 text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $result = $conn->query("SELECT * FROM participants ORDER BY registration_date DESC");
                                    while ($row = $result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td class="p-3"><?php echo $row['id']; ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($row['team_name']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($row['leader_name']); ?></td>
                                    <td class="p-3"><?php echo htmlspecialchars($row['leader_phone']); ?></td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 rounded-full text-xs <?php echo $row['status'] == 'confirmed' ? 'bg-green-600' : 'bg-yellow-600'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 rounded-full text-xs <?php echo $row['payment_status'] == 'completed' ? 'bg-green-600' : 'bg-yellow-600'; ?>">
                                            <?php echo $row['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td class="p-3"><?php echo date('d M Y', strtotime($row['registration_date'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <?php
                if (isset($_GET['logout'])) {
                    session_destroy();
                    header("Location: freefire_tournament.php?section=admin");
                    exit();
                }
            ?>
        <?php endif; ?>
    
    <?php elseif ($show_payment): ?>
        <!-- Payment Section -->
        <div class="container mx-auto px-4 py-20 max-w-md">
            <div class="payment-modal p-8">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-yellow-400">Complete Your Payment</h2>
                    <p class="text-gray-300 mt-2">Tournament Fee: ₹499</p>
                </div>
                
                <div class="mb-6 p-4 bg-gray-800 rounded-lg">
                    <h3 class="text-lg font-semibold text-yellow-400 mb-2">Team Details</h3>
                    <p class="text-gray-300">Team Name: <?php echo htmlspecialchars($_SESSION['payment_data']['team_name']); ?></p>
                    <p class="text-gray-300">Team ID: FF-<?php echo $_SESSION['payment_data']['team_id']; ?></p>
                </div>
                
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-yellow-400 mb-3">Pay via UPI</h3>
                    <button onclick="window.location.href='<?php echo $_SESSION['payment_data']['link']; ?>'" class="upi-btn w-full py-3 rounded-lg mb-3 flex items-center justify-center">
                        <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/41f40de7-1a51-4b5b-b63e-38143e8db9e9.png" alt="UPI payment icons" class="mr-2">
                        <span class="font-medium">Click to Pay via UPI</span>
                    </button>
                    
                    <div class="text-center my-4 text-gray-400">-- OR --</div>
                    
                    <div class="text-center mb-4">
                        <img src="images/upi_qr.png" alt="QR Code for UPI payment" class="mx-auto mb-2">
                        <p class="text-gray-300 text-sm">Scan this QR code with any UPI app</p>
                    </div>
                </div>
                
                <form method="post" class="mt-4">
                    <input type="hidden" name="team_id" value="<?php echo $_SESSION['payment_data']['team_id']; ?>">
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2">Transaction ID (After Payment)</label>
                        <input type="text" name="transaction_id" class="form-control w-full p-2 rounded" required placeholder="Enter UPI Transaction ID">
                    </div>
                    <button type="submit" name="verify_payment" class="btn-primary w-full py-2 rounded-lg font-bold">Verify Payment</button>
                </form>
                
                <div class="text-center mt-4">
                    <a href="freefire_tournament.php" class="text-yellow-400 hover:underline">Cancel and return to tournament page</a>
                </div>
            </div>
        </div>
    
    <?php elseif ($payment_success): ?>
        <!-- Payment Success Section -->
        <div class="container mx-auto px-4 py-20 max-w-md">
            <div class="game-card p-8 text-center">
                <div class="text-5xl mb-4 text-green-500">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="text-2xl font-bold mb-2">Payment Successful!</h2>
                <p class="text-gray-300 mb-6">Your tournament registration is now complete.</p>
                <div class="bg-gray-800 rounded-lg p-4 mb-6">
                    <p class="text-yellow-400 font-semibold">Team ID: FF-<?php echo $_SESSION['payment_data']['team_id']; ?></p>
                    <p class="text-gray-300">Keep this ID for future reference.</p>
                </div>
                <a href="freefire_tournament.php" class="btn-primary inline-block px-6 py-2 rounded-lg font-bold">Return to Home</a>
            </div>
        </div>
        
        <?php unset($_SESSION['payment_data']); ?>
    
    <?php else: ?>
        <!-- Main Tournament Landing Page -->
        <nav class="bg-gray-900 bg-opacity-80 px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/f1724dff-5ced-4b08-82ae-d9aa38cb6127.png" alt="Free Fire tournament logo with flaming effect" class="mr-3">
                <h1 class="text-xl font-bold text-yellow-400">Free Fire Tournament</h1>
            </div>
            <div>
                <a href="freefire_tournament.php?section=admin" class="text-white hover:text-yellow-400"><i class="fas fa-user-shield mr-1"></i> Admin Login</a>
            </div>
        </nav>
        
        <main>
            <!-- Hero Section -->
            <section class="hero-overlay py-20 px-4">
                <div class="container mx-auto text-center">
                    <h1 class="text-4xl md:text-6xl font-bold mb-4 text-white drop-shadow-lg">FREE FIRE CHAMPIONSHIP</h1>
                    <p class="text-xl md:text-2xl mb-8 text-yellow-300">Prove your skills. Win amazing prizes.</p>
                    
                    <div class="max-w-3xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                        <div class="game-card p-6">
                            <div class="text-3xl text-yellow-400 mb-3">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-2">₹50,000 Prize</h3>
                            <p class="text-gray-300">Total prize pool for winners</p>
                        </div>
                        <div class="game-card p-6">
                            <div class="text-3xl text-yellow-400 mb-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-2">4v4 Squad</h3>
                            <p class="text-gray-300">Battle Royale format</p>
                        </div>
                        <div class="game-card p-6">
                            <div class="text-3xl text-yellow-400 mb-3">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-2">15th July 2023</h3>
                            <p class="text-gray-300">Tournament date</p>
                        </div>
                    </div>
                    
                    <a href="#register" class="btn-primary inline-block px-8 py-3 text-lg rounded-full font-bold animate-bounce">
                        Register Now ₹499/team
                    </a>
                </div>
            </section>
            
            <!-- About Section -->
            <section class="py-16 px-4 bg-gray-900 bg-opacity-70">
                <div class="container mx-auto">
                    <h2 class="text-3xl font-bold text-center mb-12 text-yellow-400">TOURNAMENT DETAILS</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                        <div>
                            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/d4c9d5d0-e190-40b9-95d6-2ee4777ddb23.png" alt="Team of Free Fire players competing in a tournament setting with intense gameplay" class="rounded-lg shadow-lg w-full">
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold mb-4 text-yellow-400">Game Format</h3>
                            <ul class="list-disc pl-5 text-gray-300 space-y-2">
                                <li>4-player squad format</li>
                                <li>Custom room Battle Royale matches</li>
                                <li>3 matches per stage</li>
                                <li>Points based on kills and placement</li>
                                <li>Top teams qualify for finals</li>
                                <li>Final match decides the champion</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="order-2 md:order-1">
                            <h3 class="text-2xl font-bold mb-4 text-yellow-400">Rules</h3>
                            <ul class="list-disc pl-5 text-gray-300 space-y-2">
                                <li>No cheating or hacking</li>
                                <li>No account sharing</li>
                                <li>Players must be present for all matches</li>
                                <li>Decisions by organizers are final</li>
                                <li>Payment must be completed before tournament</li>
                                <li>Teams must register with valid details</li>
                            </ul>
                        </div>
                        <div class="order-1 md:order-2">
                            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/a5256f96-f3dd-40a0-b906-4394d545b014.png" alt="List of tournament rules displayed on a game-themed background" class="rounded-lg shadow-lg w-full">
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Registration Form -->
            <section id="register" class="py-16 px-4 bg-gray-900 bg-opacity-80">
                <div class="container mx-auto max-w-4xl">
                    <div class="game-card p-8">
                        <h2 class="text-3xl font-bold text-center mb-8 text-yellow-400">TEAM REGISTRATION</h2>
                        
                        <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Team Information -->
                            <div class="md:col-span-2">
                                <h3 class="text-xl font-semibold mb-4 text-yellow-400 border-b pb-2">Team Information</h3>
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2">Team Name</label>
                                <input type="text" name="team_name" class="form-control w-full p-2 rounded" required>
                            </div>
                            
                            <!-- Team Leader -->
                            <div class="md:col-span-2 mt-4">
                                <h3 class="text-xl font-semibold mb-4 text-yellow-400 border-b pb-2">Team Leader (Player 1)</h3>
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2">Full Name</label>
                                <input type="text" name="leader_name" class="form-control w-full p-2 rounded" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2">Phone Number</label>
                                <input type="tel" name="leader_phone" class="form-control w-full p-2 rounded" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2">Email Address</label>
                                <input type="email" name="leader_email" class="form-control w-full p-2 rounded" required>
                            </div>
                            
                            <!-- Team Members -->
                            <div class="md:col-span-2 mt-4">
                                <h3 class="text-xl font-semibold mb-4 text-yellow-400 border-b pb-2">Team Members</h3>
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2">Player 2 Name</label>
                                <input type="text" name="player2_name" class="form-control w-full p-2 rounded" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2">Player 3 Name</label>
                                <input type="text" name="player3_name" class="form-control w-full p-2 rounded" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2">Player 4 Name</label>
                                <input type="text" name="player4_name" class="form-control w-full p-2 rounded" required>
                            </div>
                            
                            <div class="md:col-span-2 mt-6">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" required class="focus:ring-yellow-500 h-4 w-4 text-yellow-500 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label class="text-gray-300">
                                            I agree to the tournament rules and confirm that all information provided is accurate.
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:col-span-2 mt-2">
                                <button type="submit" name="register" class="btn-primary w-full py-3 rounded-lg font-bold text-lg">
                                    Register Team (₹499)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
            <!-- Sponsors Section -->
            <section class="py-12 px-4 bg-gray-900 bg-opacity-90">
                <div class="container mx-auto">
                    <h2 class="text-2xl font-bold text-center mb-8 text-yellow-400">OUR SPONSORS</h2>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 items-center">
                        <div class="text-center">
                            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/5ef4ecb6-3ef9-4df3-8523-d2829ff72545.png" alt="Gaming hardware sponsor logo" class="mx-auto">
                        </div>
                        <div class="text-center">
                            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/3333ecdd-cd94-4483-ac3f-effef2540a52.png" alt="Energy drink sponsor logo" class="mx-auto">
                        </div>
                        <div class="text-center">
                            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/c5425a13-37bc-47fc-875b-93266b637713.png" alt="E-sports organization logo" class="mx-auto">
                        </div>
                        <div class="text-center">
                            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/765b6baf-30af-4849-9b50-7ca214d6abb1.png" alt="Tech company sponsor logo" class="mx-auto">
                        </div>
                    </div>
                </div>
            </section>
        </main>
        
        <!-- Footer -->
        <footer class="bg-gray-900 bg-opacity-90 py-8 px-4">
            <div class="container mx-auto text-center">
                <div class="flex justify-center space-x-6 mb-6">
                    <a href="#" class="text-gray-400 hover:text-yellow-400"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-gray-400 hover:text-yellow-400"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-gray-400 hover:text-yellow-400"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-gray-400 hover:text-yellow-400"><i class="fab fa-discord"></i></a>
                </div>
                
                <p class="text-gray-400">© 2023 Free Fire Tournament. All rights reserved.</p>
                <p class="text-gray-500 text-sm mt-2">This tournament is not affiliated with Garena Free Fire.</p>
            </div>
        </footer>
    <?php endif; ?>

    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    let valid = true;
                    const inputs = form.querySelectorAll('[required]');
                    
                    inputs.forEach(input => {
                        if (!input.value.trim()) {
                            input.style.borderColor = 'red';
                            valid = false;
                        } else {
                            input.style.borderColor = '';
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            });
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
        
        // Payment handler
        function handleUPIClick(upiId, amount, note) {
            // In a real implementation, this would trigger native UPI intent
            console.log(`Payment initiated: UPI ID=${upiId}, Amount=${amount}, Note=${note}`);
            return false;
        }
    </script>
</body>
</html>

