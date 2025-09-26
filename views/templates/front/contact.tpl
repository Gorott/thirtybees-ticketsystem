<form action="{$link->getModuleLink('ticketsystem','contact')}" method="post" class="form-horizontal ticket-form">
    <div class="form-group">
        <label class="control-label col-sm-2" for="subject">
            {l s='Subject' mod='ticketsystem'}
        </label>
        <div class="col-sm-10">
            <input type="text" name="subject" id="subject" class="form-control" required>
        </div>
    </div>

    <div class="form-group">
        <label class="control-label col-sm-2" for="email">
            {l s='Your email' mod='ticketsystem'}
        </label>
        <div class="col-sm-10">
            {if $customer->isLogged() && $customer->email}
                <input type="email"
                       name="email"
                       id="email"
                       value="{$customer->email|escape:'html':'UTF-8'}"
                       class="form-control"
                       readonly>
            {else}
                <input type="email"
                       name="email"
                       id="email"
                       value=""
                       class="form-control"
                       required>
            {/if}
        </div>
    </div>

    <div class="form-group">
        <label class="control-label col-sm-2" for="order">
            {l s='Order' mod='ticketsystem'}
        </label>
        <div class="col-sm-10">
            {if $customer->isLogged() && isset($orders) && $orders|@count > 0}
                <select name="id_order" id="order" class="form-control">
                    <option value="">{l s='Select an order' mod='ticketsystem'}</option>
                    {foreach from=$orders item=order}
                        <option value="{$order.id_order}">
                            #{$order.id_order} — {$order.reference} ({$order.date_add|date_format:"%Y-%m-%d"})
                        </option>
                    {/foreach}
                </select>
            {elseif $customer->isLogged()}
                <p class="text-muted">{l s='No orders found' mod='ticketsystem'}</p>
            {else}
                <select class="form-control" disabled>
                    <option>{l s='Login to select an order' mod='ticketsystem'}</option>
                </select>
            {/if}
        </div>
    </div>


    <div class="form-group">
        <label class="control-label col-sm-2" for="message">
            {l s='Message' mod='ticketsystem'}
        </label>
        <div class="col-sm-10">
            <textarea name="message" id="message" rows="6" class="form-control" required></textarea>
        </div>
    </div>

    <div class="form-group">
        <div class="col-sm-offset-2 col-sm-10">
            <button type="submit" name="submitTicket" class="btn btn-primary">
                <i class="icon-envelope"></i> {l s='Send ticket' mod='ticketsystem'}
            </button>
        </div>
    </div>
</form>
