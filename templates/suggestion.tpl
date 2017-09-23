<center>
	Sugerida por @{$suggestion->username}<br/>
	{if $suggestion->status == "NEW"}
		Expira el {$suggestion->limit_date|date_format:"%d/%m/%Y a las %I:%M %p"}<br/>
		Faltan {$suggestion->limit_votes - $suggestion->votes_count} votos para aprobarla<br/>
	{/if}
	Estado: {tag caption="{$suggestion->estado}"}
</center>

{space5}

<p style="background-color:#F2F2F2; padding:10px;"><big>{$suggestion->text}</big></p>

{space5}

<center>
	{if $voteButtonEnabled}
		{button href="SUGERENCIAS VOTAR {$suggestion->id}" caption="&#x1f44d; Votar"}
	{/if}
	{button href="SUGERENCIAS" caption="Atr&aacute;s" color="grey"}
</center>
