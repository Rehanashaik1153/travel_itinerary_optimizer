<?php
session_start();
require_once "db.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirm_password"];

    if (
        empty($username) ||
        empty($email) ||
        empty($password) ||
        empty($confirmPassword)
    ) {

        $message = "Please fill in all fields.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {

        $message = "Passwords do not match.";
        $messageType = "error";

    } elseif (strlen($password) < 6) {

        $message = "Password must contain at least 6 characters.";
        $messageType = "error";

    } else {

        $checkEmail = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ?"
        );

        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();

        $result = $checkEmail->get_result();

        if ($result->num_rows > 0) {

            $message = "This email is already registered.";
            $messageType = "error";

        } else {

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $insertUser = $conn->prepare(
                "INSERT INTO users (username, email, password)
                 VALUES (?, ?, ?)"
            );

            $insertUser->bind_param(
                "sss",
                $username,
                $email,
                $hashedPassword
            );

            if ($insertUser->execute()) {

                $message = "Registration successful! You can now log in.";
                $messageType = "success";

            } else {

                $message = "Something went wrong. Please try again.";
                $messageType = "error";
            }

            $insertUser->close();
        }

        $checkEmail->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | WanderAI</title>

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

            <span>Already have an account?</span>

            <a href="login.php" class="wander-auth-register-link">
                Login
            </a>

        </div>

    </header>



    <!-- =========================
         REGISTER SECTION
    ========================== -->

    <main class="wander-auth-main">


        <!-- Background Decoration -->

        <div class="wander-auth-shape wander-auth-shape-one"></div>
        <div class="wander-auth-shape wander-auth-shape-two"></div>


        <section class="wander-auth-card wander-register-card">


            <!-- Icon and Heading -->

            <div class="wander-auth-header">

                <div class="wander-auth-icon">
                    ✈
                </div>

                <h1>Create your account</h1>

                <p>
                    Start planning smarter and
                    travel better with WanderAI.
                </p>

            </div>



            <!-- Error / Success Message -->

            <?php if (!empty($message)): ?>

                <div class="wander-auth-message <?php echo $messageType; ?>">

                    <?php echo htmlspecialchars($message); ?>

                </div>

            <?php endif; ?>



            <!-- Registration Form -->

            <form
                method="POST"
                action="register.php"
                class="wander-auth-form"
            >


                <!-- Full Name -->

                <div class="wander-form-group">

                    <label for="username">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your full name"
                        required
                    >

                </div>



                <!-- Email -->

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



                <!-- Password -->

                <div class="wander-form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimum 6 characters"
                        required
                    >

                </div>



                <!-- Confirm Password -->

                <div class="wander-form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Re-enter your password"
                        required
                    >

                </div>



                <!-- Register Button -->

                <button
                    type="submit"
                    class="wander-auth-submit"
                >

                    <span>Create Account</span>

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



            <!-- Login Link -->

            <p class="wander-auth-bottom-text">

                Already have an account?

                <a href="login.php">
                    Login here
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