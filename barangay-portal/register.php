
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Registration | Barangay Chrysanthemum</title>
    <link rel="stylesheet" href="register.css">
</head>
<body>

    <main class="register-container">
        <h1>Create Resident Account</h1>
        <p>Register to report incidents and track your requests.</p>

        
<form action="" method="POST">

    <div class="form-group">
        <label for="first_name">First Name</label>
        <input type="text" id="first_name"
               name="first_name" required>
    </div>

    <div class="form-group">
        <label for="last_name">Last Name</label>
        <input type="text" id="last_name"
               name="last_name" required>
    </div>

    <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email"
               name="email" required>
    </div>

    <div class="form-group">
        <label for="contact_number">Contact Number</label>
        <input type="tel" id="contact_number"
               name="contact_number"
               placeholder="09XXXXXXXXX"
               pattern="09[0-9]{9}"
               maxlength="11" required>
    </div>

    <div class="form-group">
        <label for="address">Barangay Address</label>
        <input type="text" id="address"
               name="address"
               placeholder="House no., street, subdivision"
               required>
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password"
               name="password" minlength="8" required>
    </div>

    <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password"
               name="confirm_password"
               minlength="8" required>
    </div>

    <button type="submit">Create Account</button>

</form>

<p class="login-link">
    Already have an account?
    <a href="login.php">Login here</a>
</p>

    </main>


<script>
    const form = document.querySelector("form");

    form.addEventListener("submit", function(event) {
        const password = document.getElementById("password").value;
        const confirmPassword = document.getElementById("confirm_password").value;

        if (password !== confirmPassword) {
            event.preventDefault();
            alert("Passwords do not match. Please try again.");
        }
    });
</script>




</body>
</html>
