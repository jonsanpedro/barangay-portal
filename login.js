
"use strict";

document.addEventListener("DOMContentLoaded", function () {

    const loginForm = document.getElementById("login-form");
    const loginButton = document.getElementById("login-button");
    const loginMessage = document.getElementById("login-message");

    if (!loginForm || !loginButton || !loginMessage) {
        console.error("Login form elements were not found.");
        return;
    }

    loginForm.addEventListener("submit", async function (event) {

        event.preventDefault();

        loginButton.disabled = true;
        loginButton.textContent = "Logging in...";
        loginMessage.textContent = "";

        try {

            const response = await fetch("api/login.php", {
                method: "POST",
                credentials: "same-origin",
                body: new FormData(loginForm),
                headers: {
                    "Accept": "application/json"
                }
            });

            const result = await response.json();

            if (!response.ok) {
                loginMessage.textContent =
                    result.message || "Login failed. Please try again.";
                return;
            }

            if (result.user && result.user.role === "resident") {
                window.location.replace("report_incident.php");
                return;
            }

            loginMessage.textContent =
                "Your account does not have resident access.";

        } catch (error) {

            console.error("Login error:", error);

            loginMessage.textContent =
                "Unable to connect to the server. Please try again.";

        } finally {

            loginButton.disabled = false;
            loginButton.textContent = "Login";

        }

    });

});
