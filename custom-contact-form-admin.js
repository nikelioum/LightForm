// custom-contact-form-admin.js
document.addEventListener('DOMContentLoaded', function() {
    var copyShortcodeButton = document.getElementById('copy-shortcode-button');

    if (copyShortcodeButton) {
        var shortcodeToCopy = custom_contact_form_vars.shortcode;

        if (shortcodeToCopy) {
            var clipboard = new ClipboardJS(copyShortcodeButton, {
                text: function() {
                    return shortcodeToCopy;
                }
            });

            clipboard.on('success', function(e) {
                alert('Shortcode copied to clipboard: ' + e.text);
            });

            clipboard.on('error', function(e) {
                alert('Failed to copy shortcode to clipboard. Please select and copy it manually.');
            });
        } else {
            console.error('Shortcode not found in custom_contact_form_vars.shortcode');
        }
    }
});



