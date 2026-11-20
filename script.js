
console.log("Barangay Portal JavaScript connected!");

document.addEventListener("DOMContentLoaded", function () {

    const slideshowImage = document.querySelector(".hero-image img");

    if (!slideshowImage) {
        console.warn("Homepage slideshow image not found.");
        return;
    }

    let images = [];
    let currentIndex = 0;
    let slideshowTimer = null;

    // Load active slideshow images from the database.
    async function loadHomepageSlides() {

        try {
            const response = await fetch(
                "api/get_homepage_slides.php",
                {
                    method: "GET",
                    cache: "no-store"
                }
            );

            if (!response.ok) {
                throw new Error("Unable to load slideshow.");
            }

            const result = await response.json();

            if (!result.success || !Array.isArray(result.slides)) {
                throw new Error("Invalid slideshow response.");
            }

            images = result.slides
                .filter(function (slide) {
                    return typeof slide.image_path === "string";
                })
                .map(function (slide) {
                    return {
                        src: slide.image_path,
                        title: slide.image_title || "Barangay community activity"
                    };
                });

            if (images.length === 0) {
                console.warn("No active slideshow images found.");
                return;
            }

            // Show the first active image.
            currentIndex = 0;

            slideshowImage.src = images[0].src;
            slideshowImage.alt = images[0].title;

            console.log("Active slideshow images:", images);

            // Only animate when there is more than one image.
            if (images.length > 1) {
                startSlideshow();
            }

        } catch (error) {

            console.error("Slideshow loading error:", error);

            // Keep the original HTML image as fallback.
        }
    }

    // Start automatic slideshow.
    function startSlideshow() {

        if (slideshowTimer !== null) {
            clearInterval(slideshowTimer);
        }

        slideshowTimer = setInterval(function () {

            const nextIndex = (currentIndex + 1) % images.length;
            const nextImage = images[nextIndex];

            // Preload the next image before transitioning.
            const preloader = new Image();

            preloader.onload = function () {

                slideshowImage.classList.add("fade-out");

                setTimeout(function () {

                    currentIndex = nextIndex;

                    slideshowImage.src = nextImage.src;
                    slideshowImage.alt = nextImage.title;

                    slideshowImage.classList.remove("fade-out");

                }, 500);
            };

            preloader.onerror = function () {
                console.warn("Could not load image:", nextImage.src);
            };

            preloader.src = nextImage.src;

        }, 5000);
    }

    loadHomepageSlides();

});
