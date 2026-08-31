/* =========================================================
   AI TRAVEL ITINERARY OPTIMIZER
   Frontend JavaScript
   ========================================================= */


/* ---------------------------------------------------------
   Smooth scrolling
   --------------------------------------------------------- */

function scrollToSection(sectionId) {

    const section = document.getElementById(sectionId);

    if (section) {
        section.scrollIntoView({
            behavior: "smooth"
        });
    }
}


/* ---------------------------------------------------------
   Mobile navigation
   --------------------------------------------------------- */

document.addEventListener("DOMContentLoaded", function () {

    const navLinks = document.querySelectorAll(".nav-links a");

    navLinks.forEach(function (link) {

        link.addEventListener("click", function () {

            navLinks.forEach(function (item) {
                item.classList.remove("active");
            });

            this.classList.add("active");

        });

    });

});


/* ---------------------------------------------------------
   Page scroll animation
   --------------------------------------------------------- */

const observer = new IntersectionObserver(
    function (entries) {

        entries.forEach(function (entry) {

            if (entry.isIntersecting) {
                entry.target.classList.add("show");
            }

        });

    },
    {
        threshold: 0.15
    }
);


/* ---------------------------------------------------------
   Observe cards
   --------------------------------------------------------- */

document.addEventListener("DOMContentLoaded", function () {

    const elements = document.querySelectorAll(
        ".feature-card, .step-card, .destination-card"
    );

    elements.forEach(function (element) {

        element.classList.add("hidden");

        observer.observe(element);

    });

});