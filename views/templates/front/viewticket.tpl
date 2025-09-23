{if isset($errors) && $errors}
    <div class="alert alert-danger">
        <ul>
            {foreach from=$errors item=error}
                <li>{$error}</li>
            {/foreach}
        </ul>
    </div>
{/if}

<div class="row">
    <!-- LEFT SIDEBAR -->
    <div class="col-md-3">
        <div class="panel">
            <div class="panel-heading"><strong>Ticket Details</strong></div>

            <p><strong>Ticket ID:</strong> #{$ticket->id_ticket}</p>
            <p><strong>Subject:</strong> {$ticket->subject|escape:'html':'UTF-8'}</p>
            <p><strong>Status:</strong>
                <span class="label" style="background-color:{$ticket->getStatus()->color};">
                    {$ticket->getStatus()->name}
                </span>
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
            <div class="panel-heading">
                <strong>Conversation</strong>
            </div>

            <div class="chat-thread">
                {assign var="last_author" value=""}
                {foreach from=$messages item=msg}
                    {if $msg->author_type != $last_author}
                        <!-- New group header -->
                        <div class="chat-group {if $msg->author_type == 1}employee{else}customer{/if}">
                        <div class="chat-meta">
                            {if $msg->author_type == 1}
                                <strong>{$msg->getAuthor()->firstname} {$msg->getAuthor()->lastname}</strong>
                                <span class="small"> - Support</span>
                            {else}
                                <strong>You</strong>
                            {/if}
                        </div>
                    {/if}

                    <!-- Message bubble -->
                    <div class="chat-message">
                        <div class="chat-bubble">
                            <div class="chat-text">
                                {$msg->message|escape|nl2br}
                            </div>
                            <div class="chat-time text-muted small" title="{dateFormat date=$ticket->created_at full=1 time=1}">{$msg->created_at|relativetime}</div>
                        </div>
                    </div>

                    {if $msg@last || $messages[$msg@iteration]->author_type != $msg->author_type}
                        </div> <!-- close chat-group -->
                    {/if}

                    {assign var="last_author" value=$msg->author_type}
                {/foreach}
            </div>


        <!-- Reply panel -->
        <div class="panel">
            <form method="post" action="{$link->getModuleLink('ticketsystem','viewticket',['id_ticket'=>$ticket->id_ticket])}">
                <input type="hidden" name="ticket_id" value="{$ticket->id_ticket}">
                <div class="form-group">
                    <textarea name="message" class="form-control" rows="3" placeholder="Write a reply..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Reply</button>
                <a href="{$link->getModuleLink('ticketsystem','mytickets')}" class="btn btn-default">Back</a>
            </form>
        </div>
    </div>
</div>

<style>
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

</style>
