(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.apGenesysCloud = {
    attach: function (context) {
      const chatButton = context.querySelector('#apgc-chat');

      // Guard against duplicate attachment across Drupal AJAX/BigPipe
      // re-renders of the containing region.
      if (!chatButton || chatButton.dataset.apgcInitialized) {
        return;
      }
      chatButton.dataset.apgcInitialized = 'true';

      // The popup only exists when the site has custom fields configured
      // (see LumenChatBlock::build()). Sites with none (e.g. an agency
      // whose provided script is just the bootstrap snippet, no form) skip
      // straight to opening Messenger on click.
      const chatPopup = context.querySelector('#apgc-chat-popup');
      const form = context.querySelector('#apgc-contact-form');
      const closeButton = context.querySelector('#apgc-close-chat-popup');
      let lastFocusedElement = null;



      document.body.appendChild(chatButton);
      if (chatPopup) {
        document.body.appendChild(chatPopup);
      }

      if (chatPopup) {
        chatButton.setAttribute('aria-haspopup', 'dialog');
        chatButton.setAttribute('aria-expanded', 'false');
      }

      // Elements a keyboard user could legitimately land on inside the
      // popup, used to keep Tab/Shift+Tab focus trapped within it while
      // open (WCAG 2.4.3) -- a "modal" popup that lets focus escape to the
      // page behind it is confusing for keyboard and screen-reader users
      // alike, even though it isn't a literal keyboard trap itself.
      function getFocusableElements() {
        return Array.prototype.slice.call(
          chatPopup.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
          )
        );
      }

      function trapFocus(event) {
        if (event.key === 'Escape') {
          event.preventDefault();
          closePopup();
          return;
        }
        if (event.key !== 'Tab') {
          return;
        }
        const focusable = getFocusableElements();
        if (!focusable.length) {
          return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        }
        else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }

      function openPopup() {
        // Reset any leftover state from a previous handoff attempt (see
        // the Database.updated subscriber below) so the form is always
        // visible when the popup opens, not stuck hidden from a prior
        // Messenger hand-off.
        if (form) {
          form.style.display = '';
        }
        chatPopup.style.display = 'block';
        chatPopup.classList.add('open');
        // The trigger button sits at the same fixed position as the
        // popup, without hiding it here, it stays visible (just
        // stacked underneath the popup, at a lower z-index) instead of
        // disappearing while the popup is open.
        chatButton.style.display = 'none';
        chatButton.setAttribute('aria-expanded', 'true');
        lastFocusedElement = document.activeElement;
        const title = chatPopup.querySelector('#apgc-chat-popup-title');
        if (title) {
          title.focus();
        }
        document.addEventListener('keydown', trapFocus, true);
      }

      // returningToPage is false for the auto-close-on-successful-submit
      // path below, where focus should NOT return to #apgc-chat and the
      // button should NOT be shown here. The Messenger widget is taking
      // over instead, and toggleMessenger()'s own callbacks already decide
      // the button's visibility (hidden on success, shown again if
      // Messenger.open is rejected).
      function closePopup(returningToPage) {
        chatPopup.style.display = 'none';
        chatPopup.classList.remove('open');
        chatButton.setAttribute('aria-expanded', 'false');
        document.removeEventListener('keydown', trapFocus, true);
        if (returningToPage !== false) {
          chatButton.style.display = 'flex';
          (lastFocusedElement || chatButton).focus();
        }
      }

      // Keyed per deployment, so an open conversation in one area of the
      // site doesn't hide the chat button in an area that uses a
      // different deployment.
      const activeKey = `apGenesysCloudActive:${drupalSettings.apGenesysCloud?.deploymentId || ''}`;

      function showChatButton() {
        chatButton.style.display = 'flex';
        sessionStorage.removeItem(activeKey);
      }

      function hideChatButton() {
        chatButton.style.display = 'none';
        // Persisted so the custom button stays hidden if the visitor
        // navigates to another page while Messenger is still open.
        sessionStorage.setItem(activeKey, 'true');
      }

      if (sessionStorage.getItem(activeKey) === 'true') {
        hideChatButton();
      }

      // Whether the lead-capture screen can be skipped, so the trigger
      // reopens Messenger directly instead (e.g. after the visitor
      // minimized it). Deliberately kept in memory only, never in browser
      // storage:
      // - screenSubmittedOnThisPage: the visitor completed the screen on
      //   this page load. Genesys still holds their answers in its
      //   in-memory Database, so reopening Messenger loses nothing.
      // - conversationInProgress: Genesys reports a real conversation
      //   (restored from an earlier page, or started). Its answers already
      //   reached Genesys with the conversation's messages.
      let screenSubmittedOnThisPage = false;
      let conversationInProgress = false;

      function canSkipScreen() {
        return screenSubmittedOnThisPage || conversationInProgress;
      }

      chatButton.addEventListener('click', () => {
        if (chatPopup && !canSkipScreen()) {
          if (chatPopup.classList.contains('open')) {
            closePopup();
          }
          else {
            openPopup();
          }
        }
        else {
          toggleMessenger();
        }
      });

      if (closeButton) {
        closeButton.addEventListener('click', () => {
          closePopup();
        });
      }

      if (typeof Genesys === 'function') {
        Genesys('subscribe', 'Messenger.opened', hideChatButton);
        Genesys('subscribe', 'Messenger.closed', showChatButton);

        // The same conversation signals Genesys's own launcher uses to
        // decide when to show and hide itself (see its messenger/main.min.js).
        Genesys('subscribe', 'MessagingService.restored', () => {
          conversationInProgress = true;
        });
        Genesys('subscribe', 'MessagingService.started', () => {
          conversationInProgress = true;
        });
        // The conversation is over: the next chat gets the screen again.
        const resetScreen = () => {
          conversationInProgress = false;
          screenSubmittedOnThisPage = false;
        };
        Genesys('subscribe', 'MessagingService.conversationCleared', resetScreen);
        Genesys('subscribe', 'MessagingService.sessionExpired', resetScreen);
      }

      if (form) {
        // Database.updated fires for ANY Database.set call, not just ones
        // this form makes - notably including chatbot-init.js's own
        // startup "clear stale custom attributes from a previous visit"
        // call, which runs on every page load before the visitor has
        // touched this form at all. Without this guard, that startup call
        // was enough to prematurely open Messenger and hide/close this
        // popup on page load, leaving #apgc-contact-form's inline display:none
        // stuck for the rest of the session - see the Database.updated
        // subscriber below.
        let awaitingOwnSubmission = false;

        // Always attached, even if Genesys failed to load: without it the
        // form would fall back to a native GET submit, putting the
        // visitor's answers into the page URL (and server access logs).
        form.addEventListener('submit', function (event) {
          event.preventDefault();
          event.stopPropagation();

          if (typeof Genesys !== 'function') {
            return;
          }

          // reportValidity() (not checkValidity()) so an invalid submit
          // moves focus to the first invalid field and triggers the
          // browser's native, AT-announced validation message — the form
          // has novalidate specifically so this stays under this
          // handler's control instead of firing implicitly.
          if (!form.reportValidity()) {
            form.classList.add('was-validated');
            return;
          }

          const formData = new FormData(event.target);
          const formProps = Object.fromEntries(formData.entries());
          const customFields = drupalSettings.apGenesysCloud?.customFields || [];
          const customAttributes = {};

          customFields.forEach((field) => {
            if (field.mapping && field.id && formProps[field.id]) {
              customAttributes[field.mapping] = formProps[field.id];
            }
          });

          // Per the Genesys-documented pattern: setting customAttributes
          // triggers "Database.updated" below, which opens the Messenger.
          awaitingOwnSubmission = true;
          Genesys('command', 'Database.set', {
            messaging: {
              customAttributes: customAttributes,
            },
          });
        });

        if (typeof Genesys === 'function') {
          Genesys('subscribe', 'Database.updated', function () {
            if (!awaitingOwnSubmission) {
              return;
            }
            awaitingOwnSubmission = false;
            screenSubmittedOnThisPage = true;
            toggleMessenger();
            form.style.display = 'none';
            // Messenger is opening in the popup's place — don't return
            // focus to #apgc-chat, since toggleMessenger()'s success
            // callback is about to hide it.
            closePopup(false);
          });
        }
      }

      function toggleMessenger() {
        Genesys(
          'command',
          'Messenger.open',
          {},
          function () {
            hideChatButton();
          },
          function (err) {
            Genesys('command', 'Messenger.close');
            showChatButton();
            // eslint-disable-next-line no-console
            console.error('Genesys Messenger failed to open:', err);
          }
        );
      }
    },
  };
})(Drupal, drupalSettings);
