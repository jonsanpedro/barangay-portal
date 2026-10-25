console.log("Barangay Portal JavaScript connected!");

const images = [
    "images/barangay-1.jpg",
    "images/barangay-2.jpg",
    "images/barangay-3.jpg"
];

console.log(images);

const slideshowImage = document.querySelector(".hero-image img");

console.log(slideshowImage);


let currentIndex = 0;


setInterval(function() {

    slideshowImage.classList.add("fade-out");

    setTimeout(function() {

        currentIndex = (currentIndex + 1) % images.length;

        slideshowImage.src = images[currentIndex];

        slideshowImage.classList.remove("fade-out");

    }, 500);

}, 5000);

