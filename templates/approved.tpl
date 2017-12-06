<h1>{$subject}</h1>
<table width="100%" cellspacing="0" cellpadding="5" border="0">
    {foreach from=$tickets item=ticket}
    {assign var="bgcolor" value="{cycle values="#f2f2f2,white"}"}

    <tr bgcolor="{$bgcolor}">
        <td>
            <!--details-->
            <small><font color="gray">Creada por {link href="PERFIL @{$ticket->username}" caption="@{$ticket->username}"}.</font></small>
            <br/>

            <!--content-->
            {$ticket->text|truncate:100:"... "}
            {space5}

            <!--buttons-->
            {button href="SUGERENCIAS VER {$ticket->id}" caption="&#x1f50d; Leer" size="small"}
        </td>
    </tr>
    {/foreach}
</table>

{space15}

<center>
    {button href="SUGERENCIAS CREAR" desc="Describa su idea o sugerencia" caption="&#10010; Agregar" popup="true"}
    {button href="SUGERENCIAS REGLAS" caption="Reglas" color="grey"}
</center>
