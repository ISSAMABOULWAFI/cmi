{if $status == 'ok'}
	<p>{l s='Your order on' mod='receiveandpay'} {$shop_name} {l s='has been registered successfuly.' mod='cmi'}</p>
	<p>{l s='For any extra question or information, please contact our' mod='cmi'} <a href="{$link->getPageLink('contact')}">{l s='customer support' mod='cmi'}</a>.</p>
{else}
	<p>{l s='We noticed a trouble during your order. If you think it is an error, you can contact our' mod='cmi'} <a href="{$link->getPageLink('contact')}">{l s='customer support' mod='cmi'}</a>.</p>
{/if}