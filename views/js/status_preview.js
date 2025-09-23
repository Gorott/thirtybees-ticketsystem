$(document).ready(function() {
    const $select = $('select[name="DEFAULT_TICKET_STATUS"]');

    if (!$select.length) return;

    $select.select2({
        templateResult: function(option) {
            if (!option.id) return option.text;
            const parts = option.text.split(`||`);
            const label = parts[0]
            const color = parts[1] || `#999`
            return $('<span>')
                .addClass('label')
                .css({
                    'background-color': color,
                    'color': '#fff',
                    'padding': '2px 6px',
                    'border-radius': '4px'
                })
                .text(label);
        },
        templateSelection: function(option) {
            if (!option.id) return option.text;
            const parts = option.text.split(`||`);
            const label = parts[0]
            const color = parts[1] || `#999`
            return $('<span>')
                .addClass('label')
                .css({
                    'background-color': color,
                    'color': '#fff',
                    'padding': '2px 6px',
                    'border-radius': '4px'
                })
                .text(label);
        }
    });
});