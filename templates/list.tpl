<h1>Sugerencias abiertas</h1>
<p>Escriba una sugerencia para nuestra app o vote por alguna sugerencia abierta. Si su sugerencia es aprobada, ganar&aacute; cr&eacute;ditos.</p>

<center>
{if $voteButtonEnabled}
	<p>Usted tiene {$votosDisp} votos disponibles</p>
{else}
	<p><small><font color="red">No tiene votos disponibles. Debe esperar que las sugerencias que vot&oacute; sean aprobadas o descartadas.</font></small></p>
{/if}
</center>

<table width="100%" cellspacing="0" cellpadding="5" border="0">
	{foreach from=$tickets item=ticket}
		{assign var="bgcolor" value="{cycle values="#f2f2f2,white"}"}

		<tr bgcolor="{$bgcolor}">
			<td>
				<!--details-->
				<small><font color="gray">Creda por {link href="PERFIL @{$ticket->username}" caption="@{$ticket->username}"}. Expira el {$ticket->limit_date|date_format:"%d/%m"}. Faltan {$ticket->limit_votes - $ticket->votes_count} votos</font></small>
				<br/>

				<!--content-->
				{$ticket->text|truncate:100:"... "}
				{space5}

				<!--buttons-->
				{button href="SUGERENCIAS VER {$ticket->id}" caption="&#x1f50d; Leer" size="small"}
				{if $voteButtonEnabled == true}
					{button href="SUGERENCIAS VOTAR {$ticket->id}" caption="&#x1f44d; Votar" size="small"}
				{/if}
			</td>
		</tr>
	{/foreach}
</table>

{space15}

<center>
	{button href="SUGERENCIAS CREAR" desc="Describa su idea o sugerencia" caption="&#10010; Agregar" popup="true"}
	{button href="SUGERENCIAS REGLAS" caption="Reglas" color="grey"}
</center>
