<div id="myTicketsPanel" style="display:none;">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-ticket"></i> {l s='Tickets'}
        </div>
        {if isset($tickets) && $tickets|@count > 0}
            <table class="table">
                <thead>
                <tr>
                    <th>{l s='ID'}</th>
                    <th>{l s='Subject'}</th>
                    <th>{l s='Status'}</th>
                    <th>{l s='Assignee'}</th>
                    <th>{l s='Last Updated'}</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$tickets item=ticket}
                    <tr data-href="{$link->getAdminLink('AdminTicketSystem')|escape:'html':'UTF-8'}&id_ticket={$ticket->id_ticket}&viewticket" style="cursor:pointer;">
                        <td>#{$ticket->id_ticket}</td>
                        <td>{$ticket->subject|escape:'html':'UTF-8'}</td>
                        <td>{$ticket->getStatus()->name}</td>
                        <td>
                            {if $ticket->id_assignee}
                                {$ticket->getAssignee()->firstname} {$ticket->getAssignee()->lastname}
                            {else}
                                No one
                            {/if}
                        </td>
                        <td>{$ticket->last_updated|relativetime}</td>
                    </tr>

                {/foreach}
                </tbody>
            </table>
        {else}
            <p class="text-muted text-center">{l s='No tickets found for this customer.'}</p>
        {/if}
    </div>
</div>



{literal}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var panels = document.querySelectorAll('.panel');
            panels.forEach(function(panel) {
                var heading = panel.querySelector('.panel-heading');
                if (heading && heading.textContent.trim().toLowerCase().includes("messages")) {
                    var ticketsPanel = document.getElementById('myTicketsPanel');
                    if (ticketsPanel) {
                        panel.parentNode.replaceChild(ticketsPanel, panel);
                        ticketsPanel.style.display = 'block';
                    }
                }
            });
        });

        document.querySelectorAll('#myTicketsPanel table tbody tr').forEach(function(row) {
            row.addEventListener('click', function (e) {
                var url = row.getAttribute('data-href');
                if (url) {
                    window.location.href = url;
                }
            });
        });
    </script>
{/literal}
