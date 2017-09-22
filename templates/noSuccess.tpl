<h1>{$titulo}</h1>

<p>{$mensaje}</p>
<p></p>

<center>
	{if $buttonNew == true}
		{button href="SUGERENCIAS" desc="Describa idea o sugerencia" caption="&#10010; Escribir" popup="true"}
	{/if}
	{if $buttonList == true}
		{button href="SUGERENCIAS" caption="Ver Lista" color="grey"}
	{/if}
</center>
