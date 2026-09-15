<?php
session_start();
require_once "db.php";

$message = "";
$messageType = "";
$validToken = false;
$token = trim($_GET["token"] ?? $_POST["token"] ?? "");

if ($token !== "") {
    $stmt = $conn->prepare(
        "SELECT reset_id, user_id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $reset = $result->fetch_assoc();
    $stmt->close();

    if ($reset) {
        $validToken = true;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $validToken) {

    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (strlen($password) < 6) {
        $message = "Password must contain at least 6 characters.";
        $messageType = "error";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } else {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $update->bind_param("si", $hashedPassword, $reset["user_id"]);

        if ($update->execute()) {
            $delete = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $delete->bind_param("i", $reset["user_id"]);
            $delete->execute();
            $delete->close();

            $message = "Password reset successfully. You can now log in with your new password.";
            $messageType = "success";
            $validToken = false;
        } else {
            $message = "Unable to reset your password. Please try again.";
            $messageType = "error";
        }

        $update->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | WanderAI</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div style="font-size:42px; margin-bottom:10px;">🔑</div>
                <h1>Reset password</h1>
                <p>Create a new password for your WanderAI account.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <form method="POST" action="reset_password.php">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="auth-form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="Minimum 6 characters" required>
                    </div>

                    <div class="auth-form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Enter the password again" required>
                    </div>

                    <button type="submit" class="auth-submit-btn">Reset Password →</button>
                </form>
            <?php elseif ($messageType !== "success"): ?>
                <div class="auth-footer">
                    <p>This password reset link is invalid or has expired.</p>
                    <a href="forgot_password.php">Request a new reset link</a>
                </div>
            <?php endif; ?>

            <div class="auth-footer">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
