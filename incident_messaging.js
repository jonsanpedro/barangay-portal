
document.addEventListener("DOMContentLoaded", () => {

    const container = document.getElementById("live-messages");
    const indicator = document.getElementById("new-message-indicator");

    if (!container) return;

    const incidentId = container.dataset.incidentId;

    let lastMessageId = 0;
    let initialized = false;
    let loading = false;

    const POLL_INTERVAL = 5000;

    function createMessage(message) {

        const card = document.createElement("article");

        card.className = "chat-message " +
            (message.is_mine ? "chat-mine" : "chat-other");

        const author = document.createElement("strong");
        author.textContent = message.author;

        const time = document.createElement("small");
        time.textContent = message.created_at;

        const header = document.createElement("div");
        header.className = "chat-message-header";

        header.append(author, time);

        const body = document.createElement("p");
        body.textContent = message.comment_text;
        body.className = "chat-message-text";

        card.append(header, body);

        if (message.evidence_path) {

            const img = document.createElement("img");

            img.src = message.evidence_path;
            img.alt = "Additional incident evidence";
            img.loading = "lazy";
            img.className = "chat-evidence-image";

            card.appendChild(img);
        }

        return card;
    }

    async function loadMessages() {

        if (loading || document.hidden) return;

        loading = true;

        try {
            const response = await fetch(
                "api/get_incident_messages.php?incident_id=" +
                encodeURIComponent(incidentId),
                {
                    credentials: "same-origin",
                    cache: "no-store"
                }
            );

            if (!response.ok) {
                throw new Error("Unable to retrieve messages.");
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error("Message request failed.");
            }

            const messages = data.messages;

            const latestId = messages.length
                ? messages[messages.length - 1].comment_id
                : 0;

            if (initialized && latestId > lastMessageId) {

                const newMessages = messages.filter(
                    message =>
                        message.comment_id > lastMessageId &&
                        !message.is_mine
                );

                if (newMessages.length > 0 && indicator) {
                    indicator.hidden = false;
                    indicator.textContent =
                        newMessages.length + " new message(s)";
                }
            }

            container.replaceChildren();

            if (messages.length === 0) {

                const empty = document.createElement("p");
                empty.textContent = "No messages yet.";

                container.appendChild(empty);

            } else {

                const fragment = document.createDocumentFragment();

                messages.forEach(message => {
                    fragment.appendChild(createMessage(message));
                });

                container.appendChild(fragment);
            }

            lastMessageId = latestId;
            initialized = true;

        } catch (error) {
            console.error("Messaging error:", error);

        } finally {
            loading = false;
        }
    }

    if (indicator) {
        indicator.addEventListener("click", () => {
            indicator.hidden = true;
            container.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });
        });
    }

    loadMessages();

    setInterval(loadMessages, POLL_INTERVAL);

    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
            loadMessages();
        }
    });
});
