{space10}

<center>
	{assign var="posArroba" value=$ticket->username|strpos:"@"}
	{noimage width="100" height="100" text="<small>@{$ticket->username|substr:0:$posArroba}</small>"}<br/>
	<small>Expira el {$ticket->limit_date|date_format:"%d/%m/%Y %I:%M %p"}</small><br/>
	Votos: {$ticket->likes_count} {*tag caption="{$ticket->likes_count}"*}
</center>

{space10}

<p>{$ticket->body}</p>

{space10}

<center>
	{if $voteButtonEnabled == true}
		{button href="SUGERENCIAS VOTAR {$ticket->id}" desc="Votar por esta idea" caption="&#x1f44d; Votar" popup="true"}
	{/if}
	{button href="SUGERENCIAS" caption="Atr&aacute;s" color="grey"}
</center>
