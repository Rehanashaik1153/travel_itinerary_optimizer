<?php
session_start();
require_once "db.php";

$message = "";
$messageType = "";
$resetLink = "";

/*
   Create the reset-token table automatically.
   This does not change your existing users/trips structure.
*/
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } else {

        $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        /* Always give a generic success message in a real deployment. */
        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();
            $userId = (int)$user["user_id"];

            $conn->query("DELETE FROM password_resets WHERE user_id = " . $userId . " OR expires_at < NOW()");

            $token = bin2hex(random_bytes(32));
            $expiresAt = date("Y-m-d H:i:s", time() + 3600);

            $insert = $conn->prepare(
                "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)"
            );
            $insert->bind_param("iss", $userId, $token, $expiresAt);

            if ($insert->execute()) {
                $basePath = rtrim(dirname($_SERVER["PHP_SELF"]), "/\\");
                $resetLink = "http://" . $_SERVER["HTTP_HOST"] . $basePath . "/reset_password.php?token=" . urlencode($token);
                $message = "A password reset link has been created. On your local XAMPP setup, use the link shown below.";
                $messageType = "success";
            } else {
                $message = "Unable to create a reset request. Please try again.";
                $messageType = "error";
            }

            $insert->close();
        } else {
            $message = "If an account exists for this email, a password reset link will be provided.";
            $messageType = "success";
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
    <title>Forgot Password | WanderAI</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div style="font-size:42px; margin-bottom:10px;">🔐</div>
                <h1>Forgot your password?</h1>
                <p>Enter your registered email address and we'll help you reset your password.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($resetLink)): ?>
                <div style="margin:18px 0; padding:14px; border-radius:10px; background:#f1f5f9; word-break:break-all;">
                    <strong>Reset link:</strong><br>
                    <a href="<?php echo htmlspecialchars($resetLink); ?>"><?php echo htmlspecialchars($resetLink); ?></a>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php">
                <div class="auth-form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
                </div>

                <button type="submit" class="auth-submit-btn">Send Reset Link →</button>
            </form>

            <div class="auth-footer">
                <p><a href="login.php">← Back to Login</a></p>
            </div>

            <div class="auth-footer">
                <a href="index.php">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
