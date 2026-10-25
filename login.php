
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Login | Barangay Chrysanthemum</title>
    <link rel="stylesheet" href="login.css">

</head>
<body>

    <main class="login-container">
        <h1>Resident Login</h1>
        <p>Sign in to report incidents and track your requests.</p>

        
    <form action="" method="POST">

        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                required
            >
        </div>

        <button type="submit">Login</button>

    </form>

        <p class="register-link">
            Don't have an account?
        <a href="register.php">Register here</a>
        </p>



    </main>

</body>
</html>
