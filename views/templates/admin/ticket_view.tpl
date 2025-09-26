<div class="row">
    <!-- LEFT SIDEBAR -->
    <div class="col-md-3">
        <div class="panel">
            <div class="panel-heading"><strong>Ticket Details</strong></div>

            <strong>Ticket ID: </strong> #{$ticket->id_ticket}<br>
            {if !is_null($ticket->id_customer)}
                <strong>Customer Account: </strong>
                {$ticket->getCustomer()->firstname} {$ticket->getCustomer()->lastname}
                <br>
            {/if}
            {if !is_null($ticket->id_order)}
                <strong>Order: </strong>
                <a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&id_order={$ticket->id_order}&vieworder">
                    #{$ticket->getOrder()->reference}
                </a>
                <br>
            {/if}

            <strong>Email: </strong>
            <a href="mailto:{$ticket->email}">{$ticket->email}</a><br>
            <p class="assignee-row" data-ticket-id="{$ticket->id}">
                <span class="assignee-label"><strong>Assignee:</strong></span>
                <span class="assignee-name">
                    {if $ticket->id_assignee}
                        {$ticket->getAssignee()->firstname} {$ticket->getAssignee()->lastname}
                    {else}
                        <a href="#" class="assign-to-me" data-ticket-id="{$ticket->id}">Assign to me</a> or
                        <a href="#" class="change-assignee" data-ticket-id="{$ticket->id}">Assign to other employee</a>
                    {/if}
            </span>
                {if $ticket->id_assignee}
                    <a href="#" class="small change-assignee" data-ticket-id="{$ticket->id}">Change Assignee</a>
                {/if}
            </p>
            <strong>Created: </strong>
            <span class="time" title="{dateFormat date=$ticket->created_at full=1 time=1}">
                {$ticket->created_at|relativetime}
            </span><br>
            <strong>Last Update: </strong>
            <span class="time" title="{dateFormat date=$ticket->last_updated full=1 time=1}">
                {$ticket->last_updated|relativetime}
            </span><br>
        </div>
    </div>

    <!-- MAIN COLUMN -->
    <div class="col-md-9">
        <div class="panel">
            <div class="panel-heading clearfix">
                <span class="label ticket-status" data-id="{$ticket->id_ticket}" title="Click to change status"
                      style="background-color:{$ticket->getStatus()->color};">
                    {$ticket->getStatus()->name}
                </span>
                <select class="ticket-status-select hidden" data-id="{$ticket->id_ticket}">
                    {foreach from=$statuses item=status}
                        <option value="{$status->id_ticket_status} {if $ticket->getStatus()->id_ticket_status == $status->id_ticket_status}selected{/if}">{$status->name}</option>
                    {/foreach}
                </select>
                <strong>Ticket #{$ticket->id_ticket}: {$ticket->subject}</strong>
            </div>

            <div class="chat-thread">
                {assign var="last_author" value=""}
                {foreach from=$messages item=msg}
                    {if $msg->author_type != $last_author}
                        <!-- Start new group -->
                        <div class="chat-group {if $msg->author_type == 1}employee{else}customer{/if}">
                        <div class="chat-meta">
                            {if $msg->author_type == 1}
                                <strong>{$msg->getAuthor()->firstname} {$msg->getAuthor()->lastname}</strong>
                                <span class="small"> - Employee</span>
                            {else}
                                {if $msg->author_type == 2}
                                    <strong>{$msg->getAuthor()->firstname} {$msg->getAuthor()->lastname}</strong>
                                {elseif $msg->author_type == 3}
                                    <strong>{$msg->email}</strong>
                                {/if}
                                <span class="small"> - Customer</span>
                            {/if}
                        </div>
                    {/if}

                    <!-- Message bubble -->
                    <div class="chat-message">
                        <div class="chat-bubble">
                            <div class="chat-text">
                                {$msg->message|escape|nl2br}
                            </div>
                            <div class="chat-time text-muted small" title="{dateFormat date=$msg->created_at full=1 time=1}">{$msg->created_at|relativetime}</div>
                        </div>
                    </div>

                    {if $msg@last || $messages[$msg@iteration]->author_type != $msg->author_type}
                        </div> <!-- Close chat-group -->
                    {/if}

                    {assign var="last_author" value=$msg->author_type}
                {/foreach}
            </div>
        </div>

        <!-- Reply panel -->
        <div class="panel">
            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="id_ticket" value="{$ticket->id_ticket}">
                <div id="ai-suggestion-box" class="ai-suggestion hidden">
                    <div class="ai-header">💡 Suggested reply (press Tab to accept)</div>
                    <div id="ai-suggestion-text"></div>
                </div>
                <textarea id="reply-textarea" name="message" class="form-control" rows="3" placeholder="Write a reply..."></textarea>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>

    <div id="assignEmployeeModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <strong class="modal-title">Assign Ticket</strong>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <strong>Employee to assign the ticket to:</strong><br>
                    <select id="employee-select" class="form-control">
                        <option value="-1">Unassign</option>
                        {foreach from=$employees item=emp}
                            <option value="{$emp->id}">
                                {$emp->firstname} {$emp->lastname}
                            </option>
                        {/foreach}
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="confirm-assign">Assign</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .label {
        margin-right: 8px;
    }

    .ticket-status {
        cursor: pointer; /* shows it's clickable */
        transition: all 0.2s ease-in-out;
    }

    .ticket-status:hover {
        filter: brightness(1.15);
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        transform: scale(1.03);
    }


    .ticket-status-select.hidden {
        display: none;
    }

    .ticket-status-select {
        display: inline-block !important;
        width: auto !important;
        min-width: 100px;
        padding: 2px 6px !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
        height: auto !important;
    }


    .chat-thread {
        max-height: 600px;
        overflow-y: auto;
        padding: 15px;
        scroll-behavior: smooth;
    }

    .chat-group {
        margin-bottom: 20px;
    }

    .chat-meta {
        margin-bottom: 5px;
        font-weight: bold;
    }

    .chat-message {
        display: flex;
        margin-bottom: 6px;
        justify-content: flex-start;
    }

    .chat-bubble {
        display: inline-block;
        max-width: 60%;
        padding: 10px 14px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        word-wrap: break-word;
    }

    .chat-group.customer .chat-bubble {
        background: #e4e4e4;
        text-align: left;
    }

    .chat-group.employee .chat-bubble {
        background: #a5aeff;
        text-align: left;
    }

    .chat-time {
        margin-top: 4px;
        font-size: 11px;
        color: #666;
    }

    .ai-suggestion {
        background: #f8f9fa;
        border: 1px solid #ccc;
        border-radius: 6px;
        padding: 10px;
        margin-bottom: 8px;
        font-size: 14px;
        color: #333;
    }

    .ai-suggestion.hidden {
        display: none;
    }

    .ai-header {
        font-size: 12px;
        font-weight: bold;
        margin-bottom: 4px;
        color: #666;
    }
</style>
