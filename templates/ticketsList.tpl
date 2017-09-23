<h1>Sugerencias abiertas</h1>
<p>Escriba una sugerencia para nuestra app o vote por alguna sugerencia abierta. Si su sugerencia es aprobada, ganar&aacute; cr&eacute;ditos. Lease las reglas para aprender m&aacute;s.</p>
{space5}

<table width="100%" cellspacing="0" cellpadding="10" border="0">
	{foreach from=$tickets key=key item=ticket}
		{assign var="posArroba" value=$ticket->user|strpos:"@"}
		{assign var="bgcolor" value="{cycle values="#f2f2f2,white"}"}
		{assign var="person" value="<small>{$ticket->user|substr:0:$posArroba}</small>"}
		{if $ticket->user == $userEmail}
			{assign var="bgcolor" value="#d6f8d6"}
			{assign var="person" value="YO"}
		{/if}
		<tr bgcolor="{$bgcolor}">
			<!--image-->
			<td align="left">{noimage width="50" height="50" text="{$person}"}</td>
			<!--ticket-->
			<td width="100%">
				{assign var="votosRestantes" value = ($ticket->limit_votes - $ticket->likes_count)}
				{assign var="aux1" value=""}
				{assign var="aux2" value=""}
				{if $votosRestantes != 1}
					{assign var="aux1" value="n"}
					{assign var="aux2" value="s"}
				{/if}
				<small><font color="gray">Falta{$aux1} {$votosRestantes} voto{$aux2} para aceptar esta idea, Expira el {$ticket->limit_date|date_format:"%d/%m/%Y %I:%M %p"}</font></small><br/>
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
{space5}
{if $voteButtonEnabled == false}
	<p>No puedes votar por ahora porque ya no tienes ning&uacute;n voto disponible. Debes esperar a que sean aprobadas o descartadas las sugerencias por las que votaste para poder votar por alg&uacute;na otra.</p>
{else}
	<center><p>Votos disponibles: {$votosDisp}.</p></center>
{/if}
{space10}

<center>
	{button href="SUGERENCIAS" desc="Describa su idea o sugerencia" caption="&#10010; Agregar" popup="true"}
</center>
