/**
 * @file
 * Keeps the Button & Header Colour swatch and its hex text field in sync.
 */

(function (Drupal, once) {

  'use strict';

  var HEX_PATTERN = /^#[0-9a-f]{6}$/i;

  Drupal.behaviors.apGenesysCloudColorSync = {
    attach: function (context) {
      once('apgc-color-sync', '.apgc-color-row', context).forEach(function (row) {
        var picker = row.querySelector('input[type="color"]');
        var hex = row.querySelector('.apgc-color-hex');
        if (!picker || !hex) {
          return;
        }

        var pickerWrapper = picker.closest('.form-item');
        var hexWrapper = hex.closest('.form-item');
        if (pickerWrapper && hexWrapper && pickerWrapper !== hexWrapper) {
          var inline = document.createElement('div');
          inline.className = 'apgc-color-inline';
          pickerWrapper.insertBefore(inline, picker);
          inline.appendChild(picker);
          inline.appendChild(hex);
          hexWrapper.hidden = true;
        }

        hex.value = picker.value;

        picker.addEventListener('input', function () {
          hex.value = picker.value;
        });

        hex.addEventListener('input', function () {
          var value = hex.value.trim();
          if (HEX_PATTERN.test(value)) {
            picker.value = value;
          }
        });
      });
    }
  };

})(Drupal, once);
