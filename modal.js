
/* =========================================
   BARANGAY CHRYSANTHEMUM PORTAL
   LOGIN AND REGISTRATION MODALS
========================================= */

const loginModal = document.getElementById("login-modal");
const registerModal = document.getElementById("register-modal");

const openLogin = document.getElementById("open-login");
const switchToRegister = document.getElementById("switch-to-register");
const switchToLogin = document.getElementById("switch-to-login");

let previousFocus = null;
let redirectAfterLogin = null;

/* =========================================
   OPEN MODAL
========================================= */

function openModal(modal) {

    if (loginModal.hidden && registerModal.hidden) {
        previousFocus = document.activeElement;
    }

    loginModal.hidden = true;
    registerModal.hidden = true;

    modal.hidden = false;

    modal.querySelector(".modal-content").scrollTop = 0;

    document.body.classList.add("modal-open");

    modal.querySelector(".close-modal").focus();
}

/* =========================================
   CLOSE MODALS
========================================= */

function closeModals() {

    if (loginModal.hidden && registerModal.hidden) {
        return;
    }

    loginModal.hidden = true;
    registerModal.hidden = true;

    document.body.classList.remove("modal-open");

    previousFocus?.focus();
}

/* =========================================
   MODAL NAVIGATION
========================================= */

openLogin.addEventListener("click", function(event) {

    event.preventDefault();

    openModal(loginModal);
});

switchToRegister.addEventListener("click", function(event) {

    event.preventDefault();

    openModal(registerModal);
});

switchToLogin.addEventListener("click", function(event) {

    event.preventDefault();

    openModal(loginModal);
});

/* =========================================
   CLOSE BUTTONS
========================================= */

document.querySelectorAll(".close-modal").forEach(function(button) {

    button.addEventListener("click", closeModals);
});

/* =========================================
   CLOSE MODAL WHEN CLICKING BACKDROP
========================================= */

document.querySelectorAll(".modal").forEach(function(modal) {

    modal.addEventListener("click", function(event) {

        if (event.target === modal) {
            closeModals();
        }
    });
});

/* =========================================
   KEYBOARD ACCESSIBILITY
========================================= */

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closeModals();
    }

    const modal = [loginModal, registerModal].find(
        item => !item.hidden
    );

    if (event.key === "Tab" && modal) {

        const controls = modal.querySelectorAll(
            "button, input, a[href]"
        );

        const first = controls[0];
        const last = controls[controls.length - 1];

        if (event.shiftKey && document.activeElement === first) {

            event.preventDefault();
            last.focus();

        } else if (
            !event.shiftKey &&
            document.activeElement === last
        ) {

            event.preventDefault();
            first.focus();
        }
    }
});

/* =========================================
   FORM ELEMENTS
========================================= */

const registerForm = document.getElementById("register-form");
const loginForm = document.getElementById("login-form");

/* =========================================
   RESIDENT REGISTRATION
========================================= */

