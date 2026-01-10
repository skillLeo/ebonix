
(function($) {
    "use strict";


$(document).on('input', '#ai-box', (event) => {
    const textarea = event.target;
    textarea.style.height = "auto"; // Reset height to auto
    textarea.style.height = `${textarea.scrollHeight}px`; // Set the height to fit the content
});
    
})(jQuery);