<h1>My Tickets</h1>

{if $tickets && count($tickets) > 0}
    <table class="table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Date Created</th>
            <th>Last Update</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$tickets item=ticket}
            <tr>
                <td>{$ticket->id_ticket}</td>
                <td>{$ticket->subject|escape:'html':'UTF-8'}</td>
                <td>{$ticket->getStatus()->name|escape:'html':'UTF-8'}</td>
                <td title="{dateFormat date=$ticket->created_at full=1 time=1}">{$ticket->created_at|relativetime}</td>
                <td title="{dateFormat date=$ticket->last_updated full=1 time=1}">{$ticket->last_updated|relativetime}</td>
                <td>
                    <a href="{$link->getModuleLink('ticketsystem','viewticket',['id_ticket'=>$ticket->id_ticket])}"
                       class="btn btn-primary btn-sm">
                        View
                    </a>
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
{else}
    <p>You have no tickets yet.</p>
{/if}
