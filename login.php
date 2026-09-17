<?php
session_start();
require_once "db.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {

        $message = "Please enter your email and password.";
        $messageType = "error";

    } else {

        $stmt = $conn->prepare(
            "SELECT user_id, username, email, password
             FROM users
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password"])) {

                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["email"] = $user["email"];

                header("Location: dashboard.php");
                exit();

            } else {

                $message = "Incorrect password.";
                $messageType = "error";
            }

        } else {

            $message = "No account found with this email.";
            $messageType = "error";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | WanderAI</title>

    <link rel="stylesheet" href="style.css">

</head>


<body class="wander-auth-page">


    <!-- =========================
         AUTH NAVBAR
    ========================== -->

    <header class="wander-auth-navbar">

        <a href="index.php" class="wander-auth-logo">

            <span class="wander-auth-logo-icon">✈</span>

            <span class="wander-auth-logo-text">
                Wander<span>AI</span>
            </span>

        </a>


        <div class="wander-auth-nav-right">

            <span>Don't have an account?</span>

            <a href="register.php" class="wander-auth-register-link">
                Create account
            </a>

        </div>

    </header>



    <!-- =========================
         LOGIN SECTION
    ========================== -->

    <main class="wander-auth-main">


        <!-- Background Decoration -->

        <div class="wander-auth-shape wander-auth-shape-one"></div>
        <div class="wander-auth-shape wander-auth-shape-two"></div>


        <section class="wander-auth-card">


            <!-- Icon and Heading -->

            <div class="wander-auth-header">

                <div class="wander-auth-icon">
                    ✈
                </div>

                <h1>Welcome back</h1>

                <p>
                    Login to continue planning your
                    next amazing journey.
                </p>

            </div>



            <!-- Error / Success Message -->

            <?php if (!empty($message)): ?>

                <div class="wander-auth-message <?php echo $messageType; ?>">

                    <?php echo htmlspecialchars($message); ?>

                </div>

            <?php endif; ?>



            <!-- LOGIN FORM -->

            <form
                method="POST"
                action="login.php"
                class="wander-auth-form"
            >


                <!-- EMAIL -->

                <div class="wander-form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        required
                    >

                </div>


                <!-- PASSWORD -->

                <div class="wander-form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >

                </div>


                <!-- FORGOT PASSWORD -->

                <div class="wander-auth-forgot">
                    <a href="forgot_password.php">
                        Forgot password?
                    </a>
                </div>


                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="wander-auth-submit"
                >

                    <span>Login</span>

                    <span class="wander-button-arrow">
                        →
                    </span>

                </button>


            </form>



            <!-- Divider -->

            <div class="wander-auth-divider">

                <span></span>

                <p>OR</p>

                <span></span>

            </div>



            <!-- Register Link -->

            <p class="wander-auth-bottom-text">

                Don't have an account?

                <a href="register.php">
                    Create one here
                </a>

            </p>



            <!-- Back Home -->

            <a href="index.php" class="wander-back-home">

                <span>←</span>
                Back to Home

            </a>


        </section>

    </main>


</body>

</html>