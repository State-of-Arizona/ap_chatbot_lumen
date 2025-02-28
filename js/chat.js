(function (Drupal, drupalSettings) {
  document.addEventListener("DOMContentLoaded", () => {
    const chatPopup = document.querySelector("#chat-popup");
    const chatButton = document.querySelector("#ap-lumenchat");
    const form = document.querySelector("#contactForm");
    const closeButton = document.querySelector("#close-chat-popup");

    // Ensure chat popup and button exist
    if (!chatPopup || !chatButton) {
      console.error("Chat popup or button not found.");
      return;
    }

    // Toggle chat popup on button click
    if (chatButton) {
      chatButton.addEventListener("click", () => {
        chatPopup.style.display = chatPopup.style.display === "block" ? "none" : "block";
        chatPopup.classList.toggle("open");
      });
    }

    // Listen for Genesys Messenger events
    if (window.Genesys) {
      Genesys("subscribe", "Messenger.opened", () => {
        console.log("Genesys chat opened.");
        if (chatButton) {
          chatButton.style.display = "none"; // Hide custom chat button
        }
      });

      Genesys("subscribe", "Messenger.closed", () => {
        console.log("Genesys chat closed.");
        if (chatButton) {
          chatButton.style.display = "block"; // Show custom chat button
        }
      });
    }

    // Close chat popup on close button click
    if (closeButton) {
      closeButton.addEventListener("click", () => {
        chatPopup.style.display = "none";
        chatPopup.classList.remove("open");
      });
    }

    // Form handling for Genesys custom attributes
    if (form) {
      form.addEventListener("submit", function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (!form.checkValidity()) {
          // Mark form as validated for Bootstrap feedback
          form.classList.add("was-validated");
        } else {
          const formData = new FormData(event.target);
          const formProps = Object.fromEntries(formData.entries());

          // Dynamically build customAttributes from drupalSettings
          const customAttributes = {};
          const customFields = drupalSettings.apChatbotLumen?.customFields || [];

          customFields.forEach((field) => {
            if (field.mapping && field.id && formProps[field.id]) {
              customAttributes[field.mapping] = formProps[field.id];
            }
          });

          // Set custom attributes in Genesys Database
          Genesys("command", "Database.set", {
            messaging: {
              customAttributes: customAttributes,
            },
          });

          console.log("Custom attributes sent to Genesys:", customAttributes);

          // Optionally close the popup after submission
          chatPopup.style.display = "none";
        }
      });

      // Listen for Genesys Database Updates
      Genesys("subscribe", "Database.updated", function (e) {
        console.log("Genesys database updated:", e.data);
        toggleMessenger(); // Open Messenger chat window

        // Hide or disable the form after submission
        form.style.display = "none";
      });
    }

    // Toggle Messenger Chat Window
    function toggleMessenger() {
      Genesys(
        "command",
        "Messenger.open",
        {},
        function () {
          console.log("Genesys Messenger opened.");
        },
        function () {
          Genesys("command", "Messenger.close");
          console.log("Genesys Messenger closed.");
        }
      );
    }
  });
})(Drupal, drupalSettings);
