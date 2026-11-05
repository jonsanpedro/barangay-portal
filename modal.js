
const loginModal = document.getElementById("login-modal");
const registerModal = document.getElementById("register-modal");

const openLogin = document.getElementById("open-login");
const switchToRegister = document.getElementById("switch-to-register");
const switchToLogin = document.getElementById("switch-to-login");

let previousFocus = null;

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

function closeModals() {
    if (loginModal.hidden && registerModal.hidden) return;
    loginModal.hidden = true;
    registerModal.hidden = true;

    document.body.classList.remove("modal-open");
    previousFocus?.focus();
}

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

document.querySelectorAll(".close-modal").forEach(function(button) {
    button.addEventListener("click", closeModals);
});

document.querySelectorAll(".modal").forEach(function(modal) {
    modal.addEventListener("click", function(event) {
        if (event.target === modal) {
            closeModals();
        }
    });
});

document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        closeModals();
    }
    const modal = [loginModal, registerModal].find(item => !item.hidden);
    if (event.key === "Tab" && modal) {
        const controls = modal.querySelectorAll("button, input, a[href]");
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
});

const registerForm = document.getElementById("register-form");
const loginForm = document.getElementById("login-form");

registerForm.addEventListener("submit", function(event) {
    event.preventDefault();

    const password = document.getElementById("reg-password").value;
    const confirmPassword = document.getElementById("reg-confirm-password").value;

    if (password !== confirmPassword) {
        alert("Passwords do not match. Please try again.");
        return;
    }

    alert("Form validation passed. Database registration is not yet connected.");
});

loginForm.addEventListener("submit", function(event) {
    event.preventDefault();

    alert("Login authentication is not yet connected.");
});
