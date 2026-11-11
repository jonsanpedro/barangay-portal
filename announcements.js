
/* =====================================
   DYNAMIC BARANGAY ANNOUNCEMENTS
===================================== */

const announcementsContainer = document.getElementById(
    "announcements-container"
);

async function loadAnnouncements() {

    if (!announcementsContainer) {
        return;
    }

    try {
        const response = await fetch(
            "api/get_announcements.php",
            {
                method: "GET",
                cache: "no-store"
            }
        );

        if (!response.ok) {
            throw new Error("Failed to load announcements.");
        }

        const data = await response.json();

        if (!data.success || !Array.isArray(data.announcements)) {
            throw new Error("Invalid announcements response.");
        }

        // Clear the loading message.
        announcementsContainer.replaceChildren();

        // Display a message if there are no announcements.
        if (data.announcements.length === 0) {

            const emptyMessage = document.createElement("p");

            emptyMessage.textContent =
                "No announcements available at the moment.";

            announcementsContainer.appendChild(emptyMessage);

            return;
        }

        // Create an announcement card for each record.
        data.announcements.forEach(function(announcement) {

            const card = document.createElement("article");
            card.className = "announcement-card";

            const date = document.createElement("p");
            date.className = "announcement-date";

            if (announcement.published_at) {
                const datePart = announcement.published_at.split(" ")[0];
                const [year, month, day] = datePart.split("-").map(Number);

                const publishedDate = new Date(year, month - 1, day);

                date.textContent = publishedDate.toLocaleDateString(
                    "en-US",
                    {
                        year: "numeric",
                        month: "long",
                        day: "numeric"
                    }
                );
            } else {
                date.textContent = "Date unavailable";
            }

            const title = document.createElement("h3");
            title.textContent = announcement.title;

            const content = document.createElement("p");
            content.textContent = announcement.content;

            card.appendChild(date);
            card.appendChild(title);
            card.appendChild(content);

            announcementsContainer.appendChild(card);
        });

    } catch (error) {

        console.error("Announcements error:", error);

        announcementsContainer.textContent =
            "Unable to load announcements. Please try again later.";
    }
}

// Load announcements when the page opens.
loadAnnouncements();
