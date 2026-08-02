(function (Drupal, drupalSettings) {
  'use strict';

  // Ensure Genesys command queue exists so events are safely queued before SDK boots
  window.Genesys = window.Genesys || function () {
    (Genesys.q = Genesys.q || []).push(arguments);
  };

  Drupal.behaviors.genesysChatIntegration = {
    attach: function (context, settings) {
      const chatPopup = context.querySelector("#chat-popup");
      const chatButton = context.querySelector("#ap-lumenchat");
      const form = context.querySelector("#contactForm");
      const closeButton = context.querySelector("#close-chat-popup");
      const customIcon = context.querySelector(".custom-icon");
      const contain = context.querySelector(".container");

      // Prevent duplicate attachment across Drupal AJAX refreshes
      if (!chatButton || chatButton.dataset.initialized) {
      
        return;
      }
      chatButton.dataset.initialized = "true"; 

      // Display custom chat button and save state
      function showChatButton() {
        if (chatButton) {
          chatButton.style.display = "flex";
        }
      sessionStorage.removeItem("genesys_chat_active");
      }

      // Do not show custom chat button and save state
      function hideChatButton() {
        if (chatButton) {
          chatButton.style.display = "none";
        }
        // Needed if user clicks another page within the site
        sessionStorage.setItem("genesys_chat_active", "true");
      }

      // Initial state check on page load / navigation
      if (contain && chatButton) {
        contain.appendChild(chatButton);
        
        // If an active session was saved in sessionstorage show button.
        if (sessionStorage.getItem("genesys_chat_active") === "true") {
          console.log("where my button go 1");
               showChatButton();
        } 
      }

      // --- Genesys Messenger Subscriptions --- //

      // Handle page refresh or direct SDK load when session already exists
      Genesys("subscribe", "MessagingService.started", () => {
        // If Genesys recognizes an existing session, ensure chatButton remains hidden
        hideChatButton();
      });

      // Hide chatButton when Genesys opens
      Genesys("subscribe", "Messenger.opened", () => {
        console.log("Genesys Messenger opened.");
        hideChatButton();
      });

      // Do NOTHING to chatButton when user simply minimizes/closes the Messenger window
      Genesys("subscribe", "Messenger.closed", () => {
        console.log("Genesys Messenger toggled.");
      });

      // Display custom chat button ONLY when user explicitly terminates/clears the conversation
      Genesys("subscribe", "MessagingService.conversationCleared", () => {
        console.log("Genesys Conversation terminated by user.");
        
        // Clear active session state and restore custom button
        showChatButton(); 

        // Reset lead form so it's fresh for next time
        if (form) {
          form.reset(); 
          form.style.display = "block";
          console.log("where my button go");
        }
      });

      // --- Lead Form & Popup Interactions --- //

      chatButton.addEventListener("click", () => {
        if (chatPopup) {
          chatPopup.style.display = chatPopup.style.display === "block" ? "none" : "block";
          chatPopup.classList.toggle("open");
          hideChatButton();
        }
        if (form) {
          form.style.display = "block";
          if (customIcon) {
            customIcon.classList.remove("default");
          }
        }
      });

      if (closeButton) {
        closeButton.addEventListener("click", () => {
          if (chatPopup) {
            chatPopup.style.display = "none";
            chatPopup.classList.remove("open");
          }
          showChatButton();
        });
      }

      if (form) {
        form.addEventListener("submit", function (event) {
          event.preventDefault();
          event.stopPropagation();

          if (!form.checkValidity()) {
            form.classList.add("was-validated");
          } else {
            const formData = new FormData(event.target);
            const formProps = Object.fromEntries(formData.entries());

            const customAttributes = {};
            const customFields = drupalSettings.apChatbotLumen?.customFields || [];

            customFields.forEach((field) => {
              if (field.mapping && field.id && formProps[field.id]) {
                customAttributes[field.mapping] = formProps[field.id];
              }
            });

            Genesys("command", "Database.set", {
              messaging: {
                customAttributes: customAttributes,
              },
            });

            if (chatPopup) { 
              chatPopup.style.display = "none";
              toggleMessenger();
            }
          }
        });
      }

      // Genesys Toggle
      function toggleMessenger() {
        Genesys(
          "command",
          "Messenger.open",
          {},
          function () {
            console.log("Genesys Messenger opened.");
            hideChatButton();
          },
          function (err) {
            console.error("Genesys Messenger failed to open:", err);
            showChatButton(); // Restoration fallback if Genesys fails
          }
        );
      }
    },
  };
})(Drupal, drupalSettings);