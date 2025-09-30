$(document).on('click', '.ticket-status', function() {
    console.log('test');
    var $label = $(this);
    var $select = $label.siblings('.ticket-status-select')

    $label.addClass('hidden')
    $select.removeClass('hidden').focus();
})

$(document).on('blur', '.ticket-status-select', function() {
    console.log('test');
    var $select = $(this);
    var $label = $select.siblings('.ticket-status')

    $label.removeClass('hidden')
    $select.addClass('hidden')
})

$(document).on('change', '.ticket-status-select', function() {
    var $select = $(this);
    var $label = $select.siblings('.ticket-status')

    var ticketId = $select.data('id');
    var newStatusId = $select.val();

    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            controller: 'AdminTicketSystem',
            ajax: 1,
            action: 'updateTicketStatus',
            id_ticket: ticketId,
            id_status: newStatusId,
            token: adminToken
        },
        success: function (response) {
            if (response.success) {
                location.reload()
            } else {
                alert(response.message)
            }

            $label.removeClass('hidden');
            $select.addClass('hidden');
        }
    })
})