registerForm.addEventListener("submit", async function(event) {

    event.preventDefault();

    const password = document.getElementById(
        "reg-password"
    ).value;

    const confirmPassword = document.getElementById(
        "reg-confirm-password"
    ).value;

    if (password !== confirmPassword) {

        alert("Passwords do not match.");
        return;
    }

    const formData = new FormData(registerForm);

    try {

        // Request CSRF token from the server.
        const tokenResponse = await fetch(
            "api/registration_csrf.php",
            {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        if (!tokenResponse.ok) {
            throw new Error(
                "Unable to retrieve security token."
            );
        }

        const tokenData = await tokenResponse.json();

        if (typeof tokenData.csrf_token !== "string") {
            throw new Error(
                "Invalid registration security token response."
            );
        }

        // Include CSRF token in registration data.
        formData.set(
            "csrf_token",
            tokenData.csrf_token
        );

        // Submit registration request.
        const response = await fetch(
            "api/register.php",
            {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            }
        );

        const result = await response.json();

        alert(result.message);

        if (response.ok) {

            registerForm.reset();

            openModal(loginModal);
        }

    } catch (error) {

        console.error(error);

        alert(
            "Unable to connect to the registration server."
        );
    }
});

/* =========================================
   RESIDENT LOGIN
========================================= */

loginForm.addEventListener("submit", async function(event) {

    event.preventDefault();

    const formData = new FormData(loginForm);

    try {

        // Request login CSRF token.
        const tokenResponse = await fetch(
            "api/login_csrf.php",
            {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        if (!tokenResponse.ok) {
            throw new Error(
                "Unable to retrieve login security token."
            );
        }

        const tokenData = await tokenResponse.json();

        if (typeof tokenData.csrf_token !== "string") {
            throw new Error(
                "Invalid login security token response."
            );
        }

        formData.set(
            "csrf_token",
            tokenData.csrf_token
        );

        // Submit login request.
        const response = await fetch(
            "api/login.php",
            {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            }
        );

        const result = await response.json();

        if (!response.ok) {

            alert(result.message);
            return;
        }

        loginForm.reset();

        closeModals();
// Update the navigation after successful login.
        await updateAccountNavigation();

        /* =====================================
           REDIRECT AFTER LOGIN
        ===================================== */

        if (redirectAfterLogin) {

            const destination = redirectAfterLogin;

            redirectAfterLogin = null;

            const sessionResponse = await fetch(
                "api/session.php",
                {
                    credentials: "same-origin",
                    cache: "no-store"
                }
            );

            if (!sessionResponse.ok) {
                throw new Error(
                    "Unable to verify login session"
                );
            }

            const sessionData = await sessionResponse.json();

            if (
                sessionData.authenticated &&
                sessionData.user.role === "resident"
            ) {

                window.location.href = destination;
            }
        }

    } catch (error) {

        console.error(error);

        alert(
            "Unable to connect to the login server."
        );
    }
});

/* =========================================
   ACCOUNT NAVIGATION
========================================= */

const accountName = document.getElementById("account-name");
const logoutButton = document.getElementById("logout-button");

/* =========================================
   UPDATE ACCOUNT NAVIGATION
========================================= */

async function updateAccountNavigation() {

    try {

        const response = await fetch(
            "api/session.php",
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        if (!response.ok) {
            throw new Error("Session check failed");
        }

        const data = await response.json();

        if (data.authenticated) {

            openLogin.hidden = true;

            accountName.hidden = false;
            logoutButton.hidden = false;

            accountName.textContent =
                "Hello, " + data.user.first_name;

            /* =====================================
               RESIDENT DASHBOARD LINK
            ===================================== */

            if (data.user.role === "resident") {

                accountName.href = "resident_dashboard.php";

                accountName.title =
                    "Go to Resident Dashboard";

                accountName.classList.add(
                    "resident-dashboard-link"
                );

            } else {

                accountName.removeAttribute("href");
                accountName.removeAttribute("title");

                accountName.classList.remove(
                    "resident-dashboard-link"
                );
            }

        } else {

            openLogin.hidden = false;

            accountName.hidden = true;
            logoutButton.hidden = true;

            accountName.textContent = "";

            accountName.removeAttribute("href");
            accountName.removeAttribute("title");

            accountName.classList.remove(
                "resident-dashboard-link"
            );
        }

    } catch (error) {

        console.error(error);
    }
}

/* =========================================
   LOGOUT
========================================= */

logoutButton.addEventListener("click", async function() {

    try {

        const response = await fetch(
            "api/logout.php",
            {
                method: "POST",
                credentials: "same-origin"
            }
        );

        if (!response.ok) {
            throw new Error("Logout failed");
        }

        await updateAccountNavigation();

    } catch (error) {

        console.error(error);

        alert(
            "Unable to log out. Please try again."
        );
    }
});

/* =========================================
   INITIAL SESSION CHECK
========================================= */

updateAccountNavigation();

/* =========================================
   TRACK MY REPORT BUTTON
========================================= */

const trackMyReport = document.getElementById(
    "track-my-report"
);

trackMyReport.addEventListener("click", async function(event) {

    event.preventDefault();

    try {

        const response = await fetch(
            "api/session.php",
            {
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        if (!response.ok) {
            throw new Error("Session check failed");
        }

        const data = await response.json();

        if (
            data.authenticated &&
            data.user.role === "resident"
        ) {

            window.location.href = "my_incidents.php";

        } else {

            redirectAfterLogin = "my_incidents.php";

            openModal(loginModal);
        }

    } catch (error) {

        console.error(error);

        alert(
            "Unable to verify your session. Please try again."
        );
    }
});

/* =========================================
   REPORT AN INCIDENT BUTTON
========================================= */

const reportIncident = document.getElementById(
    "report-incident"
);

if (reportIncident) {

    reportIncident.addEventListener(
        "click",
        async function(event) {

            event.preventDefault();

            try {

                const response = await fetch(
                    "api/session.php",
                    {
                        credentials: "same-origin",
                        cache: "no-store"
                    }
                );

                if (!response.ok) {
                    throw new Error("Session check failed");
                }

                const data = await response.json();

                if (
                    data.authenticated &&
                    data.user.role === "resident"
                ) {

                    window.location.href =
                        "report_incident.php";

                } else {

                    redirectAfterLogin =
                        "report_incident.php";

                    openModal(loginModal);
                }

            } catch (error) {

                console.error(error);

                alert(
                    "Unable to verify your session. Please try again."
                );
            }
        }
    );
}

/* =========================================
   PORTAL FEATURE CARDS
========================================= */

const residentFeatureCards = document.querySelectorAll(
    "[data-resident-destination]"
);

residentFeatureCards.forEach(function(card) {

    card.addEventListener(
        "click",
        async function(event) {

            event.preventDefault();

            const destination =
                card.dataset.residentDestination;

            // Only allow known resident pages.
            const allowedDestinations = [
                "report_incident.php",
                "my_incidents.php"
            ];

            if (!allowedDestinations.includes(destination)) {
                return;
            }

            try {

                const response = await fetch(
                    "api/session.php",
                    {
                        credentials: "same-origin",
                        cache: "no-store"
                    }
                );

                if (!response.ok) {
                    throw new Error(
                        "Session check failed"
                    );
                }

                const data = await response.json();

                if (
                    data.authenticated &&
                    data.user.role === "resident"
                ) {

                    window.location.href = destination;

                } else {

                    redirectAfterLogin = destination;

                    openModal(loginModal);
                }

            } catch (error) {

                console.error(error);

                alert(
                    "Unable to verify your session. Please try again."
                );
            }
        }
    );
});

/* =========================================
   SHOW / HIDE PASSWORD
========================================= */

document.querySelectorAll(
    ".modal-password-toggle"
).forEach(function(button) {

    button.addEventListener("click", function() {

        const targetId =
            button.dataset.passwordTarget;

        const passwordInput =
            document.getElementById(targetId);

        if (!passwordInput) {
            return;
        }

        const isHidden =
            passwordInput.type === "password";

        passwordInput.type =
            isHidden ? "text" : "password";

        button.textContent =
            isHidden ? "Hide" : "Show";

        button.setAttribute(
            "aria-label",
            isHidden
                ? "Hide password"
                : "Show password"
        );
    });
});
