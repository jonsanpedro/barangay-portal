
(function () {
    "use strict";

    function initializeFAQChatbot() {
        const toggle = document.getElementById("faq-chat-toggle");
        const close = document.getElementById("faq-chat-close");
        const windowEl = document.getElementById("faq-chat-window");
        const messages = document.getElementById("faq-chat-messages");
        const form = document.getElementById("faq-chat-form");
        const input = document.getElementById("faq-chat-input");

        if (!toggle || !close || !windowEl ||
            !messages || !form || !input) {
            return;
        }

        // Prevent duplicate initialization.
        if (toggle.dataset.initialized === "true") {
            return;
        }

        toggle.dataset.initialized = "true";

        const answers = {
            report:
                "To report an incident, log in to your resident " +
                "account and select Report Incident. Provide the " +
                "incident details, location, and photo if available.",

            track:
                "To track your incident, log in to your resident " +
                "account and open your submitted reports. " +
                "You can view the current processing status " +
                "and incident updates.",

            status:
                "Pending means the incident is awaiting action. " +
                "In Progress means barangay personnel are " +
                "working on it. Resolved means the incident " +
                "has been marked as addressed.",

            map:
                "The Community Issue Map displays incident " +
                "reports approved for public visibility. " +
                "You can view their locations and statuses.",

            support:
                "The I Have the Same Concern feature lets " +
                "eligible residents support an existing " +
                "approved incident instead of submitting " +
                "a duplicate report.",

            chat:
                "You can communicate with barangay personnel " +
                "through the Incident Conversation section " +
                "of your submitted report.",

            approval:
                "Barangay personnel review incident reports " +
                "before approving them for display on the " +
                "public Community Issue Map.",

            account:
                "You need a resident account to submit " +
                "incident reports. Use the registration " +
                "and login pages to access resident features.",

            priority:
                "Community support helps barangay personnel " +
                "identify frequently reported concerns. " +
                "Urgency and public safety are also considered.",

            emergency:
                "For immediate danger or emergencies, " +
                "contact the appropriate local emergency " +
                "services directly. Do not rely on this chatbot " +
                "for emergency response.",

            greeting:
                "Hello! I can help with incident reporting, " +
                "tracking, statuses, the Community Map, " +
                "and the Same Concern feature.",

            default:
                "I'm sorry, I don't have an answer to that " +
                "question yet. You can ask about reporting, " +
                "tracking, status, Community Map, Same Concern, " +
                "or messaging barangay personnel."
        };

        function getAnswer(question) {
            const q = question.toLowerCase().trim();

            if (/\b(emergency|fire|injured|accident|danger|urgent)\b/.test(q)) {
                return answers.emergency;
            }

            if (/\b(hello|hi|hey|good morning)\b/.test(q)) {
                return answers.greeting;
            }

            if (/\b(same concern|upvote|support|vote)\b/.test(q)) {
                return answers.support;
            }

            if (/\b(priority|prioritization)\b/.test(q)) {
                return answers.priority;
            }

            if (/\b(map|location|heatmap)\b/.test(q)) {
                return answers.map;
            }

            if (/\b(approval|approved|rejected)\b/.test(q)) {
                return answers.approval;
            }

            if (/\b(chat|message|contact admin|reply)\b/.test(q)) {
                return answers.chat;
            }

            if (/\b(register|registration|account|login|sign up)\b/.test(q)) {
                return answers.account;
            }

            if (/\b(track|tracking|reference|update)\b/.test(q)) {
                return answers.track;
            }

            if (/\b(status|pending|progress|resolved)\b/.test(q)) {
                return answers.status;
            }

            if (/\b(report|submit|incident|complaint)\b/.test(q)) {
                return answers.report;
            }

            return answers.default;
        }

        function addMessage(text, sender) {
            const bubble = document.createElement("div");

            bubble.className =
                "faq-message " +
                (sender === "user" ? "faq-user" : "faq-bot");

            // textContent prevents HTML injection.
            bubble.textContent = text;

            messages.appendChild(bubble);
            messages.scrollTop = messages.scrollHeight;
        }

        function sendQuestion(question, answerKey = null) {
            const text = question.trim();

            if (!text) {
                return;
            }

            addMessage(text, "user");

            const answer = answerKey && answers[answerKey]
                ? answers[answerKey]
                : getAnswer(text);

            addMessage(answer, "bot");
        }

        function openChat() {
            windowEl.hidden = false;
            toggle.setAttribute("aria-expanded", "true");
            input.focus();
        }

        function closeChat() {
            windowEl.hidden = true;
            toggle.setAttribute("aria-expanded", "false");
        }

        toggle.addEventListener("click", function () {
            if (windowEl.hidden) {
                openChat();
            } else {
                closeChat();
            }
        });

        close.addEventListener("click", closeChat);

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const question = input.value.trim();

            if (!question) {
                return;
            }

            sendQuestion(question);
            input.value = "";
            input.focus();
        });

        document.querySelectorAll(
            "#faq-chatbot [data-faq]"
        ).forEach(function (button) {
            button.addEventListener("click", function () {
                const key = button.dataset.faq;

                sendQuestion(button.textContent, key);
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initializeFAQChatbot
        );
    } else {
        initializeFAQChatbot();
    }
})();
