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


<body class="auth-body">


    <div class="auth-container">

        <div class="auth-card">


            <!-- HEADER -->

            <div class="auth-header">

                <div style="font-size: 42px; margin-bottom: 10px;">
                    ✈
                </div>

                <h1>Welcome back</h1>

                <p>
                    Login to continue planning your next amazing journey.
                </p>

            </div>


            <!-- ERROR / SUCCESS MESSAGE -->

            <?php if (!empty($message)): ?>

                <div class="message <?php echo $messageType; ?>">

                    <?php echo htmlspecialchars($message); ?>

                </div>

            <?php endif; ?>


            <!-- LOGIN FORM -->

            <form method="POST" action="login.php">


                <!-- EMAIL -->

                <div class="auth-form-group">

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

                <div class="auth-form-group">

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


                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="auth-submit-btn"
                >
                    Login →
                </button>


            </form>


            <!-- REGISTER LINK -->

            <div class="auth-footer">

                <p>
                    Don't have an account?

                    <a href="register.php">
                        Create an account
                    </a>
                </p>

            </div>


            <!-- BACK HOME -->

            <div class="auth-footer">

                <a href="index.php">
                    ← Back to Home
                </a>

            </div>


        </div>

    </div>


</body>

</html>