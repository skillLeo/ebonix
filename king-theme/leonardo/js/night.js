(function($) {
    "use strict";
    document.documentElement.classList.remove("king-night");
    localStorage.removeItem("king-night");
    $(window).load(function() {
        JSON.parse(localStorage.getItem("king-lnight")) && (document.documentElement.classList.remove("king-lnight"), document.getElementById("king-lnight").checked = !0);
        $("#king-lnight").change(function() {
            if ($(this).is(":checked")) {
                document.documentElement.classList.remove("king-lnight");
                var b = document.getElementById("king-lnight");
                localStorage.setItem("king-lnight", b.checked);
            } else {
                localStorage.removeItem("king-lnight");
                document.documentElement.classList.add("king-lnight");
            }
        })
    });
    try {
        $(window).load()
    } catch (b) {}
})(jQuery);