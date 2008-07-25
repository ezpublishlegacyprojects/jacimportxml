<h1>export.tpl - Import XML - exportiere Content</h1>

<form action="" method="GET">
Welche Node soll exportiert werden <input type="text" size="4" name="exportNodeId" value="{$export_node_id}"><br />
BaumTiefe <input type="text" size="4" name="treeDepth" value="{$tree_depth}"><br />

<input type="submit" name="ExportButton" value="exportieren"><br />
</form>

<pre>
{$content}
</pre>

<h2>Exportierte Dateien Anzahl = {$exported_files|count()}</h2>
<ul>
{foreach $exported_files as $filename}
<li><a href={$filename|ezroot()}>{$filename}</a></li>
{/foreach}
</ul>

<hr>


{*$contentobject|attribute(show)*}