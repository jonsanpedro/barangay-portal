
<?php
// FAQ Chatbot interface.
// No database connection required.
?>

<link rel="stylesheet" href="faq_chatbot.css">

<div id="faq-chatbot" class="faq-chatbot">

    <button
        type="button"
        id="faq-chat-toggle"
        class="faq-chat-toggle"
        aria-label="Open FAQ chatbot"
        aria-expanded="false"
    >
        Help & FAQ
    </button>

    <section
        id="faq-chat-window"
        class="faq-chat-window"
        aria-label="Barangay FAQ Assistant"
        hidden
    >
        <div class="faq-chat-header">
            <div>
                <strong>Barangay Portal Assistant</strong>
                <small>Automated FAQ Support</small>
            </div>

            <button
                type="button"
                id="faq-chat-close"
                aria-label="Close chatbot"
            >
                &times;
            </button>
        </div>

        <div
            id="faq-chat-messages"
            class="faq-chat-messages"
            role="log"
            aria-live="polite"
        >
            <div class="faq-message faq-bot">
                Hello! Welcome to Barangay Chrysanthemum
                Portal. How can I help you today?
            </div>
        </div>

        <div class="faq-chat-suggestions">
            <button type="button" data-faq="report">
                Report Incident
            </button>

            <button type="button" data-faq="track">
                Track Report
            </button>

            <button type="button" data-faq="status">
                Report Status
            </button>

            <button type="button" data-faq="map">
                Community Map
            </button>
        </div>

        <form id="faq-chat-form" class="faq-chat-form">
            <input
                type="text"
                id="faq-chat-input"
                placeholder="Type your question..."
                maxlength="300"
                autocomplete="off"
                aria-label="Your question"
                required
            >

            <button type="submit">
                Send
            </button>
        </form>

        <p class="faq-chat-disclaimer">
            Automated answers only. For emergencies,
            contact the appropriate emergency services.
        </p>
    </section>
</div>

<script src="faq_chatbot.js" defer></script>
