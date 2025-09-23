// Handle "Assign to me"
$(document).on('click', '.assign-to-me', function (e) {
    e.preventDefault();
    var link = $(this);
    var ticketId = link.data('ticket-id');

    $.post(
        'index.php?controller=AdminTicketSystem&ajax=1&action=assignToEmployee&token=' + window.adminToken,
        { id_ticket: ticketId, id_employee: window.currentEmployee },
        function (data) {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed: ' + (data.error || 'Unknown error'));
            }
        },
        'json'
    );
});

// Handle "Change Assignee" (skeleton – you can plug in your modal/dropdown logic)
$(document).on('click', '.change-assignee', function (e) {
    e.preventDefault();
    currentTicketId = $(this).data('ticket-id');
    $('#assignEmployeeModal').modal('show');
});

// Confirm assign in modal
$(document).on('click', '#confirm-assign', function (e) {
    e.preventDefault();

    const empId = $('#employee-select').val();

    $.post(
        'index.php?controller=AdminTicketSystem&ajax=1&action=assignToEmployee&token=' + window.adminToken,
        { id_ticket: currentTicketId, id_employee: empId },
        function (data) {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed: ' + (data.error || 'Unknown error'));
            }
        },
        'json'
    );
});

