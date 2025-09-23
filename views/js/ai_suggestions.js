$(function () {
    var $suggestionBox = $("#ai-suggestion-box");
    var $suggestionText = $("#ai-suggestion-text");
    var ticketId = $("input[name='id_ticket']").val();
    var $textArea = $("#reply-textarea");
    var debounceTimer;


    function fetchSuggestion(draft) {
        $.ajax({
            url: "index.php",
            method: "POST",
            data: {
                controller: "AdminTicketSuggest",
                ajax: 1,
                action: "getSuggestion",
                token: window.adminAiSuggest,
                id_ticket: ticketId,
                draft: draft
            },
            dataType: "json",
            success: function (data) {
                if (data.suggestion) {
                    $suggestionBox.removeClass("hidden");
                    $suggestionText.text(data.suggestion);
                } else {
                    $suggestionBox.addClass("hidden");
                }
            }
        });
    }

    $textArea.on("input", function() {
        clearTimeout(debounceTimer);
        var draft = $textArea.val();
        debounceTimer = setTimeout(function() {
            fetchSuggestion(draft);
        }, 2000)
    })

    $textArea.on("keydown", function (e) {
        if (e.key === "Tab" && !$suggestionBox.hasClass("hidden")) {
            console.log('test')
            e.preventDefault();
            $textArea.val($suggestionText.text());
            $suggestionBox.addClass("hidden");
        }
    });

    // Insert only selected part
    $suggestionText.on("mouseup", function () {
        var selection = window.getSelection().toString();
        if (selection) {
            var current = $textArea.val();
            $textArea.val((current ? current + "\n" : "") + selection);
        }
    });
})