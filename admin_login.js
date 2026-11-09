const adminForm = document.getElementById("admin-login-form");
const loginMessage = document.getElementById("login-message");
const loginButton = document.getElementById("login-button");

adminForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    loginMessage.textContent = "";
    loginButton.disabled = true;

    try {
        const response = await fetch("api/admin_login.php", {
            method: "POST",
            body: new FormData(adminForm),
            credentials: "same-origin"
        });

        const result = await response.json();

        if (!response.ok) {
            loginMessage.textContent = result.message;
            return;
        }

        window.location.href = "admin_dashboard.php";

    } catch (error) {
        console.error(error);
        loginMessage.textContent =
            "Unable to connect to the login server.";
    } finally {
        loginButton.disabled = false;
    }
});
