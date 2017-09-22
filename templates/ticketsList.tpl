<h1>Sugerencias abiertas</h1>
<p>Escriba una sugerencia para nuestra app o vote por alguna sugerencia abierta. Si su sugerencia es aprobada, ganar&aacute; cr&eacute;ditos. Lease las reglas para aprender m&aacute;s.</p>
{space5}

<table width="100%" cellspacing="0" cellpadding="10" border="0">
	{foreach from=$tickets key=key item=ticket}
	{assign var="posArroba" value=$ticket->user|strpos:"@"}
	{assign var="bgcolor" value="#C1C1C1"}
	{assign var="person" value="<small>{$ticket->user|substr:0:$posArroba}</small>"}
	{if $ticket->user == $userEmail}
		{assign var="bgcolor" value="white"}
		{assign var="person" value="YO"}
	{/if}
	<tr bgcolor="{$bgcolor}">
		<!--image-->
		<td align="left">{noimage width="50" height="50" text="{$person}"}</td>
		<!--ticket-->
		<td width="100%">
			{assign var="aux" value=""}
			{if $ticket->likes_count != 1}
				{assign var="aux" value="s"}
			{/if}
			<small><font color="gray">{$ticket->likes_count} voto{$aux}, Expira el {$ticket->limit_date|date_format:"%d/%m/%Y %I:%M %p"}</font></small><br/>
			{$ticket->body|truncate:100:"... "}
		</td>
		<!--buttons-->
		<td align="right">
			{button href="SUGERENCIAS VER {$ticket->id}" caption="&#x1f50d; Leer" size="small"}
		</td>
		{if $voteButtonEnabled == true}
		<td align="right">
			{button href="SUGERENCIAS VOTAR {$ticket->id}" caption="&#x1f44d; Votar" size="small"}
		</td>
		{/if}
	</tr>
	{/foreach}
</table>
{if $voteButtonEnabled == false}
	{space5}
	<p>No puedes votar por ahora porque ya no tienes ning&uacute;n voto disponible. Debes esperar a que sean aprobadas o descartadas las sugerencias por las que votaste para poder votar por alg&uacute;na otra.</p>
{/if}
{space10}

<center>
	{button href="SUGERENCIAS" desc="Describa su idea o sugerencia" caption="&#10010; Agregar" popup="true"}
</center>
