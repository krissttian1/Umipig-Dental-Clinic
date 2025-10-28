<?php
session_start();

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "clinic_db";

$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ✅ Check if both fields are submitted
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $input = trim($_POST['username']); // Email or Username
        $password = $_POST['password'];

        // ✅ Step 1: Try users table (admins and patients)
        $sql = filter_var($input, FILTER_VALIDATE_EMAIL)
            ? "SELECT * FROM users WHERE email = ?"
            : "SELECT * FROM users WHERE username = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row['password'])) {
                // ✅ Check if email is verified
                if ($row['email_verification'] == 0) {
                    $error = "⚠️ Please verify your email first.";
                } else {
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['role'] = $row['role'];

                    // ✅ Redirect based on user role
                    if ($row['role'] === 'admin') {
                        header("Location: admin_dashboard.php");
                    } else {
                        header("Location: home.php"); // patient
                    }
                    exit();
                }
            } else {
                $error = "❌ Incorrect password.";
            }

            $stmt->close();

        } else {
            // ✅ Step 2: Try clinic_staff table (admins only)
            $sql = filter_var($input, FILTER_VALIDATE_EMAIL)
                ? "SELECT * FROM clinic_staff WHERE email = ? AND staff_role = 'admin'"
                : "SELECT * FROM clinic_staff WHERE username = ? AND staff_role = 'admin'";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();

                if (password_verify($password, $row['password'])) {
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['user_id'] = $row['staff_id'];
                    $_SESSION['role'] = $row['staff_role'];

                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    $error = "❌ Incorrect password.";
                }

                $stmt->close();

            } else {
                // ✅ Step 3: Try dentists table (email only)
                if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    $sql = "SELECT * FROM dentists WHERE email = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $input);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result && $result->num_rows > 0) {
                        $dentist = $result->fetch_assoc();

                        if (password_verify($password, $dentist['password'])) {
                            // ✅ Dentist login success
                            $_SESSION['username'] = $dentist['name'];
                            $_SESSION['user_id'] = $dentist['Dentist_ID'];
                            $_SESSION['role'] = $dentist['role'] ?? 'dentist'; // fallback
                            $_SESSION['dentist_id'] = $dentist['Dentist_ID']; 

                            header("Location: dentist_appointment_module.php");
                            exit();
                        } else {
                            $error = "❌ Incorrect password.";
                        }

                        $stmt->close();
                    } else {
                        $error = "❌ Account does not exist.";
                    }
                } else {
                    $error = "❌ Invalid username or email.";
                }
            }
        }
    } else {
        $error = "❌ Please enter both username/email and password.";
    }
}
?>











<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-image: url('images/login-background.jpg');
            background-size: cover;
            background-position: center;
        }

        header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 20px;
    background-color: #ecf5ff;
    width: 100%;
    box-sizing: border-box;
    position: page;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.logo-circle {
    width: 40px;
    height: 40px;
    background-color: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

    .logo-circle img {
        width: 45px;
        height: 45px;
        object-fit: contain;
    }

.clinic-info h1 {
    font-size: 15px;
    text-align: left;
    color: #333;
    margin: 0 0 5px 0;
}

.clinic-info p {
    font-size: 10px;
    color: #555;
    margin: 0;
}

.main-nav {
    flex-grow: 1;
    display: flex;
    justify-content: center;
    gap: 50px;
}

    .main-nav a {
        text-decoration: none;
        color: #333;
        font-weight: 700;
        font-size: 12px;
        transition: color 0.3s;
    }

        .main-nav a:hover {
            color: #0066cc;
        }

.main-nav a.active {
    color: #0056b3;
    font-weight: bold;
}

.header-right {
    display: flex;
    gap: 20px;
    margin-right: 10px;
    align-items: center;
}

.auth-link {
    text-decoration: none;
    color: #0066cc;
    font-weight: 600;
    font-size: 12px;
    transition: color 0.3s;
}

    .auth-link:hover {
        color: #003d80;
    }

.header-right span {
    color: black;
    font-size: 10px;
}

.welcome-text {
    font-weight: 700;
    font-size: 12px;
    color: #003366;
}

.welcome-text .auth-link {
    font-weight: 600;
    color: #0066cc;
    text-decoration: none;
}

.welcome-text .auth-link:hover {
    color: #003d80;
}


        .login-container {
            width: 350px;
            padding: 5%;
            background: white;
            border-radius: 10px;
            margin: 100px auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        .login-container h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 25px;
        }
        .login-container input[type="submit"] {
            width: 100%;
            padding: 12px;
            border: none;
            background-color: #03a9f4;
            color: white;
            border-radius: 25px;
            cursor: pointer;
            margin-top: 10px;
        }
        .login-container input[type="submit"]:hover {
            background-color: #0288d1;
        }
        .login-container .options {
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
        }
        .login-container .options a {
            color: blue;
            text-decoration: none;
        }
        .login-container .options a:hover {
            text-decoration: underline;
        }
        .error-message {
            color: red;
            text-align: center;
            font-size: 14px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

    <header>

        <div class="logo-container">
            <div class="logo-circle">
                <img src="images/UmipigDentalClinic_Logo.jpg" alt="Umipig Dental Clinic">
            </div>

            <div class="clinic-info">
                <h1>Umipig Dental Clinic</h1>
                <p>General Dentist, Orthodontist, Oral Surgeon & Cosmetic Dentist</p>
            </div>

        </div>


        <nav class="main-nav">
            <a href="home.php">Home</a>
            <a href="aboutUs.php">About Us</a>            
            <a href="contactUs.php">Contact</a>
            <a href="services.php">Services</a>
        </nav>

<div class="header-right">
    <?php if (isset($_SESSION['username'])): ?>
        <span class="welcome-text">
            Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! &nbsp; | &nbsp;
            <a href="logout.php" class="auth-link">Logout</a>
        </span>
    <?php else: ?>
        <a href="register.php" class="auth-link">Register</a>
        <span>|</span>
        <a href="login.php" class="auth-link">Login</a>
    <?php endif; ?>
</div>

    </header>

<div class="login-container">
    <h2>LOG IN</h2>
    
    <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form action="login.php" method="POST">
        <input type="text" name="username" placeholder="Email/Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="submit" value="LOGIN">
    </form>
    <div class="options">
        <label><input type="checkbox"> Remember Me</label> | 
        <a href="#">Forgot Password?</a><br><br>
        Don't have an account? <a href="register.php">Sign Up here</a>
    </div>
</div>
</body>
</html>