<h1>Sugerencias abiertas</h1>
<p>Escriba una sugerencia para nuestra app o vote por alguna sugerencia abierta. Si su sugerencia es aprobada, ganar&aacute; cr&eacute;ditos. Lea las reglas para aprender m&aacute;s.</p>

<center>
{if $voteButtonEnabled}
	<p>Usted tiene {$votosDisp} votos disponibles</p>
{else}
	<p><small><font color="red">No tiene votos disponibles. Debe esperar que las sugerencias que vot&oacute; sean aprobadas o descartadas.</font></small></p>
{/if}
</center>

<table width="100%" cellspacing="0" cellpadding="10" border="0">
	{foreach from=$tickets item=ticket}
		{assign var="posArroba" value=$ticket->user|strpos:"@"}
		{assign var="bgcolor" value="{cycle values="#f2f2f2,white"}"}
		{assign var="person" value="<small>{$ticket->user|substr:0:$posArroba}</small>"}
		{if $ticket->user == $userEmail}{assign var="person" value="YO"}{/if}

		<tr bgcolor="{$bgcolor}">
			<!--image-->
			<td align="left">{noimage width="50" height="50" text="{$person}"}</td>

			<!--content-->
			<td width="100%">
				<small><font color="gray">Expira el {$ticket->limit_date|date_format:"%d/%m/%Y"} y faltan {$ticket->limit_votes - $ticket->votes_count} votos</font></small>
				<br/>
				{$ticket->text|truncate:100:"... "}
			</td>

			<!--buttons-->
			<td align="center">
				{button href="SUGERENCIAS VER {$ticket->id}" caption="&#x1f50d; Leer" size="small"}
			</td>
			{if $voteButtonEnabled == true}
			<td align="center">
				{button href="SUGERENCIAS VOTAR {$ticket->id}" caption="&#x1f44d; Votar" size="small"}
			</td>
			{/if}
		</tr>
	{/foreach}
</table>

{space10}

<center>
	{button href="SUGERENCIAS CREAR" desc="Describa su idea o sugerencia" caption="&#10010; Agregar" popup="true"}
	{button href="SUGERENCIAS REGLAS" caption="Reglas" color="grey"}
</center>
